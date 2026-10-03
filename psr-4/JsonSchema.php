<?php
/**
 * JSON schema
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * JSON schema class
 *
 * Loads a JSON schema from the `json-schemas` directory in a form that the
 * WordPress REST API validator understands: local `$ref`s to `$defs` are
 * inlined and the `$schema`, `$id` and `$defs` keywords are removed.
 *
 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
 */
final class JsonSchema {
	/**
	 * Load a JSON schema.
	 *
	 * @param string $name Schema name, for example `siteground-invoice`.
	 * @return array<string, mixed>
	 */
	public static function load( string $name ): array {
		$schema = \wp_json_file_decode( __DIR__ . '/../json-schemas/' . $name . '.json', [ 'associative' => true ] );

		if ( ! \is_array( $schema ) ) {
			return [];
		}

		$defs = $schema['$defs'] ?? [];

		unset( $schema['$schema'], $schema['$id'], $schema['$defs'] );

		return self::resolve( $schema, $defs );
	}

	/**
	 * Resolve local references.
	 *
	 * Keywords next to a `$ref`, such as `description`, override the keywords
	 * of the referenced definition.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $defs   Definitions.
	 * @return array<string, mixed>
	 */
	private static function resolve( array $schema, array $defs ): array {
		if ( isset( $schema['$ref'] ) && \is_string( $schema['$ref'] ) && \str_starts_with( $schema['$ref'], '#/$defs/' ) ) {
			$name = \substr( $schema['$ref'], \strlen( '#/$defs/' ) );

			unset( $schema['$ref'] );

			$schema = \array_merge( self::resolve( $defs[ $name ] ?? [], $defs ), $schema );
		}

		foreach ( $schema as $key => $value ) {
			if ( \is_array( $value ) ) {
				$schema[ $key ] = self::resolve( $value, $defs );
			}
		}

		return $schema;
	}
}
