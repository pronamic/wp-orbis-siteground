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

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Account importer class
 *
 * Upserts the accounts from a SiteGround `GET /v1/accounts` response into the
 * shadow table and marks accounts that are no longer in the response as removed.
 *
 * @link https://uapi.siteground.com/v1/accounts?sort_field=expires&sort_order=ASC
 */
final readonly class AccountImporter {
	/**
	 * Construct.
	 *
	 * @param AccountRepository $accounts Account repository.
	 */
	public function __construct(
		/**
		 * Account repository.
		 */
		private AccountRepository $accounts
	) {
	}

	/**
	 * Import a SiteGround accounts JSON string.
	 *
	 * @param string $json JSON.
	 * @return ImportResult
	 * @throws InvalidArgumentException When the JSON does not contain SiteGround accounts.
	 */
	public function import_json( string $json ): ImportResult {
		$data = \json_decode( $json, true );

		if ( ! \is_array( $data ) ) {
			throw new InvalidArgumentException( \__( 'The file does not contain valid JSON.', 'orbis-siteground' ) );
		}

		if ( ! isset( $data['data']['accounts'] ) || ! \is_array( $data['data']['accounts'] ) ) {
			throw new InvalidArgumentException( \__( 'The JSON does not contain SiteGround accounts (`data.accounts`).', 'orbis-siteground' ) );
		}

		return $this->import( $data['data']['accounts'] );
	}

	/**
	 * Import accounts.
	 *
	 * @param array<int, mixed> $items SiteGround accounts.
	 * @return ImportResult
	 * @throws InvalidArgumentException When the accounts are empty or invalid.
	 */
	public function import( array $items ): ImportResult {
		// An empty or invalid list would mark every account as removed, so refuse it.
		if ( [] === $items ) {
			throw new InvalidArgumentException( \__( 'The JSON does not contain any SiteGround accounts.', 'orbis-siteground' ) );
		}

		foreach ( $items as $item ) {
			if ( ! \is_array( $item ) || empty( $item['id'] ) || empty( $item['name'] ) ) {
				throw new InvalidArgumentException( \__( 'Every SiteGround account must have an `id` and a `name`.', 'orbis-siteground' ) );
			}
		}

		$now = \current_time( 'mysql', true );

		$result = new ImportResult();

		foreach ( $items as $item ) {
			$this->upsert( $item, $now, $result );
		}

		$result->removed = $this->accounts->mark_removed( $now );

		return $result;
	}

	/**
	 * Upsert account.
	 *
	 * @param array<string, mixed> $item   SiteGround account.
	 * @param string               $now    Import time (UTC, MySQL format).
	 * @param ImportResult         $result Import result.
	 * @return void
	 */
	private function upsert( array $item, string $now, ImportResult $result ): void {
		$values = $this->map( $item );

		$account = $this->accounts->get_by_siteground_id( $values['siteground_id'] );

		if ( null === $account ) {
			$values['post_id']       = $this->insert_post( $values );
			$values['created_at']    = $now;
			$values['updated_at']    = $now;
			$values['first_seen_at'] = $now;
			$values['last_seen_at']  = $now;

			$this->accounts->insert( $values );

			++$result->created;

			return;
		}

		$values['post_id'] = $this->sync_post( $account, $values );

		$changed = $this->has_changes( $account, $values );

		$values['last_seen_at'] = $now;
		$values['removed_at']   = null;

		if ( $changed ) {
			$values['updated_at'] = $now;
		}

		$this->accounts->update( (int) $account->id, $values );

		if ( null !== $account->removed_at ) {
			++$result->restored;
		} elseif ( $changed ) {
			++$result->updated;
		} else {
			++$result->unchanged;
		}
	}

	/**
	 * Map SiteGround account to table columns.
	 *
	 * @param array<string, mixed> $item SiteGround account.
	 * @return array<string, mixed>
	 */
	private function map( array $item ): array {
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
	 * Check if the mapped values differ from the stored account.
	 *
	 * @param object               $account Stored account.
	 * @param array<string, mixed> $values  Mapped values.
	 * @return bool
	 */
	private function has_changes( object $account, array $values ): bool {
		foreach ( $values as $key => $value ) {
			$stored = $account->$key ?? null;

			if ( ( null === $value ) !== ( null === $stored ) ) {
				return true;
			}

			if ( null !== $value && (string) $value !== (string) $stored ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Insert post for account.
	 *
	 * @param array<string, mixed> $values Mapped values.
	 * @return int|null Post ID.
	 */
	private function insert_post( array $values ): ?int {
		$postarr = [
			'post_type'   => 'orbis_sg_account',
			'post_status' => 'publish',
			'post_title'  => $values['name'],
		];

		if ( null !== $values['siteground_created_at'] ) {
			$postarr['post_date_gmt'] = $values['siteground_created_at'];
			$postarr['post_date']     = \get_date_from_gmt( $values['siteground_created_at'] );
		}

		$post_id = \wp_insert_post( $postarr, true );

		return \is_wp_error( $post_id ) ? null : $post_id;
	}

	/**
	 * Sync the post of an existing account.
	 *
	 * Recreates a deleted post, restores a trashed post and updates the title
	 * when the account name changed.
	 *
	 * @param object               $account Stored account.
	 * @param array<string, mixed> $values  Mapped values.
	 * @return int|null Post ID.
	 */
	private function sync_post( object $account, array $values ): ?int {
		$post = null === $account->post_id ? null : \get_post( (int) $account->post_id );

		if ( null === $post ) {
			return $this->insert_post( $values );
		}

		if ( 'trash' === $post->post_status ) {
			\wp_untrash_post( $post->ID );
		}

		if ( 'publish' !== $post->post_status || $values['name'] !== $post->post_title ) {
			\wp_update_post(
				[
					'ID'          => $post->ID,
					'post_status' => 'publish',
					'post_title'  => $values['name'],
				]
			);
		}

		return $post->ID;
	}

	/**
	 * String or null.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private function string_or_null( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return \is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Convert an ISO 8601 date to a UTC MySQL datetime.
	 *
	 * @param mixed $value Value, for example `2026-09-05T08:20:25-05:00`.
	 * @return string|null
	 */
	private function date_or_null( $value ): ?string {
		if ( ! \is_string( $value ) || '' === $value ) {
			return null;
		}

		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception ) {
			return null;
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
