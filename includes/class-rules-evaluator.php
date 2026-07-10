<?php
/**
 * Rules evaluation engine.
 *
 * @package ElementorDynamicSelect
 */

namespace ElementorDynamicSelect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates rulesets against source field values.
 */
class Rules_Evaluator {

	/**
	 * Evaluate a ruleset and return matching options.
	 *
	 * @param array<string, mixed>              $ruleset Normalized ruleset.
	 * @param array<string, string|string[]>    $source_values Source field values keyed by field ID.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function evaluate( array $ruleset, array $source_values ) {
		$behavior = $ruleset['behavior'] ?? [];
		$match_mode = isset( $behavior['match'] ) ? (string) $behavior['match'] : 'first';
		$option_sets = $ruleset['optionSets'] ?? [];
		$rules = $ruleset['rules'] ?? [];
		$default = $ruleset['default'] ?? null;

		$matched = [];

		foreach ( $rules as $rule ) {
			if ( ! self::rule_matches( $rule, $source_values ) ) {
				continue;
			}

			$options = self::resolve_options( $rule, $option_sets );

			if ( 'first' === $match_mode ) {
				return $options;
			}

			$matched = self::merge_options( $matched, $options );
		}

		if ( ! empty( $matched ) ) {
			return $matched;
		}

		if ( is_array( $default ) ) {
			return self::resolve_options( $default, $option_sets );
		}

		return [];
	}

	/**
	 * Check whether a submitted value is allowed.
	 *
	 * @param array<string, mixed>           $ruleset Normalized ruleset.
	 * @param array<string, string|string[]> $source_values Source field values.
	 * @param string                         $submitted_value Submitted select value.
	 * @return bool
	 */
	public static function is_value_allowed( array $ruleset, array $source_values, $submitted_value ) {
		$allowed = self::evaluate( $ruleset, $source_values );

		foreach ( $allowed as $option ) {
			if ( isset( $option['value'] ) && (string) $option['value'] === (string) $submitted_value ) {
				return true;
			}
		}

		return empty( $allowed ) && '' === (string) $submitted_value;
	}

