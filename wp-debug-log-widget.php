<?php // phpcs:disable

declare(strict_types=1);

/**
 * Plugin Name: Debug Log Widget
 * Description: Adds an admin dashboard widget to parse the WordPress error log file.
 * Author: Austin Passy
 * Author URI: https://github.com/thefrosty
 * Version: 1.4.5
 * Requires at least: 6.6
 * Tested up to: 6.8.0
 * Requires PHP: 8.3
 * Plugin URI: https://github.com/thefrosty/wp-debug-log-widget
 * GitHub Plugin URI: https://github.com/thefrosty/wp-debug-log-widget
 * Update URI: https://github.com/thefrosty/wp-debug-log-widget
 * Primary Branch: develop
 * Release Asset: true
 * phpcs:enable
 */

namespace TheFrosty\WpDebugLogWidget;

\defined('ABSPATH') || exit;

use TheFrosty\WpUtilities\Plugin\PluginFactory;
use TheFrosty\WpUtilities\WpAdmin\DisablePluginUpdateCheck;

if (\is_readable(__DIR__ . '/vendor/autoload.php')) {
    include __DIR__ . '/vendor/autoload.php';
}

$plugin = PluginFactory::create('debug-log-widget');

if (\is_admin()) {
    $plugin
        ->add(new DisablePluginUpdateCheck())
        ->addOnHook(ErrorLog::class, 'admin_init', 10, true);
}

\add_action('init', static function () use ($plugin) {
    $plugin->initialize();
});
