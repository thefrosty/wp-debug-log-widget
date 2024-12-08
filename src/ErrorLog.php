<?php

declare(strict_types=1);

namespace TheFrosty\WpDebugLogWidget;

use Exception;
use TheFrosty\WpUtilities\Plugin\AbstractHookProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use function add_query_arg;
use function apply_filters;
use function array_reverse;
use function check_ajax_referer;
use function esc_html__;
use function fclose;
use function file;
use function file_exists;
use function filesize;
use function fopen;
use function get_current_user_id;
use function intval;
use function is_array;
use function is_resource;
use function is_super_admin;
use function network_home_url;
use function parse_url;
use function preg_match;
use function preg_replace;
use function printf;
use function remove_query_arg;
use function round;
use function sanitize_key;
use function sprintf;
use function strip_tags;
use function strlen;
use function strtolower;
use function substr;
use function wp_add_dashboard_widget;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_register_script;
use function wp_safe_redirect;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_verify_nonce;
use const WP_CONTENT_DIR;
use const WP_MEMORY_LIMIT;

/**
 * Class ErrorLog
 * @package TheFrosty\WpDebugLogWidget
 * phpcs:disable SlevomatCodingStandard.Files.TypeNameMatchesFileName.NoMatchBetweenTypeNameAndFileName
 * phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
 */
class ErrorLog extends AbstractHookProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait;

    public const ACTION_LOG_CLEARED = 'log_cleared';
    public const ARG_ACTION = 'wpdebugaction';
    public const ARG_CLEAR = 'clear';
    public const ARG_VIEW = 'view';
    public const TAG_CURRENT_USER_CAN = 'thefrosty/wp_debug_log_widget/current_user_can';
    public const TAG_LOG_FILE = 'thefrosty/wp_debug_log_widget/filename';
    public const TAG_LOG_FILE_LIMIT = 'thefrosty/wp_debug_log_widget/file_limit';
    public const TAG_LOG_FILE_LENGTH = 'thefrosty/wp_debug_log_widget/file_length';
    public const ACTION = self::class;
    public const KEY = 'wpdebuglog';
    public const NONCE = '_wpdebuglog_nonce';

    /**
     * Domain (host).
     * @var string $domain
     */
    private string $domain;

    /**
     * Location of the logfile.
     * @var string $logfile
     */
    private string $logfile;

    /**
     * ErrorLog constructor.
     */
    public function __construct()
    {
        $this->domain = network_home_url();
        $this->logfile = WP_CONTENT_DIR . '/debug.log';
    }

    public function addHooks(): void
    {
        $this->addAction('load-index.php', [$this, 'maybeRedirect'], 0);
        $this->addAction('wp_dashboard_setup', [$this, 'addDashboardWidget'], 99);
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueScript']);
        $this->addAction('wp_ajax_wp_debug_log_clear', [$this, 'wpDebugLogClear']);
    }

    /**
     * Return the sanitized domain host.
     * @return string
     */
    public function getDomain(): string
    {
        return sanitize_key(parse_url($this->domain, \PHP_URL_HOST));
    }

    /**
     * Return the log filename.
     * Defaults to `wp-content/debug.log`.
     * @return string
     */
    public function getLogFileName(): string
    {
        return apply_filters(self::TAG_LOG_FILE, $this->logfile);
    }

    /**
     * Return whether the current user can.
     * Defaults to a super admin.
     * @return bool
     */
    public function currentUserCan(): bool
    {
        return apply_filters(self::TAG_CURRENT_USER_CAN, is_super_admin(get_current_user_id()));
    }

    /**
     * Maybe redirect on `init`.
     */
    protected function maybeRedirect(): void
    {
        $query = $this->getRequest()->query;
        if (!$this->currentUserCan() || !$query->has(self::KEY)) {
            return;
        }

        switch ($query->get(self::KEY)) {
            case self::ARG_CLEAR:
                if (!$query->get(self::NONCE) || !wp_verify_nonce($query->get(self::NONCE), self::ACTION)) {
                    wp_safe_redirect(\admin_url());
                    exit;
                }
                $stream = fopen($this->logfile, 'w');
                fclose($stream);
                wp_safe_redirect(
                    add_query_arg(
                        self::ARG_ACTION,
                        self::ACTION_LOG_CLEARED,
                        remove_query_arg([self::KEY, self::NONCE])
                    )
                );
                exit;
            case self::ARG_VIEW:
                if (!$query->get(self::NONCE) ||
                    !wp_verify_nonce($query->get(self::NONCE), self::ACTION) ||
                    !file_exists($this->logfile) ||
                    !is_array(file($this->logfile))
                ) {
                    wp_safe_redirect(\admin_url());
                    exit;
                }
                $errors = file($this->logfile);
                $this->formatErrors($errors, 1000, 10000);
                exit;
        }
    }

    /**
     * Register the dashboard widget.
     */
    protected function addDashboardWidget(): void
    {
        wp_add_dashboard_widget(
            sprintf('thefrosty-debug-log-%s', $this->getDomain()),
            esc_html__('Debug Log', 'wp-debug-log-widget'),
            function (): void {
                $this->dashboardHandler();
            }
        );
    }

    /**
     * Register our inline script.
     * @param string $hook
     */
    protected function enqueueScript(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }

        $admin_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce(ErrorLog::ACTION);
        $data = <<< SCRIPT
