<?php
/**
 * Query helper utilities.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Data;

/**
 * Shared query helpers for dynamic clauses and pagination.
 */
final class Query_Helpers {
	/**
	 * Build an IN clause placeholder list with sanitized values.
	 *
	 * @param array    $values      Raw values for the IN clause.
	 * @param callable $sanitize    Callback to sanitize each value.
	 * @param string   $placeholder Placeholder for $wpdb->prepare (default: %d).
	 * @return array{placeholders:string,values:array}
	 */
	public static function prepare_in_clause( array $values, callable $sanitize, string $placeholder = '%d' ): array {
		$sanitized = array();

		foreach ( $values as $value ) {
			$clean = $sanitize( $value );

			if ( '%d' === $placeholder ) {
				$clean = (int) $clean;
				if ( $clean > 0 ) {
					$sanitized[] = $clean;
				}
			} else {
				$clean = (string) $clean;
				if ( '' !== $clean ) {
					$sanitized[] = $clean;
				}
			}
		}

		$sanitized = array_values( array_unique( $sanitized ) );
		if ( empty( $sanitized ) ) {
			return array(
				'placeholders' => '',
				'values'       => array(),
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $sanitized ), $placeholder ) );

		return array(
			'placeholders' => $placeholders,
			'values'       => $sanitized,
		);
	}

	/**
	 * Ensure an identifier is safe for use with %i placeholders.
	 *
	 * @param string        $identifier Raw identifier.
	 * @param array<string> $allowlist  Optional allowlist of identifiers.
	 * @return string
	 */
	public static function safe_identifier( string $identifier, array $allowlist = array() ): string {
		$identifier = preg_replace( '/[^A-Za-z0-9_]/', '', $identifier );
		if ( '' === $identifier ) {
			return '';
		}

		if ( ! empty( $allowlist ) && ! in_array( $identifier, $allowlist, true ) ) {
			return '';
		}

		return $identifier;
	}

	/**
	 * Normalize pagination values and compute offset.
	 *
	 * @param int $page         Page number.
	 * @param int $per_page     Items per page.
	 * @param int $max_per_page Max items per page.
	 * @return array{page:int,per_page:int,offset:int}
	 */
	public static function normalize_pagination( int $page, int $per_page, int $max_per_page = 100 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( $max_per_page, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}
}
