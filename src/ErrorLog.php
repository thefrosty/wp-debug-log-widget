<?php declare(strict_types=1);

namespace TheFrosty\WpDebugLogWidget;

use TheFrosty\WpUtilities\Plugin\AbstractPlugin;
use TheFrosty\WpUtilities\Plugin\HooksTrait;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use TheFrosty\WpUtilities\Plugin\PluginAwareInterface;
use TheFrosty\WpUtilities\Plugin\PluginAwareTrait;
use TheFrosty\WpUtilities\Plugin\WpHooksInterface;

/**
 * Class ErrorLog
 * @package TheFrosty\WpDebugLogWidget
 */
class ErrorLog extends AbstractPlugin implements HttpFoundationRequestInterface, PluginAwareInterface, WpHooksInterface
{

    use HooksTrait, HttpFoundationRequestTrait, PluginAwareTrait;

    public const ACTION_LOG_CLEARED = 'log_cleared';
    public const ARG_ACTION = 'wpdebugaction';
    public const ARG_CLEAR = 'clear';
    public const ARG_VIEW = 'view';
    public const TAG_CURRENT_USER_CAN = 'thefrosty/wp_debug_log_widget/current_user_can';
    public const TAG_LOG_FILE = 'thefrosty/wp_debug_log_widget/filename';
    public const TAG_LOG_FILE_LIMIT = 'thefrosty/wp_debug_log_widget/file_limit';
    public const TAG_LOG_FILE_LENGTH = 'thefrosty/wp_debug_log_widget/file_length';
    private const ACTION = self::class;
    private const KEY_S = '%s_wpdebuglog';
    private const NONCE = '_wpdebuglog_nonce';

    /** @var string $domain */
    private $domain;

    /** @var string $logfile */
    private $logfile;

    /**
     * ErrorLog constructor.
     */
    public function __construct()
    {
        $this->domain = \sanitize_key(\network_home_url());
        $this->logfile = \WP_CONTENT_DIR . '/debug.log';
    }

    public function addHooks(): void
    {
        $this->addAction('init', [$this, 'maybeRedirect'], 99);
        $this->addAction('wp_dashboard_setup', [$this, 'addDashboardWidget'], 99);
    }

    /**
     * Return the key.
     * @return string
     */
    public function getKey(): string
    {
        return \sprintf(self::KEY_S, $this->domain);
    }

    /**
     * Return the log filename.
     * Defaults to `wp-content/debug.log`.
     * @return string
     */
    public function getLogFileName(): string
    {
        return \apply_filters(self::TAG_LOG_FILE, $this->logfile);
    }

    /**
     * Return whether the current user can.
     * Defaults to a super admin.
     * @return bool
     */
    public function currentUserCan(): bool
    {
        return \apply_filters(self::TAG_CURRENT_USER_CAN, \is_super_admin(\get_current_user_id()));
    }

    /**
     * Maybe redirect on `init`.
     */
    protected function maybeRedirect(): void
    {
        $key = $this->getKey();
        $query = $this->getRequest()->query;
        if (!$this->currentUserCan() || !$query->has($key)) {
            return;
        }

        switch ($query->get($key)) {
            case self::ARG_CLEAR:
                if (!$query->get(self::NONCE) || !\wp_verify_nonce($query->get(self::NONCE), self::ACTION)) {
                    \wp_safe_redirect(\admin_url());
                    exit;
                }
                $handle = \fopen($this->logfile, 'w');
                \fclose($handle);
                \wp_safe_redirect(
                    \add_query_arg(self::ARG_ACTION, self::ACTION_LOG_CLEARED, \remove_query_arg([$key, self::NONCE]))
                );
                exit;
            case self::ARG_VIEW:
                if (!$query->get(self::NONCE) ||
                    !\wp_verify_nonce($query->get(self::NONCE), self::ACTION) ||
                    !\file_exists($this->logfile) ||
                    !\is_array(\file($this->logfile))
                ) {
                    \wp_safe_redirect(\admin_url());
                    exit;
                }
                $errors = \file($this->logfile);
                $this->formatErrors($errors, 1000, 10000);
                exit;
        }
    }

    /**
     * Register the dashboard widget.
     */
    protected function addDashboardWidget(): void
    {
        \wp_add_dashboard_widget(
            \sprintf('thefrosty-debug-log-%s', $this->domain),
            \esc_html__('Debug Log', 'wp-debug-log-widget'),
            function (): void {
                $this->dashboardHandler();
            }
        );
    }

    /**
     * Dashboard widget view handler callback.
     */
    private function dashboardHandler(): void
    {
        $filename = $this->getLogFileName();
        if (!\file_exists($filename) || \file($filename) === false) {
            \printf(
                '<p><em>%s <code>%s</code></em></p>',
                \esc_html__('There was a problem reading the debug log file.', 'wp-debug-log-widget'),
                \sanitize_file_name($filename)
            );

            return;
        }

        include $this->getPath('views/dashboard-widget.php');
    }

    /**
     * Format the error log array.
     * @param array $errors
     * @param int $length
     * @param int $limit
     */
    private function formatErrors(array $errors, int $length, int $limit): void
    {
        $height = $limit > 1000 ? '100%' : '250px';
        echo '<div id="' . $this->domain . '-php-errors" style="height:' . $height . ';overflow:scroll;padding:2px;background-color:#faf9f7;border:1px solid #ccc;">';
        echo '<ol style="padding:0;margin:0;">';

        $i = 0;
        foreach (\array_reverse($errors) as $error) {
            echo '<li style="padding:2px 4px 6px;border-bottom:1px solid #ececec;">';
            $errorOutput = \preg_replace('/\[([^]]+)]/', '<strong>[$1]</strong>', $error, 1);
            if (\strlen($errorOutput) > $length) {
                echo \substr(\strip_tags($errorOutput), 0, $length) . ' [...]';
            } else {
                echo $errorOutput;
            }
            echo '</li>';

            $i++;
            if ($i > $limit) {
                echo '<li style="padding:2px;border-bottom:2px solid #ccc;"><em>More than ' . $limit . ' errors in log...</em></li>';
                break;
            }
        }
        echo '</ol></div>';
    }
}
