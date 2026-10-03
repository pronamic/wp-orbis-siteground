<?php
/**
 * Account repository
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Account repository class
 *
 * Reads and writes the `orbis_siteground_accounts` shadow table.
 */
final class AccountRepository extends Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table(): string {
		global $wpdb;

		return $wpdb->orbis_siteground_accounts;
	}

	/**
	 * Get present accounts, accounts that were in the latest import.
	 *
	 * @return object[]
	 */
	public function get_present(): array {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM $wpdb->orbis_siteground_accounts WHERE removed_at IS NULL ORDER BY name ASC;" );
	}

	/**
	 * Get accounts by names.
	 *
	 * @param string[] $names Account names (domain names).
	 * @return array<string, object> Accounts keyed by name.
	 */
	public function get_by_names( array $names ): array {
		global $wpdb;

		$names = \array_values( \array_unique( \array_filter( \array_map( strval( ... ), $names ) ) ) );

		if ( [] === $names ) {
			return [];
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $names ), '%s' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated above.
				"SELECT * FROM $wpdb->orbis_siteground_accounts WHERE name IN ( $placeholders ) ORDER BY removed_at IS NOT NULL, last_seen_at DESC;",
				...$names
			)
		);

		$accounts = [];

		foreach ( $results as $result ) {
			$accounts[ (string) $result->name ] ??= $result;
		}

		return $accounts;
	}

	/**
	 * Search accounts.
	 *
	 * @param array{search?: string, status?: string, plan_type?: string, include_removed?: bool, expires_before?: string, expires_after?: string, per_page?: int, page?: int} $args Arguments.
	 * @return array{total: int, page: int, per_page: int, accounts: object[]}
	 */
	public function search( array $args = [] ): array {
		global $wpdb;

		$args = \wp_parse_args(
			$args,
			[
				'search'          => '',
				'status'          => 'any',
				'plan_type'       => '',
				'include_removed' => false,
				'expires_before'  => '',
				'expires_after'   => '',
				'per_page'        => 20,
				'page'            => 1,
			]
		);

		$conditions = [ '1 = 1' ];

		$search = \trim( (string) $args['search'] );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$conditions[] = $wpdb->prepare(
				'( account.name LIKE %s OR account.plan_description LIKE %s OR account.server_location LIKE %s OR account.server_ip LIKE %s OR account.datacenter_name LIKE %s )',
				$like,
				$like,
				$like,
				$like,
				$like
			);
		}

		if ( '' !== $args['status'] && 'any' !== $args['status'] ) {
			$conditions[] = $wpdb->prepare( 'account.status = %s', $args['status'] );
		}

		if ( '' !== $args['plan_type'] ) {
			$conditions[] = $wpdb->prepare( 'account.plan_type = %s', $args['plan_type'] );
		}

		if ( ! $args['include_removed'] ) {
			$conditions[] = 'account.removed_at IS NULL';
		}

		if ( '' !== $args['expires_before'] ) {
			$conditions[] = $wpdb->prepare( 'account.expires_at < %s', \gmdate( 'Y-m-d H:i:s', (int) \strtotime( $args['expires_before'] ) ) );
		}

		if ( '' !== $args['expires_after'] ) {
			$conditions[] = $wpdb->prepare( 'account.expires_at >= %s', \gmdate( 'Y-m-d H:i:s', (int) \strtotime( $args['expires_after'] ) ) );
		}

		$where = \implode( ' AND ', $conditions );

		$per_page = \max( 1, \min( 100, (int) $args['per_page'] ) );
		$page     = \max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The where clause is prepared above.
		$total = (int) $wpdb->get_var( "SELECT COUNT( account.id ) FROM $wpdb->orbis_siteground_accounts AS account WHERE $where;" );

		$accounts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT account.* FROM $wpdb->orbis_siteground_accounts AS account WHERE $where ORDER BY account.expires_at ASC, account.name ASC LIMIT %d OFFSET %d;",
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'accounts' => $accounts,
		];
	}
}
