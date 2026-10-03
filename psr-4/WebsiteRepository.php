<?php
/**
 * Website repository
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Website repository class
 *
 * Reads and writes the `orbis_siteground_websites` shadow table.
 */
final class WebsiteRepository extends Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table(): string {
		global $wpdb;

		return $wpdb->orbis_siteground_websites;
	}

	/**
	 * Get websites of a SiteGround account.
	 *
	 * @param string $account_siteground_id SiteGround account ID.
	 * @return object[]
	 */
	public function get_by_account_siteground_id( string $account_siteground_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->orbis_siteground_websites WHERE account_siteground_id = %s ORDER BY removed_at IS NOT NULL, domain ASC;",
				$account_siteground_id
			)
		);
	}

	/**
	 * Search websites.
	 *
	 * The results include the `account_post_id` of the linked SiteGround account.
	 *
	 * @param array{search?: string, status?: string, cms?: string, account_id?: string, include_removed?: bool, per_page?: int, page?: int} $args Arguments.
	 * @return array{total: int, page: int, per_page: int, websites: object[]}
	 */
	public function search( array $args = [] ): array {
		global $wpdb;

		$args = \wp_parse_args(
			$args,
			[
				'search'          => '',
				'status'          => 'any',
				'cms'             => '',
				'account_id'      => '',
				'include_removed' => false,
				'per_page'        => 20,
				'page'            => 1,
			]
		);

		$conditions = [ '1 = 1' ];

		$search = \trim( (string) $args['search'] );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$conditions[] = $wpdb->prepare(
				'( website.domain LIKE %s OR website.account_name LIKE %s OR website.server_location LIKE %s OR website.server_ip LIKE %s OR website.datacenter_name LIKE %s )',
				$like,
				$like,
				$like,
				$like,
				$like
			);
		}

		if ( '' !== $args['status'] && 'any' !== $args['status'] ) {
			$conditions[] = $wpdb->prepare( 'website.status = %s', $args['status'] );
		}

		if ( '' !== $args['cms'] ) {
			$conditions[] = $wpdb->prepare( 'website.cms = %s', $args['cms'] );
		}

		if ( '' !== $args['account_id'] ) {
			$conditions[] = $wpdb->prepare( 'website.account_siteground_id = %s', $args['account_id'] );
		}

		if ( ! $args['include_removed'] ) {
			$conditions[] = 'website.removed_at IS NULL';
		}

		$where = \implode( ' AND ', $conditions );

		$per_page = \max( 1, \min( 100, (int) $args['per_page'] ) );
		$page     = \max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The where clause is prepared above.
		$total = (int) $wpdb->get_var( "SELECT COUNT( website.id ) FROM $wpdb->orbis_siteground_websites AS website WHERE $where;" );

		$websites = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					website.*,
					account.post_id AS account_post_id
				FROM
					$wpdb->orbis_siteground_websites AS website
						LEFT JOIN
					$wpdb->orbis_siteground_accounts AS account
							ON website.account_siteground_id = account.siteground_id
				WHERE
					$where
				ORDER BY
					website.domain ASC
				LIMIT
					%d
				OFFSET
					%d
				;
				",
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'websites' => $websites,
		];
	}
}
