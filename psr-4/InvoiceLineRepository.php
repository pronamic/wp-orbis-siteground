<?php
/**
 * Invoice line repository
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Invoice line repository class
 *
 * Reads and writes the `orbis_siteground_invoice_lines` table.
 */
final class InvoiceLineRepository {
	/**
	 * Get the lines of an invoice.
	 *
	 * The lines include the post ID of the account as `account_post_id`.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return object[]
	 */
	public function get_by_invoice_id( int $invoice_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					line.*,
					account.post_id AS account_post_id
				FROM
					$wpdb->orbis_siteground_invoice_lines AS line
						LEFT JOIN
					$wpdb->orbis_siteground_accounts AS account
							ON account.id = line.account_id
				WHERE
					line.invoice_id = %d
				ORDER BY
					line.line_number ASC
				;
				",
				$invoice_id
			)
		);
	}

	/**
	 * Get the invoice lines of an account.
	 *
	 * The lines include the invoice columns `invoice_number`, `invoice_date`,
	 * `currency` and `invoice_post_id`, newest invoice first.
	 *
	 * @param int $account_id Account ID.
	 * @return object[]
	 */
	public function get_by_account_id( int $account_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					line.*,
					invoice.invoice_number,
					invoice.invoice_date,
					invoice.currency,
					invoice.post_id AS invoice_post_id
				FROM
					$wpdb->orbis_siteground_invoice_lines AS line
						INNER JOIN
					$wpdb->orbis_siteground_invoices AS invoice
							ON invoice.id = line.invoice_id
				WHERE
					line.account_id = %d
				ORDER BY
					invoice.invoice_date DESC,
					invoice.id DESC,
					line.line_number ASC
				;
				",
				$account_id
			)
		);
	}

	/**
	 * Insert or update an invoice line.
	 *
	 * @param int                  $invoice_id  Invoice ID.
	 * @param int                  $line_number Line number, 1-based position of the line on the invoice.
	 * @param array<string, mixed> $data        Column values.
	 * @return void
	 */
	public function upsert( int $invoice_id, int $line_number, array $data ): void {
		global $wpdb;

		$now = \current_time( 'mysql', true );

		$data = [
			'created_at'  => $now,
			'updated_at'  => $now,
			'invoice_id'  => $invoice_id,
			'line_number' => $line_number,
		] + $data;

		$columns      = [];
		$placeholders = [];
		$updates      = [];
		$values       = [];

		foreach ( $data as $column => $value ) {
			$columns[] = $column;

			if ( null === $value ) {
				$placeholders[] = 'NULL';
			} else {
				$placeholders[] = '%s';
				$values[]       = (string) $value;
			}

			if ( ! \in_array( $column, [ 'created_at', 'invoice_id', 'line_number' ], true ) ) {
				$updates[] = "$column = VALUES( $column )";
			}
		}

		$sql = \sprintf(
			'INSERT INTO %s ( %s ) VALUES ( %s ) ON DUPLICATE KEY UPDATE %s;',
			$wpdb->orbis_siteground_invoice_lines,
			\implode( ', ', $columns ),
			\implode( ', ', $placeholders ),
			\implode( ', ', $updates )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The query is built from column names and placeholders only.
		$wpdb->query( $wpdb->prepare( $sql, ...$values ) );
	}

	/**
	 * Delete the lines of an invoice after a line number.
	 *
	 * Removes the lines that are no longer on the invoice after an update
	 * with fewer lines.
	 *
	 * @param int $invoice_id  Invoice ID.
	 * @param int $line_number Line number of the last line on the invoice.
	 * @return void
	 */
	public function delete_after( int $invoice_id, int $line_number ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->orbis_siteground_invoice_lines WHERE invoice_id = %d AND line_number > %d;",
				$invoice_id,
				$line_number
			)
		);
	}
}
