<?php
/**
 * CLI controller.
 *
 * @package Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use InvalidArgumentException;
use RuntimeException;
use WP_CLI;

/**
 * CLI controller class.
 */
final readonly class CliController {
	/**
	 * Construct.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		if ( ! \defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'orbis siteground process-invoice', $this->process_invoice( ... ) );
		WP_CLI::add_command( 'orbis siteground process-invoices', $this->process_invoices( ... ) );
	}

	/**
	 * Process one invoice: extract the invoice data from the PDF text with the WordPress AI client and update the invoice.
	 *
	 * Processed invoices are processed again, the extracted data replaces the registered data.
	 *
	 * ## OPTIONS
	 *
	 * <invoice-id>
	 * : Invoice ID from the SiteGround invoices table, not the WordPress post ID.
	 *
	 * [--dry-run]
	 * : Print the extracted JSON without updating the invoice.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground process-invoice 12
	 *     wp orbis siteground process-invoice 12 --dry-run
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function process_invoice( array $args, array $assoc_args ): void {
		$invoice_id = \filter_var( $args[0] ?? '', \FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

		if ( false === $invoice_id ) {
			WP_CLI::error( 'Provide a positive SiteGround invoice ID.' );
		}

		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		try {
			$invoice = $this->process( $invoice_id, $dry_run );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			WP_CLI::error( \sprintf( 'Invoice %d: %s', $invoice_id, $e->getMessage() ) );
		}

		if ( $dry_run ) {
			return;
		}

		WP_CLI::success( \sprintf( 'Invoice %d processed: %s.', $invoice_id, InvoiceService::get_title( $invoice ) ) );
	}

	/**
	 * Process the queue of unprocessed invoices with the WordPress AI client.
	 *
	 * Invoices that fail remain unprocessed and are reported, the queue continues with the next invoice.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of invoices to process.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--dry-run]
	 * : Print the extracted JSON without updating the invoices.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground process-invoices
	 *     wp orbis siteground process-invoices --limit=5
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function process_invoices( array $args, array $assoc_args ): void {
		$limit   = \max( 0, (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 ) );
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$invoice_ids = $this->plugin->invoices->get_unprocessed_ids( $limit );

		if ( [] === $invoice_ids ) {
			WP_CLI::success( 'No unprocessed invoices.' );

			return;
		}

		WP_CLI::log( \sprintf( 'Processing %d unprocessed invoice(s).', \count( $invoice_ids ) ) );

		$failed = 0;

		foreach ( $invoice_ids as $invoice_id ) {
			try {
				$invoice = $this->process( $invoice_id, $dry_run );
			} catch ( InvalidArgumentException | RuntimeException $e ) {
				++$failed;

				WP_CLI::warning( \sprintf( 'Invoice %d: %s', $invoice_id, $e->getMessage() ) );

				continue;
			}

			if ( ! $dry_run ) {
				WP_CLI::log( \sprintf( 'Invoice %d processed: %s.', $invoice_id, InvoiceService::get_title( $invoice ) ) );
			}
		}

		$processed = \count( $invoice_ids ) - $failed;

		if ( $failed > 0 ) {
			WP_CLI::error( \sprintf( '%d invoice(s) processed, %d failed.', $processed, $failed ) );
		}

		WP_CLI::success( \sprintf( '%d invoice(s) processed.', $processed ) );
	}

	/**
	 * Process invoice.
	 *
	 * @param int  $invoice_id Invoice ID.
	 * @param bool $dry_run    Print the extracted JSON instead of updating the invoice.
	 * @return object
	 * @throws InvalidArgumentException When the invoice does not exist or the data is invalid.
	 */
	private function process( int $invoice_id, bool $dry_run ): object {
		$service = new InvoiceService( $this->plugin->invoices, $this->plugin->accounts, $this->plugin->invoice_lines );

		$invoice = $this->plugin->invoices->get_by_id( $invoice_id );

		if ( null === $invoice ) {
			throw new InvalidArgumentException( 'SiteGround invoice not found.' );
		}

		$data = $service->extract_data( $invoice );

		if ( $dry_run ) {
			WP_CLI::log( (string) \wp_json_encode( $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) );

			return $invoice;
		}

		return $service->update( $invoice_id, $data );
	}
}
