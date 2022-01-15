<?php declare(strict_types=1);

/**
 * Plugin Name: Debug Log Widget
 * Description: Adds an admin dashboard widget to parse the WordPress error log file.
 * Author: Austin Passy
 * Author URI: https://github.com/thefrosty
 * Version: 1.1.2
 * Requires at least: 5.4
 * Tested up to: 5.8.3
 * Requires PHP: 7.4
 * Plugin URI: https://github.com/thefrosty/wp-debug-log-widget
 * GitHub Plugin URI: https://github.com/thefrosty/wp-debug-log-widget
 * Primary Branch: develop
 * Release Asset: true
 */

namespace TheFrosty\WpDebugLogWidget;

\defined('ABSPATH') || exit;

use TheFrosty\WpUtilities\Plugin\PluginFactory;
use TheFrosty\WpUtilities\WpAdmin\DisablePluginUpdateCheck;

if (\is_readable(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

$plugin = PluginFactory::create('debug-log-widget');

$plugin
    ->add(new DisablePluginUpdateCheck())
    ->addOnHook(ErrorLog::class, 'load-index.php', 10, true)
    ->initialize();
