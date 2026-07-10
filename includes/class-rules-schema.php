<?php
/**
 * Rules schema normalization and validation.
 *
 * @package ElementorDynamicSelect
 */

namespace ElementorDynamicSelect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and normalizes JSON rulesets.
 */
class Rules_Schema {

	/**
	 * Supported condition operators.
	 *
	 * @var string[]
	 */
	private const OPERATORS = [
		'includes',
		'includesAll',
		'includesAny',
		'intersects',
		'equals',
		'in',
		'any',
	];

	/**
	 * Parse raw JSON string into a normalized ruleset.
	 *
	 * @param string $json Raw JSON string.
	 * @return array{ruleset: array|null, errors: string[]}
	 */
	public static function parse_json( $json ) {
		$errors = [];

		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return [
				'ruleset' => null,
				'errors'  => [ __( 'Rules JSON is empty.', 'elementor-dynamic-select' ) ],
			];
		}

		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return [
				'ruleset' => null,
				'errors'  => [ __( 'Rules JSON is invalid.', 'elementor-dynamic-select' ) ],
			];
		}

		return self::normalize( $data );
	}

	/**
	 * Load rules from a remote URL.
	 *
	 * @param string $url Rules JSON URL.
	 * @return array{ruleset: array|null, errors: string[]}
	 */
	public static function load_from_url( $url ) {
		if ( empty( $url ) ) {
			return [
				'ruleset' => null,
				'errors'  => [],
			];
		}

		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			return [
				'ruleset' => null,
				'errors'  => [ $response->get_error_message() ],
			];
		}

		$body = wp_remote_retrieve_body( $response );

		return self::parse_json( $body );
	}

	/**
	 * Normalize a decoded ruleset array.
	 *
	 * @param array<string, mixed> $data Raw ruleset data.
	 * @return array{ruleset: array|null, errors: string[]}
	 */
	public static function normalize( array $data ) {
		$errors = [];

		$version = isset( $data['version'] ) ? (int) $data['version'] : 1;
		if ( 1 !== $version ) {
			$errors[] = __( 'Unsupported rules version. Only version 1 is supported.', 'elementor-dynamic-select' );
		}

		$behavior = self::normalize_behavior( $data['behavior'] ?? [] );
		$option_sets = self::normalize_option_sets( $data['optionSets'] ?? [], $errors );
		$rules = self::normalize_rules( $data['rules'] ?? [], $errors );
		$default = self::normalize_default( $data['default'] ?? [], $option_sets, $errors );

		$sources = [];
		if ( ! empty( $data['sources'] ) && is_array( $data['sources'] ) ) {
			foreach ( $data['sources'] as $source ) {
				if ( is_string( $source ) && '' !== $source ) {
					$sources[] = $source;
				}
			}
		}

		$sources = array_values( array_unique( array_merge( $sources, self::collect_source_fields( $rules ) ) ) );

		if ( empty( $rules ) && null === $default ) {
			$errors[] = __( 'Ruleset must define at least one rule or a default.', 'elementor-dynamic-select' );
		}

		if ( ! empty( $errors ) ) {
			return [
				'ruleset' => null,
				'errors'  => $errors,
			];
		}

		return [
			'ruleset' => [
				'version'    => 1,
				'behavior'   => $behavior,
				'sources'    => $sources,
				'optionSets' => $option_sets,
				'rules'      => $rules,
				'default'    => $default,
			],
			'errors'  => [],
		];
	}

	/**
	 * Normalize behavior settings.
	 *
	 * @param mixed $behavior Raw behavior data.
	 * @return array<string, mixed>
	 */
	private static function normalize_behavior( $behavior ) {
		if ( ! is_array( $behavior ) ) {
			$behavior = [];
		}

		$match = isset( $behavior['match'] ) ? (string) $behavior['match'] : 'first';
		if ( ! in_array( $match, [ 'first', 'merge' ], true ) ) {
			$match = 'first';
		}

		return [
			'match'              => $match,
			'preserveSelection'  => ! empty( $behavior['preserveSelection'] ),
			'emptyText'          => isset( $behavior['emptyText'] ) ? (string) $behavior['emptyText'] : '',
			'noMatchText'        => isset( $behavior['noMatchText'] ) ? (string) $behavior['noMatchText'] : '',
		];
	}

	/**
	 * Normalize reusable option sets.
	 *
	 * @param mixed    $option_sets Raw option sets.
	 * @param string[] $errors      Collected errors.
	 * @return array<string, array<int, array{value: string, label: string}>>
	 */
	private static function normalize_option_sets( $option_sets, array &$errors ) {
		$normalized = [];

		if ( ! is_array( $option_sets ) ) {
			return $normalized;
		}

		foreach ( $option_sets as $key => $options ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}

			$normalized_options = self::normalize_options( $options, $errors, $key );
			if ( ! empty( $normalized_options ) ) {
				$normalized[ $key ] = $normalized_options;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize rules array.
	 *
	 * @param mixed    $rules  Raw rules.
	 * @param string[] $errors Collected errors.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_rules( $rules, array &$errors ) {
		$normalized = [];

		if ( ! is_array( $rules ) ) {
			return $normalized;
		}

		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				$errors[] = sprintf(
					/* translators: %d: rule index. */
					__( 'Rule at index %d is invalid.', 'elementor-dynamic-select' ),
					(int) $index
				);
				continue;
			}

			$when = self::normalize_when( $rule['when'] ?? [], $errors, (int) $index );
			$options = [];
			$use = null;

			if ( isset( $rule['use'] ) && is_string( $rule['use'] ) && '' !== $rule['use'] ) {
				$use = $rule['use'];
			}

			if ( isset( $rule['options'] ) ) {
				$options = self::normalize_options( $rule['options'], $errors, 'rule-' . $index );
			}

			if ( null === $use && empty( $options ) ) {
				$errors[] = sprintf(
					/* translators: %d: rule index. */
					__( 'Rule at index %d must define "use" or "options".', 'elementor-dynamic-select' ),
					(int) $index
				);
				continue;
			}

			$normalized[] = [
				'id'      => isset( $rule['id'] ) ? (string) $rule['id'] : 'rule-' . $index,
				'when'    => $when,
				'use'     => $use,
				'options' => $options,
			];
		}

		return $normalized;
	}

	/**
	 * Normalize default fallback.
	 *
	 * @param mixed                                              $default     Raw default.
	 * @param array<string, array<int, array{value: string, label: string}>> $option_sets Option sets.
	 * @param string[]                                           $errors      Collected errors.
	 * @return array{use: string|null, options: array<int, array{value: string, label: string}>}|null
	 */
	private static function normalize_default( $default, array $option_sets, array &$errors ) {
		if ( ! is_array( $default ) ) {
			return null;
		}

		$use = null;
		if ( isset( $default['use'] ) && is_string( $default['use'] ) && '' !== $default['use'] ) {
			$use = $default['use'];
			if ( ! isset( $option_sets[ $use ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: option set key. */
					__( 'Default references unknown option set "%s".', 'elementor-dynamic-select' ),
					$use
				);
			}
		}

		$options = [];
		if ( isset( $default['options'] ) ) {
			$options = self::normalize_options( $default['options'], $errors, 'default' );
		}

		if ( null === $use && empty( $options ) ) {
			return null;
		}

		return [
			'use'     => $use,
			'options' => $options,
		];
	}

	/**
	 * Normalize a when group.
	 *
	 * @param mixed    $when   Raw when data.
	 * @param string[] $errors Collected errors.
	 * @param int      $index  Rule index for error messages.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function normalize_when( $when, array &$errors, $index ) {
		if ( ! is_array( $when ) ) {
			$errors[] = sprintf(
				/* translators: %d: rule index. */
				__( 'Rule at index %d has an invalid "when" block.', 'elementor-dynamic-select' ),
				$index
			);
			return [ 'all' => [] ];
		}

		$normalized = [];

		foreach ( [ 'all', 'any' ] as $group ) {
			if ( empty( $when[ $group ] ) || ! is_array( $when[ $group ] ) ) {
				continue;
			}

			$conditions = [];
			foreach ( $when[ $group ] as $condition_index => $condition ) {
				if ( ! is_array( $condition ) ) {
					continue;
				}

				$field = isset( $condition['field'] ) ? (string) $condition['field'] : '';
				$operator = isset( $condition['operator'] ) ? (string) $condition['operator'] : 'includes';

				if ( '' === $field ) {
					$errors[] = sprintf(
						/* translators: 1: rule index, 2: condition index. */
						__( 'Rule at index %1$d has a condition at index %2$d without a field.', 'elementor-dynamic-select' ),
						$index,
						(int) $condition_index
					);
					continue;
				}

				if ( ! in_array( $operator, self::OPERATORS, true ) ) {
					$errors[] = sprintf(
						/* translators: 1: operator, 2: rule index. */
						__( 'Unsupported operator "%1$s" in rule at index %2$d.', 'elementor-dynamic-select' ),
						$operator,
						$index
					);
					continue;
				}

				$conditions[] = [
					'field'    => $field,
					'operator' => $operator,
					'value'    => $condition['value'] ?? null,
				];
			}

			if ( ! empty( $conditions ) ) {
				$normalized[ $group ] = $conditions;
			}
		}

		if ( empty( $normalized ) ) {
			$errors[] = sprintf(
				/* translators: %d: rule index. */
				__( 'Rule at index %d must define at least one condition.', 'elementor-dynamic-select' ),
				$index
			);
			return [ 'all' => [] ];
		}

		return $normalized;
	}

	/**
	 * Normalize option list.
	 *
	 * @param mixed    $options Raw options.
	 * @param string[] $errors  Collected errors.
	 * @param string   $context Context label for errors.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function normalize_options( $options, array &$errors, $context ) {
		$normalized = [];

		if ( ! is_array( $options ) ) {
			$errors[] = sprintf(
				/* translators: %s: context label. */
				__( 'Options for "%s" must be an array.', 'elementor-dynamic-select' ),
				$context
			);
			return $normalized;
		}

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$value = isset( $option['value'] ) ? (string) $option['value'] : '';
			$label = isset( $option['label'] ) ? (string) $option['label'] : $value;

			if ( '' === $value ) {
				continue;
			}

			$normalized[] = [
				'value' => $value,
				'label' => $label,
			];
		}

		return $normalized;
	}

	/**
	 * Collect source field IDs from rule conditions.
	 *
	 * @param array<int, array<string, mixed>> $rules Normalized rules.
	 * @return string[]
	 */
	private static function collect_source_fields( array $rules ) {
		$fields = [];

		foreach ( $rules as $rule ) {
			if ( empty( $rule['when'] ) || ! is_array( $rule['when'] ) ) {
				continue;
			}

			foreach ( [ 'all', 'any' ] as $group ) {
				if ( empty( $rule['when'][ $group ] ) || ! is_array( $rule['when'][ $group ] ) ) {
					continue;
				}

				foreach ( $rule['when'][ $group ] as $condition ) {
					if ( ! empty( $condition['field'] ) ) {
						$fields[] = (string) $condition['field'];
					}
				}
			}
		}

		return array_values( array_unique( $fields ) );
	}
}
