<?php

declare(strict_types=1); // phpcs:disable

use TheFrosty\WpDebugLogWidget\ErrorLog;

if (!($this instanceof ErrorLog)) {
    wp_die(
        sprintf(
            'Please don\'t load this file outside of <code>%s.</code>',
            esc_attr(ErrorLog::class)
        )
    );
}

$errors = file($this->getLogFileName());
$length = absint(apply_filters(ErrorLog::TAG_LOG_FILE_LENGTH, 300));
$limit = absint(apply_filters(ErrorLog::TAG_LOG_FILE_LIMIT, 100));
$query = $this->getRequest()->query;

if ($query->has(ErrorLog::ARG_ACTION) && $query->get(ErrorLog::ARG_ACTION) === ErrorLog::ACTION_LOG_CLEARED) {
    printf('<p><em>%s</em></p>', esc_html__('Debug log file cleared.', 'wp-debug-log-widget'));
}

if (empty($errors)) {
    echo wpautop(esc_html__('No errors currently logged', 'wp-debug-log-widget'));

    return;
}

$html = sprintf(
    _n(
        '<span id="wp-debug-errors-count">%s error</span>',
        '<span id="wp-debug-errors-count">%s errors</span>',
        count($errors),
        'wp-debug-log-widget'
    ),
    number_format_i18n(count($errors))
);

if ($this->currentUserCan()) {
    $html .= sprintf(
        '&nbsp;[<strong><a id="wp-debug-log-widget__clear" href="%s" >%s</a></strong>]',
        esc_url(
            wp_nonce_url(add_query_arg(ErrorLog::KEY, ErrorLog::ARG_CLEAR, ''), ErrorLog::ACTION, ErrorLog::NONCE)
        ),
        esc_html__('CLEAR LOG FILE', 'wp-debug-log-widget')
    );
    $html .= sprintf(
        '&nbsp;[<strong><a href="%s" target="_blank">%s</a></strong>]',
        esc_url(
            wp_nonce_url(add_query_arg(ErrorLog::KEY, ErrorLog::ARG_VIEW, ''), ErrorLog::ACTION, ErrorLog::NONCE)
        ),
        esc_html__('VIEW LOG FILE', 'wp-debug-log-widget')
    );
}

echo wpautop($html);

try {
    $formatErrors = (new ReflectionObject($this))->getMethod('formatErrors');
    $formatErrors->invoke($this, $errors, $length, $limit);
} catch (Throwable $throwable) { // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedExceptions.NonFullyQualifiedException
    echo wpautop($throwable->getMessage());
}
