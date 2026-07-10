<?php
/**
 * Dynamic Select form field.
 *
 * @package ElementorDynamicSelect
 */

namespace ElementorDynamicSelect\Fields;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use ElementorDynamicSelect\Rules_Evaluator;
use ElementorDynamicSelect\Rules_Schema;
use ElementorPro\Modules\Forms\Fields\Field_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Elementor Pro form field for dynamic select options.
 */
class Dynamic_Select_Field extends Field_Base {

	/**
	 * Field type identifier.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'eds_dynamic_select';
	}

	/**
	 * Field label in the editor.
	 *
	 * @return string
	 */
	public function get_name() {
		return esc_html__( 'Conditional Dynamic Select', 'elementor-dynamic-select' );
	}

	/**
	 * Script dependencies.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return [ 'eds-dynamic-select' ];
	}

	/**
	 * Style dependencies.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return [ 'widget-form' ];
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'elementor_pro/forms/render/item/' . $this->get_type(), [ $this, 'prepare_render_item' ], 10, 3 );
		add_action( 'elementor/preview/init', [ $this, 'editor_preview_footer' ] );
	}

	/**
	 * Apply native select field-group classes so form styles match built-in selects.
	 *
	 * @param array                                      $item       Field settings.
	 * @param int                                        $item_index Field index.
	 * @param \ElementorPro\Modules\Forms\Widgets\Form   $form       Form widget.
	 * @return array
	 */
	public function prepare_render_item( $item, $item_index, $form ) {
		$form->add_render_attribute( 'field-group' . $item_index, 'class', 'elementor-field-type-select' );

		return $item;
	}

	/**
	 * Enqueue preview assets in the editor.
	 *
	 * @return void
	 */
	public function editor_preview_footer() {
		wp_enqueue_script( 'eds-dynamic-select' );
	}

