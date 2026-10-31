<?php
/**
 * Plugin Name:       Custom Author Box
 * Description:       Elementor author box with selectable layouts, managed authors, and Yoast SEO author tags.
 * Version:           3.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  elementor
 * Author:            Black Digital
 * Text Domain:       custom-author-box
 * License:           GPL-2.0-or-later
 */

// This file deliberately uses only old PHP syntax so that an outdated server
// shows an admin notice instead of a fatal error.

if (!defined('ABSPATH')) {
  exit;
}

define('CUSTOM_AUTHOR_BOX_VERSION', '3.0.0');
define('CUSTOM_AUTHOR_BOX_FILE', __FILE__);
define('CUSTOM_AUTHOR_BOX_DIR', plugin_dir_path(__FILE__));
define('CUSTOM_AUTHOR_BOX_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '8.1', '<')) {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-error"><p>'
      . esc_html__('Custom Author Box requires PHP 8.1 or newer. The plugin is inactive until PHP is updated.', 'custom-author-box')
      . '</p></div>';
  });
  return;
}

spl_autoload_register(function ($class) {
  $prefix = 'BlackDigital\\CustomAuthorBox\\';
  if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
    return;
  }
  $relative = substr($class, strlen($prefix));
  $file     = CUSTOM_AUTHOR_BOX_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';
  if (is_readable($file)) {
    require_once $file;
  }
});

register_activation_hook(__FILE__, array('BlackDigital\\CustomAuthorBox\\Plugin', 'activate'));
add_action('plugins_loaded', array('BlackDigital\\CustomAuthorBox\\Plugin', 'boot'));
