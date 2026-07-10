<?php
/**
 * Main plugin bootstrap.
 *
 * @package ElementorDynamicSelect
 */

namespace ElementorDynamicSelect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->hooks();
	}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once EDS_PLUGIN_DIR . 'includes/class-rules-schema.php';
		require_once EDS_PLUGIN_DIR . 'includes/class-rules-evaluator.php';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'elementor/preview/enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_editor_preview' ] );
		add_action( 'elementor_pro/forms/fields/register', [ $this, 'register_fields' ] );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_script(
			'eds-dynamic-select',
			EDS_PLUGIN_URL . 'assets/js/dynamic-select.js',
			[],
			EDS_VERSION,
			true
		);

		wp_register_script(
			'eds-editor-preview',
			EDS_PLUGIN_URL . 'assets/js/editor-preview.js',
			[ 'jquery' ],
			EDS_VERSION,
			true
		);
	}

	/**
	 * Enqueue editor preview script.
	 *
	 * @return void
	 */
	public function enqueue_editor_preview() {
		wp_enqueue_script( 'eds-editor-preview' );
	}

	/**
	 * Register custom form fields.
	 *
	 * @param \ElementorPro\Modules\Forms\Registrars\Form_Fields_Registrar $form_fields_registrar Field registrar.
	 * @return void
	 */
	public function register_fields( $form_fields_registrar ) {
		// Loaded lazily: the parent Field_Base class only exists once the
		// Forms module has initialized, which is when this hook fires.
		require_once EDS_PLUGIN_DIR . 'includes/fields/class-dynamic-select-field.php';

		$form_fields_registrar->register( new Fields\Dynamic_Select_Field() );
	}
}