	/**
	 * Determine whether a rule matches source values.
	 *
	 * @param array<string, mixed>           $rule Rule definition.
	 * @param array<string, string|string[]> $source_values Source field values.
	 * @return bool
	 */
	private static function rule_matches( array $rule, array $source_values ) {
		$when = $rule['when'] ?? [];

		if ( ! empty( $when['all'] ) && is_array( $when['all'] ) ) {
			foreach ( $when['all'] as $condition ) {
				if ( ! self::condition_matches( $condition, $source_values ) ) {
					return false;
				}
			}
		}

		if ( ! empty( $when['any'] ) && is_array( $when['any'] ) ) {
			$any_match = false;
			foreach ( $when['any'] as $condition ) {
				if ( self::condition_matches( $condition, $source_values ) ) {
					$any_match = true;
					break;
				}
			}

			if ( ! $any_match ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param array<string, mixed>           $condition Condition definition.
	 * @param array<string, string|string[]> $source_values Source field values.
	 * @return bool
	 */
	private static function condition_matches( array $condition, array $source_values ) {
		$field = isset( $condition['field'] ) ? (string) $condition['field'] : '';
		$operator = isset( $condition['operator'] ) ? (string) $condition['operator'] : 'includes';
		$expected = $condition['value'] ?? null;
		$actual = self::normalize_values( $source_values[ $field ] ?? [] );

		switch ( $operator ) {
			case 'includes':
				return self::includes_value( $actual, $expected );

			case 'includesAll':
				return self::includes_all( $actual, $expected );

			case 'includesAny':
			case 'intersects':
				return self::includes_any( $actual, $expected );

			case 'equals':
				return self::equals_set( $actual, $expected );

			case 'in':
				return self::in_value( $actual, $expected );

			case 'any':
				return ! empty( $actual );

			default:
				return false;
		}
	}

	/**
	 * Normalize a value into a string array.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	private static function normalize_values( $value ) {
		if ( is_array( $value ) ) {
			return array_values(
				array_filter(
					array_map(
						static function ( $item ) {
							return (string) $item;
						},
						$value
					),
					static function ( $item ) {
						return '' !== $item;
					}
				)
			);
		}

		if ( null === $value || '' === $value ) {
			return [];
		}

		return [ (string) $value ];
	}

	/**
	 * Normalize expected values.
	 *
	 * @param mixed $expected Expected value(s).
	 * @return string[]
	 */
	private static function normalize_expected( $expected ) {
		return self::normalize_values( $expected );
	}

	/**
	 * Check if actual values include a single expected value.
	 *
	 * @param string[] $actual Actual values.
	 * @param mixed    $expected Expected value.
	 * @return bool
	 */
	private static function includes_value( array $actual, $expected ) {
		$expected_values = self::normalize_expected( $expected );

		if ( empty( $expected_values ) ) {
			return false;
		}

		foreach ( $expected_values as $value ) {
			if ( in_array( $value, $actual, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if actual values include all expected values.
	 *
	 * @param string[] $actual Actual values.
	 * @param mixed    $expected Expected values.
	 * @return bool
	 */
	private static function includes_all( array $actual, $expected ) {
		$expected_values = self::normalize_expected( $expected );

		if ( empty( $expected_values ) ) {
			return false;
		}

		foreach ( $expected_values as $value ) {
			if ( ! in_array( $value, $actual, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if actual values intersect expected values.
	 *
	 * @param string[] $actual Actual values.
	 * @param mixed    $expected Expected values.
	 * @return bool
	 */
	private static function includes_any( array $actual, $expected ) {
		$expected_values = self::normalize_expected( $expected );

		if ( empty( $expected_values ) || empty( $actual ) ) {
			return false;
		}

		foreach ( $expected_values as $value ) {
			if ( in_array( $value, $actual, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check set equality.
	 *
	 * @param string[] $actual Actual values.
	 * @param mixed    $expected Expected values.
	 * @return bool
	 */
	private static function equals_set( array $actual, $expected ) {
		$expected_values = self::normalize_expected( $expected );
		sort( $actual );
		sort( $expected_values );

		return $actual === $expected_values;
	}

	/**
	 * Check if any actual value is in expected list.
	 *
	 * @param string[] $actual Actual values.
	 * @param mixed    $expected Expected values.
	 * @return bool
	 */
	private static function in_value( array $actual, $expected ) {
		$expected_values = self::normalize_expected( $expected );

		if ( empty( $expected_values ) || empty( $actual ) ) {
			return false;
		}

		foreach ( $actual as $value ) {
			if ( in_array( $value, $expected_values, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve options from a rule or default block.
	 *
	 * @param array<string, mixed>                                              $block Rule/default block.
	 * @param array<string, array<int, array{value: string, label: string}>> $option_sets Option sets.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function resolve_options( array $block, array $option_sets ) {
		if ( ! empty( $block['use'] ) && isset( $option_sets[ $block['use'] ] ) ) {
			return $option_sets[ $block['use'] ];
		}

		if ( ! empty( $block['options'] ) && is_array( $block['options'] ) ) {
			return $block['options'];
		}

		return [];
	}

	/**
	 * Merge option arrays uniquely by value.
	 *
	 * @param array<int, array{value: string, label: string}> $existing Existing options.
	 * @param array<int, array{value: string, label: string}> $incoming Incoming options.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function merge_options( array $existing, array $incoming ) {
		$merged = [];

		foreach ( array_merge( $existing, $incoming ) as $option ) {
			if ( empty( $option['value'] ) ) {
				continue;
			}

			$merged[ $option['value'] ] = $option;
		}

		return array_values( $merged );
	}
}