	/**
	 * Add field-specific controls.
	 *
	 * @param \Elementor\Widget_Base $widget Form widget instance.
	 * @return void
	 */
	public function update_controls( $widget ) {
		$elementor = \ElementorPro\Plugin::elementor();

		$control_data = $elementor->controls_manager->get_control_from_stack( $widget->get_unique_name(), 'form_fields' );

		if ( is_wp_error( $control_data ) ) {
			return;
		}

		$field_controls = [
			'eds_rules' => [
				'name'        => 'eds_rules',
				'label'       => esc_html__( 'Rules JSON', 'elementor-dynamic-select' ),
				'type'        => Controls_Manager::CODE,
				'language'    => 'json',
				'rows'        => 12,
				'description' => esc_html__( 'Paste a JSON ruleset that defines how options are populated based on other field values.', 'elementor-dynamic-select' ),
				'condition'   => [
					'field_type' => $this->get_type(),
				],
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			],
			'eds_rules_url' => [
				'name'        => 'eds_rules_url',
				'label'       => esc_html__( 'Rules JSON URL (optional)', 'elementor-dynamic-select' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => esc_html__( 'https://example.com/rules.json', 'elementor-dynamic-select' ),
				'description' => esc_html__( 'Optional fallback URL to load rules JSON from a file. Inline JSON takes precedence.', 'elementor-dynamic-select' ),
				'condition'   => [
					'field_type' => $this->get_type(),
				],
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			],
			'eds_placeholder' => [
				'name'      => 'eds_placeholder',
				'label'     => esc_html__( 'Placeholder Text', 'elementor-dynamic-select' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Select an option', 'elementor-dynamic-select' ),
				'condition' => [
					'field_type' => $this->get_type(),
				],
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			],
			'eds_no_match_text' => [
				'name'      => 'eds_no_match_text',
				'label'     => esc_html__( 'No Match Text', 'elementor-dynamic-select' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'No options available', 'elementor-dynamic-select' ),
				'condition' => [
					'field_type' => $this->get_type(),
				],
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			],
		];

		$control_data['fields'] = $this->inject_field_controls( $control_data['fields'], $field_controls );
		$widget->update_control( 'form_fields', $control_data );
	}

	/**
	 * Render the field on the frontend.
	 *
	 * @param array                         $item       Field settings.
	 * @param int                           $item_index Field index.
	 * @param \ElementorPro\Modules\Forms\Widgets\Form $form Form widget.
	 * @return void
	 */
	public function render( $item, $item_index, $form ) {
		$rules_payload = $this->get_rules_payload( $item );
		$rules_json    = ! empty( $rules_payload['ruleset'] )
			? wp_json_encode( $rules_payload['ruleset'] )
			: wp_json_encode( new \stdClass() );

		$input_size  = ! empty( $item['input_size'] ) ? $item['input_size'] : $form->get_settings_for_display( 'input_size' );
		$css_classes = ! empty( $item['css_classes'] ) ? $item['css_classes'] : '';

		$form->add_render_attribute(
			'select-wrapper' . $item_index,
			[
				'class' => array_values(
					array_filter(
						[
							'elementor-field',
							'elementor-select-wrapper',
							'remove-before',
							$css_classes,
						]
					)
				),
			]
		);

		$form->add_render_attribute(
			'select' . $item_index,
			[
				'name'                 => $form->get_attribute_name( $item ),
				'id'                   => $form->get_attribute_id( $item ),
				'class'                => [
					'elementor-field-textual',
					'elementor-size-' . $input_size,
					'eds-dynamic-select',
				],
				'data-eds-rules'       => esc_attr( $rules_json ),
				'data-eds-sources'     => esc_attr( wp_json_encode( $this->get_sources_payload( $item ) ) ),
				'data-eds-placeholder' => esc_attr( $this->get_placeholder_text( $item ) ),
				'data-eds-no-match'    => esc_attr( $this->get_no_match_text( $item ) ),
			]
		);

		if ( ! empty( $item['required'] ) ) {
			$form->add_render_attribute( 'select' . $item_index, 'required', 'required' );
			$form->add_render_attribute( 'select' . $item_index, 'aria-required', 'true' );
		}

		if ( ! empty( $rules_payload['errors'] ) ) {
			$form->add_render_attribute(
				'select' . $item_index,
				'data-eds-error',
				esc_attr( implode( ' ', $rules_payload['errors'] ) )
			);
		}
		?>
		<div <?php $form->print_render_attribute_string( 'select-wrapper' . $item_index ); ?>>
			<div class="select-caret-down-wrapper">
				<?php
				Icons_Manager::render_icon(
					[
						'library'  => 'eicons',
						'value'    => 'eicon-caret-down',
						'position' => 'right',
					],
					[ 'aria-hidden' => 'true' ]
				);
				?>
			</div>
			<select <?php $form->print_render_attribute_string( 'select' . $item_index ); ?>>
				<option value="" disabled selected><?php echo esc_html( $this->get_placeholder_text( $item ) ); ?></option>
			</select>
		</div>
		<?php
	}

	/**
	 * Validate submitted value against computed options.
	 *
	 * @param array                                      $field        Field data.
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record       Form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler.
	 * @return void
	 */
	public function validation( $field, $record, $ajax_handler ) {
		$field_settings = $this->get_field_settings_from_record( $field, $record );

		if ( null === $field_settings ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'Dynamic Select configuration is invalid.', 'elementor-dynamic-select' )
			);
			return;
		}

		$rules_payload = $this->get_rules_payload( $field_settings );

		if ( empty( $rules_payload['ruleset'] ) ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'Dynamic Select rules are invalid.', 'elementor-dynamic-select' )
			);
			return;
		}

		$source_values = $this->get_source_values_from_record( $rules_payload['ruleset'], $record );
		$submitted_value = isset( $field['value'] ) ? (string) $field['value'] : '';

		if ( ! empty( $field_settings['required'] ) && '' === $submitted_value ) {
			return;
		}

		if ( ! Rules_Evaluator::is_value_allowed( $rules_payload['ruleset'], $source_values, $submitted_value ) ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'The selected value is not allowed for the current field combination.', 'elementor-dynamic-select' )
			);
		}
	}

	/**
	 * Resolve rules payload for rendering/validation.
	 *
	 * @param array<string, mixed> $item Field settings.
	 * @return array{ruleset: array|null, errors: string[]}
	 */
	private function get_rules_payload( array $item ) {
		$inline_rules = isset( $item['eds_rules'] ) ? (string) $item['eds_rules'] : '';
		$result = Rules_Schema::parse_json( $inline_rules );

		if ( null !== $result['ruleset'] || empty( $item['eds_rules_url']['url'] ?? '' ) ) {
			return $result;
		}

		return Rules_Schema::load_from_url( (string) $item['eds_rules_url']['url'] );
	}

	/**
	 * Build sources payload for the frontend.
	 *
	 * @param array<string, mixed> $item Field settings.
	 * @return string[]
	 */
	private function get_sources_payload( array $item ) {
		$payload = $this->get_rules_payload( $item );

		if ( empty( $payload['ruleset']['sources'] ) ) {
			return [];
		}

		return array_values( $payload['ruleset']['sources'] );
	}

	/**
	 * Get placeholder text with fallback.
	 *
	 * @param array<string, mixed> $item Field settings.
	 * @return string
	 */
	private function get_placeholder_text( array $item ) {
		if ( ! empty( $item['eds_placeholder'] ) ) {
			return (string) $item['eds_placeholder'];
		}

		$payload = $this->get_rules_payload( $item );
		if ( ! empty( $payload['ruleset']['behavior']['emptyText'] ) ) {
			return (string) $payload['ruleset']['behavior']['emptyText'];
		}

		return esc_html__( 'Select an option', 'elementor-dynamic-select' );
	}

	/**
	 * Get no-match text with fallback.
	 *
	 * @param array<string, mixed> $item Field settings.
	 * @return string
	 */
	private function get_no_match_text( array $item ) {
		if ( ! empty( $item['eds_no_match_text'] ) ) {
			return (string) $item['eds_no_match_text'];
		}

		$payload = $this->get_rules_payload( $item );
		if ( ! empty( $payload['ruleset']['behavior']['noMatchText'] ) ) {
			return (string) $payload['ruleset']['behavior']['noMatchText'];
		}

		return esc_html__( 'No options available', 'elementor-dynamic-select' );
	}

	/**
	 * Find field settings in the form record.
	 *
	 * @param array                                      $field  Submitted field.
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record Form record.
	 * @return array<string, mixed>|null
	 */
	private function get_field_settings_from_record( array $field, $record ) {
		$form_settings = $record->get( 'form_settings' );

		if ( empty( $form_settings['form_fields'] ) || ! is_array( $form_settings['form_fields'] ) ) {
			return null;
		}

		foreach ( $form_settings['form_fields'] as $form_field ) {
			$field_id = $form_field['custom_id'] ?? $form_field['_id'] ?? '';

			if ( (string) $field_id === (string) $field['id'] ) {
				return $form_field;
			}
		}

		return null;
	}

	/**
	 * Collect source field values from the submitted record.
	 *
	 * @param array<string, mixed>                       $ruleset Normalized ruleset.
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record  Form record.
	 * @return array<string, string|string[]>
	 */
	private function get_source_values_from_record( array $ruleset, $record ) {
		$values = [];
		$fields = $record->get( 'fields' );
		$sources = $ruleset['sources'] ?? [];

		if ( empty( $fields ) || ! is_array( $fields ) || empty( $sources ) ) {
			return $values;
		}

		foreach ( $fields as $field ) {
			if ( empty( $field['id'] ) || ! in_array( $field['id'], $sources, true ) ) {
				continue;
			}

			$values[ $field['id'] ] = $field['value'] ?? '';
		}

		return $values;
	}
}
