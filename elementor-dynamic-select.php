<?php
/**
 * Plugin Name: Elementor Dynamic Select
 * Plugin URI:  https://github.com/robbdeveloper/elementor-multiple-select-conditions
 * Description: Adds a Dynamic Select field to Elementor Pro forms with options driven by JSON rules and multi-select source fields.
 * Version:     1.0.1
 * Author:      Elementor Dynamic Select
 * Text Domain: elementor-dynamic-select
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 *
 * @package ElementorDynamicSelect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDS_VERSION', '1.0.1' );
define( 'EDS_PLUGIN_FILE', __FILE__ );
define( 'EDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Display admin notice when Elementor Pro is missing.
 *
 * @return void
 */
function eds_admin_notice_missing_elementor_pro() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['activate'] );
	}

	$message = sprintf(
		/* translators: 1: This plugin name, 2: Elementor Pro plugin name. */
		esc_html__( '"%1$s" requires "%2$s" to be installed and active.', 'elementor-dynamic-select' ),
		'<strong>' . esc_html__( 'Elementor Dynamic Select', 'elementor-dynamic-select' ) . '</strong>',
		'<strong>' . esc_html__( 'Elementor Pro', 'elementor-dynamic-select' ) . '</strong>'
	);

	printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', wp_kses_post( $message ) );
}

/**
 * Whether Elementor Pro (or compatible fork) is active.
 *
 * @return bool
 */
function eds_is_elementor_pro_active() {
	if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
		return true;
	}

	if ( class_exists( '\ElementorPro\Plugin' ) ) {
		return true;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$pro_plugins = [
		'elementor-pro/elementor-pro.php',
		'pro-elements/pro-elements.php',
	];

	foreach ( $pro_plugins as $plugin ) {
		if ( is_plugin_active( $plugin ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Load plugin classes and hooks.
 *
 * @return void
 */
function eds_bootstrap() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$loaded = true;

	require_once EDS_PLUGIN_DIR . 'includes/class-plugin.php';

	\ElementorDynamicSelect\Plugin::instance();
}

/**
 * Bootstrap the plugin as soon as we know Elementor Pro is present.
 *
 * This must run before `elementor/init`, because Elementor Pro fires
 * `elementor_pro/forms/fields/register` while building its modules (during
 * `elementor/init`), which is earlier than the `elementor_pro/init` action.
 * Hooking later would miss the field registration entirely.
 *
 * @return void
 */
function eds_register_hooks() {
	if ( ! eds_is_elementor_pro_active() ) {
		return;
	}

	eds_bootstrap();
}

/**
 * Show missing-Pro notice only after all plugins have had time to load.
 *
 * @return void
 */
function eds_maybe_show_missing_notice() {
	if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( eds_is_elementor_pro_active() ) {
		return;
	}

	add_action( 'admin_notices', 'eds_admin_notice_missing_elementor_pro' );
}

add_action( 'plugins_loaded', 'eds_register_hooks', 25 );
add_action( 'admin_init', 'eds_maybe_show_missing_notice', 20 );