<script>
(function($) {
  $(document).ready(function() {
      $('a#wp-debug-log-widget__clear').on('click', (e) => {
        e.preventDefault()
        if (!confirm('Clear the debug log?')) {
            return;
        }
        
        const success = (response) => {
          if (response.success) {
            const errors = $('div[id$="-php-errors"]')
            errors.fadeOut('slow')
            setTimeout(() => {
              $('span#wp-debug-errors-count').text('0 errors')
              errors.remove()
            }, 250)
          }
        }
        $.post('$admin_url', { 'action': 'wp_debug_log_clear', 'nonce': '$nonce' }, success)
      })
  })
})(jQuery)
</script>
SCRIPT;
        wp_register_script('wp-debug-log-widget', '', ['jquery']);
        wp_enqueue_script('wp-debug-log-widget');
        wp_add_inline_script('wp-debug-log-widget', strip_tags($data));
    }

    /**
     * Clear the debug.log.
     */
    protected function wpDebugLogClear(): void
    {
        check_ajax_referer(ErrorLog::ACTION, 'nonce');

        $stream = fopen($this->logfile, 'w');
        if (is_resource($stream)) {
            fclose($stream);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    /**
     * Dashboard widget view handler callback.
     */
    private function dashboardHandler(): void
    {
        $filename = $this->getLogFileName();
        if (!file_exists($filename) || !is_file($filename)) {
            printf(
                '<p><em>%s <code>%s</code></em></p>',
                esc_html__('There was a problem reading the debug log file.', 'wp-debug-log-widget'),
                $filename
            );

            return;
        }

        try {
            $file_size = filesize($filename);
            $memory_limit_bytes = $this->byteConvert(WP_MEMORY_LIMIT);
            // If there is an error.
            if ($file_size === false || !$memory_limit_bytes) {
                throw new Exception('Error reading filesize or getting WP_MEMORY_LIMIT');
            }
            // If the file size is greater than our allowed server memory (minus 5MB).
            if ($file_size >= ($memory_limit_bytes - (5 * 1024))) {
                throw new Exception(
                    sprintf(
                        'File size (%s MB) exceeds the memory limit defined by WP_MEMORY_LIMIT of %s. 
                        Not parsing to avoid possible exhaustion errors.',
                        round($file_size / 1024 / 1024, 2),
                        WP_MEMORY_LIMIT
                    )
                );
            }
        } catch (Exception $e) {
            printf('<p><em>%s <br><code>%s</code></em></p>', esc_html($e->getMessage()), $filename);

            return;
        }

        include $this->getPlugin()->getPath('src/views/dashboard-widget.php');
    }

    /**
     * Format the error log array.
     * @param array $errors
     * @param int $length
     * @param int $limit
     */
    private function formatErrors(array $errors, int $length, int $limit): void
    {
        printf(
            '<div id="%s-php-errors" style="height:%s;overflow:scroll;padding:0;border:1px solid #ccc;">',
            $this->getDomain(),
            $limit >= 1000 ? '100%' : '350px'
        );
        echo '<ol style="padding:0;margin:0;">';

        $i = 0;
        foreach (array_reverse($errors) as $error) {
            $i++; // phpcs:ignore
            printf(
                '<li style="padding:%s;background-color:%s;border-bottom:1px solid #ececec;margin:0">',
                $limit >= 1000 ? '15px 5px' : '8px 5px 10px',
                $i % 2 === 0 ? '#faf9f7' : '#fdfdfd'
            );
            $errorOutput = preg_replace('/\[([^]]+)]/', '<strong>[$1]</strong>', $error, 1);

            if (strlen($errorOutput) > $length) {
                echo substr(strip_tags($errorOutput, 'strong'), 0, $length) . ' [...]';
            } else {
                echo $errorOutput;
            }
            echo '</li>';

            if ($i > $limit) {
                printf(
                    '<li style="padding:2px;border-bottom:2px solid #ccc;"><em>%s</em></li>',
                    sprintf(esc_html__('More than %d errors in log...', 'wp-error-log-widget'), $limit)
                );

                break;
            }
        }
        echo '</ol></div>';
    }

    /**
     * Convert an input value to Bytes.
     * @link https://stackoverflow.com/a/11813414/558561
     * @param mixed $input
     * @return int|null
     */
    private function byteConvert(mixed $input): ?int
    {
        preg_match('/(\d+)(\w+)/', $input, $matches);
        $type = !isset($matches[2]) ? '' : strtolower($matches[2]);
        $output = match ($type) {
            'b' => $matches[1],
            'kb' => $matches[1] * 1024,
            'm', 'mb' => $matches[1] * 1024 * 1024,
            'g', 'gb', 't', 'tb' => $matches[1] * 1024 * 1024 * 1024,
            default => null,  // or handle the default case
        };

        return !isset($output) ? null : intval($output);
    }
}
