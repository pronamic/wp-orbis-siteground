<?php
/**
 * Account importer
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Account importer class
 *
 * Imports the accounts from a SiteGround `GET /v1/accounts` response.
 *
 * @link https://uapi.siteground.com/v1/accounts?sort_field=expires&sort_order=ASC
 */
final readonly class AccountImporter extends Importer {
	/**
	 * Get post type.
	 *
	 * @return string
	 */
	protected function get_post_type(): string {
		return 'orbis_sg_account';
	}

	/**
	 * Check if a SiteGround account is valid.
	 *
	 * @param array<mixed> $item SiteGround account.
	 * @return bool
	 */
	protected function is_valid_item( array $item ): bool {
		return ! empty( $item['id'] ) && ! empty( $item['name'] );
	}

	/**
	 * Get the message for an import without accounts.
	 *
	 * @return string
	 */
	protected function get_empty_message(): string {
		return \__( 'The JSON does not contain any SiteGround accounts.', 'orbis-siteground' );
	}

	/**
	 * Get the message for an import with invalid accounts.
	 *
	 * @return string
	 */
	protected function get_invalid_message(): string {
		return \__( 'Every SiteGround account must have an `id` and a `name`.', 'orbis-siteground' );
	}

	/**
	 * Map SiteGround account to table columns.
	 *
	 * @param array<string, mixed> $item SiteGround account.
	 * @return array<string, mixed>
	 */
	protected function map( array $item ): array {
		$server     = \is_array( $item['server'] ?? null ) ? $item['server'] : [];
		$datacenter = \is_array( $item['datacenter'] ?? null ) ? $item['datacenter'] : [];
		$billing    = \is_array( $item['billing_settings'] ?? null ) ? $item['billing_settings'] : [];

		return [
			'siteground_id'         => (string) $item['id'],
			'name'                  => (string) $item['name'],
			'status'                => (string) ( $item['status'] ?? '' ),
			'plan_type'             => $this->string_or_null( $item['plan_type'] ?? null ),
			'plan_description'      => $this->string_or_null( $item['plan_description'] ?? null ),
			'server_id'             => $this->string_or_null( $server['id'] ?? null ),
			'server_ip'             => $this->string_or_null( $server['ip'] ?? null ),
			'server_location'       => $this->string_or_null( $server['location'] ?? null ),
			'datacenter_name'       => $this->string_or_null( $datacenter['name'] ?? null ),
			'siteground_created_at' => $this->date_or_null( $item['created'] ?? null ),
			'expires_at'            => $this->date_or_null( $item['expires'] ?? null ),
			'suspended_at'          => $this->date_or_null( $item['suspended_when'] ?? null ),
			'next_billing_date'     => $this->date_or_null( $billing['next_billing_date'] ?? null ),
			'billing_cycle'         => isset( $billing['current_billing_cycle'] ) ? (int) $billing['current_billing_cycle'] : null,
			'auto_renew'            => isset( $billing['active'] ) ? (int) (bool) $billing['active'] : null,
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
		return (string) $values['name'];
	}
}
