<?php
/**
 * Repository
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Repository class
 *
 * Reads and writes a shadow table with SiteGround items, each row is linked
 * to a post and keyed by the SiteGround ID.
 */
abstract class Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	abstract protected function get_table(): string;

	/**
	 * Get item by SiteGround ID.
	 *
	 * @param string $siteground_id SiteGround ID.
	 * @return object|null
	 */
	public function get_by_siteground_id( string $siteground_id ): ?object {
		global $wpdb;

		$table = $this->get_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				"SELECT * FROM $table WHERE siteground_id = %s;",
				$siteground_id
			)
		);
	}

	/**
	 * Get item by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public function get_by_post_id( int $post_id ): ?object {
		global $wpdb;

		$table = $this->get_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				"SELECT * FROM $table WHERE post_id = %d;",
				$post_id
			)
		);
	}

	/**
	 * Get items by post IDs.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<int, object> Items keyed by post ID.
	 */
	public function get_by_post_ids( array $post_ids ): array {
		global $wpdb;

		$post_ids = \array_filter( \array_map( intval( ... ), $post_ids ) );

		if ( [] === $post_ids ) {
			return [];
		}

		$table = $this->get_table();

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are generated above.
				"SELECT * FROM $table WHERE post_id IN ( $placeholders );",
				...$post_ids
			)
		);

		$items = [];

		foreach ( $results as $result ) {
			$items[ (int) $result->post_id ] = $result;
		}

		return $items;
	}

	/**
	 * Insert item.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int Item ID.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$wpdb->insert( $this->get_table(), $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update item.
	 *
	 * @param int                  $id   Item ID.
	 * @param array<string, mixed> $data Column values.
	 * @return void
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;

		$wpdb->update( $this->get_table(), $data, [ 'id' => $id ] );
	}

	/**
	 * Mark items that were not seen in the import as removed.
	 *
	 * @param string $import_time Import time (UTC, MySQL format).
	 * @return int Number of items marked as removed.
	 */
	public function mark_removed( string $import_time ): int {
		global $wpdb;

		$table = $this->get_table();

		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				"UPDATE $table SET removed_at = %s, updated_at = %s WHERE removed_at IS NULL AND last_seen_at < %s;",
				$import_time,
				$import_time,
				$import_time
			)
		);
	}
}
