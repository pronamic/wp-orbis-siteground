<?php
/**
 * Website importer
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Website importer class
 *
 * Imports the websites from a SiteGround `GET /v1/sites/list` response.
 *
 * @link https://uapi.siteground.com/v1/sites/list
 */
final readonly class WebsiteImporter extends Importer {
	/**
	 * Get post type.
	 *
	 * @return string
	 */
	protected function get_post_type(): string {
		return 'orbis_sg_website';
	}

	/**
	 * Check if a SiteGround website is valid.
	 *
	 * @param array<mixed> $item SiteGround website.
	 * @return bool
	 */
	protected function is_valid_item( array $item ): bool {
		return ! empty( $item['id'] ) && ! empty( $item['domain'] );
	}

	/**
	 * Get the message for an import without websites.
	 *
	 * @return string
	 */
	protected function get_empty_message(): string {
		return \__( 'The JSON does not contain any SiteGround websites.', 'orbis-siteground' );
	}

	/**
	 * Get the message for an import with invalid websites.
	 *
	 * @return string
	 */
	protected function get_invalid_message(): string {
		return \__( 'Every SiteGround website must have an `id` and a `domain`.', 'orbis-siteground' );
	}

	/**
	 * Map SiteGround website to table columns.
	 *
	 * The account dates are not mapped, they belong to the SiteGround account.
	 *
	 * @param array<string, mixed> $item SiteGround website.
	 * @return array<string, mixed>
	 */
	protected function map( array $item ): array {
		$server     = \is_array( $item['server'] ?? null ) ? $item['server'] : [];
		$datacenter = \is_array( $item['datacenter'] ?? null ) ? $item['datacenter'] : [];
		$admin_urls = \is_array( $item['cms_admin_url'] ?? null ) ? $item['cms_admin_url'] : [];

		return [
			'siteground_id'         => (string) $item['id'],
			'account_siteground_id' => $this->string_or_null( $item['account_id'] ?? null ),
			'domain'                => (string) $item['domain'],
			'account_name'          => $this->string_or_null( $item['account_name'] ?? null ),
			'status'                => (string) ( $item['status'] ?? '' ),
			'account_status'        => $this->string_or_null( $item['account_status'] ?? null ),
			'account_type'          => $this->string_or_null( $item['account_type'] ?? null ),
			'cms'                   => $this->string_or_null( $item['cms'] ?? null ),
			'admin_url'             => $this->string_or_null( \array_values( $admin_urls )[0] ?? null ),
			'server_id'             => $this->string_or_null( $server['id'] ?? null ),
			'server_ip'             => $this->string_or_null( $server['ip'] ?? null ),
			'server_location'       => $this->string_or_null( $server['location'] ?? null ),
			'datacenter_name'       => $this->string_or_null( $datacenter['name'] ?? null ),
			'siteground_created_at' => $this->date_or_null( $item['created'] ?? null ),
			'suspended'             => (int) ( $item['suspended'] ?? 0 ),
			'data'                  => (string) \wp_json_encode( $item ),
		];
	}

	/**
	 * Get post title.
	 *
	 * @param array<string, mixed> $values Mapped values.
	 * @return string
	 */
	protected function get_post_title( array $values ): string {
		return (string) $values['domain'];
	}
}
