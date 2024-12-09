<?php
/**
 * Plugin Name: Sitemap HTML
 * Plugin URI: https://github.com/xwp/sitemap-html
 * Description: Generates a Monthly Sitemap in HTML for your WordPress site.
 * Version: 1.0.0
 * Author: XWP
 * Author URI: https://xwp.co
 * License: GPLv2+
 * Text Domain: sitemap-html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'SITEMAP_HTML_VERSION', '1.0.0' );
define( 'SITEMAP_HTML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITEMAP_HTML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Classes.
require_once SITEMAP_HTML_PLUGIN_DIR . 'includes/class-singleton.php';
require_once SITEMAP_HTML_PLUGIN_DIR . 'includes/class-posts.php';
require_once SITEMAP_HTML_PLUGIN_DIR . 'includes/class-date.php';
require_once SITEMAP_HTML_PLUGIN_DIR . 'includes/class-plugin.php';

// Initialize the plugin.
function sitemap_html_init() {
	SitemapHtml\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'sitemap_html_init' );

// Activation Hook.
register_activation_hook( __FILE__, 'sitemap_html_activate' );

function sitemap_html_activate() {
	$plugin = SitemapHtml\Plugin::get_instance();
	$plugin->update_sitemap();
	$plugin->create_sitemap_page();
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	flush_rewrite_rules(); // Flush rewrite rules after creating page.
}

// Deactivation Hook.
register_deactivation_hook( __FILE__, 'sitemap_html_deactivate' );

function sitemap_html_deactivate() {
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	flush_rewrite_rules(); // Flush rewrite rules on deactivation.
}
