<?php
/**
 * Invoice repository
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Invoice repository class
 *
 * Reads and writes the `orbis_siteground_invoices` table.
 */
final class InvoiceRepository {
	/**
	 * Get invoice by ID.
	 *
	 * @param int $id Invoice ID.
	 * @return object|null
	 */
	public function get_by_id( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->orbis_siteground_invoices WHERE id = %d;",
				$id
			)
		);
	}

	/**
	 * Get invoice by invoice number.
	 *
	 * @param string $invoice_number Invoice number.
	 * @return object|null
	 */
	public function get_by_invoice_number( string $invoice_number ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->orbis_siteground_invoices WHERE invoice_number = %s;",
				$invoice_number
			)
		);
	}

	/**
	 * Get invoice by the SHA-256 hash of the PDF.
	 *
	 * @param string $file_sha256 SHA-256 hash of the PDF.
	 * @return object|null
	 */
	public function get_by_file_sha256( string $file_sha256 ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->orbis_siteground_invoices WHERE file_sha256 = %s;",
				$file_sha256
			)
		);
	}

	/**
	 * Get invoice by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public function get_by_post_id( int $post_id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->orbis_siteground_invoices WHERE post_id = %d;",
				$post_id
			)
		);
	}

	/**
	 * Get invoices by post IDs.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<int, object> Invoices keyed by post ID.
	 */
	public function get_by_post_ids( array $post_ids ): array {
		global $wpdb;

		$post_ids = \array_filter( \array_map( intval( ... ), $post_ids ) );

		if ( [] === $post_ids ) {
			return [];
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated above.
				"SELECT * FROM $wpdb->orbis_siteground_invoices WHERE post_id IN ( $placeholders );",
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
	 * Insert invoice.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int Invoice ID.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$wpdb->insert( $wpdb->orbis_siteground_invoices, $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update invoice.
	 *
	 * @param int                  $id   Invoice ID.
	 * @param array<string, mixed> $data Column values.
	 * @return void
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;

		$wpdb->update( $wpdb->orbis_siteground_invoices, $data, [ 'id' => $id ] );
	}

	/**
	 * Search invoices.
	 *
	 * The search term is also matched against the file name and the JSON data,
	 * so invoices can be found by the domain or product of an invoice line.
	 *
	 * @param array{search?: string, status?: string, date_after?: string, date_before?: string, per_page?: int, page?: int} $args Arguments.
	 * @return array{total: int, page: int, per_page: int, invoices: object[]}
	 */
	public function search( array $args = [] ): array {
		global $wpdb;

		$args = \wp_parse_args(
			$args,
			[
				'search'      => '',
				'status'      => 'any',
				'date_after'  => '',
				'date_before' => '',
				'per_page'    => 20,
				'page'        => 1,
			]
		);

		$conditions = [ '1 = 1' ];

		$search = \trim( (string) $args['search'] );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$conditions[] = $wpdb->prepare(
				'( invoice.invoice_number LIKE %s OR invoice.file_name LIKE %s OR invoice.data LIKE %s )',
				$like,
				$like,
				$like
			);
		}

		if ( 'processed' === $args['status'] ) {
			$conditions[] = 'invoice.processed_at IS NOT NULL';
		}

		if ( 'unprocessed' === $args['status'] ) {
			$conditions[] = 'invoice.processed_at IS NULL';
		}

		if ( '' !== $args['date_after'] ) {
			$conditions[] = $wpdb->prepare( 'invoice.invoice_date >= %s', $args['date_after'] );
		}

		if ( '' !== $args['date_before'] ) {
			$conditions[] = $wpdb->prepare( 'invoice.invoice_date < %s', $args['date_before'] );
		}

		$where = \implode( ' AND ', $conditions );

		$per_page = \max( 1, \min( 100, (int) $args['per_page'] ) );
		$page     = \max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The where clause is prepared above.
		$total = (int) $wpdb->get_var( "SELECT COUNT( invoice.id ) FROM $wpdb->orbis_siteground_invoices AS invoice WHERE $where;" );

		$invoices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT invoice.* FROM $wpdb->orbis_siteground_invoices AS invoice WHERE $where ORDER BY invoice.processed_at IS NOT NULL, invoice.invoice_date DESC, invoice.id DESC LIMIT %d OFFSET %d;",
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'invoices' => $invoices,
		];
	}
}
