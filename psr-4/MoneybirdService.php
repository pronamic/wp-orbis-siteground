<?php
/**
 * Moneybird service
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use InvalidArgumentException;
use RuntimeException;

/**
 * Moneybird service class
 *
 * Pushes SiteGround invoices to Moneybird as purchase invoices and links
 * invoices to purchase invoices that already exist in Moneybird.
 *
 * The Moneybird state of an invoice is stored in the `moneybird_status`
 * column: `pushed` (created by this plugin), `linked` (already in Moneybird)
 * or `skipped` (never to be pushed).
 */
final readonly class MoneybirdService {
	/**
	 * Construct.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Get a Moneybird setting.
	 *
	 * @param string $name Setting name without the `orbis_siteground_moneybird_` prefix.
	 * @return string
	 */
	public static function get_setting( string $name ): string {
		$value = \get_option( 'orbis_siteground_moneybird_' . $name, '' );

		return \is_scalar( $value ) ? \trim( (string) $value ) : '';
	}

	/**
	 * Get the Moneybird client.
	 *
	 * @return MoneybirdClient
	 * @throws InvalidArgumentException When the API token or administration ID is not set.
	 */
	public function get_client(): MoneybirdClient {
		$api_token         = self::get_setting( 'api_token' );
		$administration_id = self::get_setting( 'administration_id' );

		if ( '' === $api_token || '' === $administration_id ) {
			throw new InvalidArgumentException( 'Set the Moneybird API token and administration ID first.' );
		}

		return new MoneybirdClient( $api_token, $administration_id );
	}

	/**
	 * Get the Moneybird URL of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return string|null
	 */
	public static function get_url( object $invoice ): ?string {
		if ( null === $invoice->moneybird_id ) {
			return null;
		}

		return self::get_document_url( (string) $invoice->moneybird_id );
	}

	/**
	 * Get the Moneybird URL of a document.
	 *
	 * @param string $moneybird_id Moneybird document ID.
	 * @return string|null
	 */
	public static function get_document_url( string $moneybird_id ): ?string {
		$administration_id = self::get_setting( 'administration_id' );

		if ( '' === $administration_id ) {
			return null;
		}

		return 'https://moneybird.com/' . \rawurlencode( $administration_id ) . '/documents/' . \rawurlencode( $moneybird_id );
	}

	/**
	 * Get the label of a Moneybird status.
	 *
	 * @param string|null $status Moneybird status.
	 * @return string|null
	 */
	public static function get_status_label( ?string $status ): ?string {
		$labels = [
			'pushed'  => \__( 'Purchase invoice created by Orbis', 'orbis-siteground' ),
			'linked'  => \__( 'Linked to existing purchase invoice', 'orbis-siteground' ),
			'skipped' => \__( 'Skipped, not pushed to Moneybird', 'orbis-siteground' ),
		];

		return $labels[ $status ] ?? null;
	}

	/**
	 * Build the Moneybird purchase invoice attributes of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the invoice cannot be pushed.
	 */
	public function build_purchase_invoice( object $invoice ): array {
		if ( null === $invoice->processed_at ) {
			throw new InvalidArgumentException( 'The invoice is not processed.' );
		}

		$settings = [];

		foreach ( [ 'contact_id', 'ledger_account_id', 'tax_rate_id' ] as $name ) {
			$settings[ $name ] = self::get_setting( $name );

			if ( '' === $settings[ $name ] ) {
				throw new InvalidArgumentException( \sprintf( 'Set the Moneybird setting `%s` first.', $name ) );
			}
		}

		if ( null === $invoice->subtotal ) {
			throw new InvalidArgumentException( 'The invoice has no subtotal.' );
		}

		$lines = $this->plugin->invoice_lines->get_by_invoice_id( (int) $invoice->id );

		if ( [] === $lines ) {
			throw new InvalidArgumentException( 'The invoice has no lines.' );
		}

		$details = [];
		$sum     = 0;

		foreach ( $lines as $line ) {
			if ( null === $line->line_total ) {
				throw new InvalidArgumentException( \sprintf( 'Line %d has no total.', (int) $line->line_number ) );
			}

			$line_total = self::to_cents( (string) $line->line_total );

			$sum += $line_total;

			$amount = '1';
			$price  = (string) $line->line_total;

			if ( null !== $line->quantity && null !== $line->unit_price && self::to_cents( (string) $line->quantity ) * self::to_cents( (string) $line->unit_price ) === $line_total * 100 ) {
				$amount = self::trim_decimals( (string) $line->quantity );
				$price  = (string) $line->unit_price;
			}

			$detail = [
				'description'       => (string) $line->description,
				'amount'            => $amount,
				'price'             => $price,
				'ledger_account_id' => $settings['ledger_account_id'],
				'tax_rate_id'       => $settings['tax_rate_id'],
			];

			if ( null !== $line->start_date && null !== $line->end_date ) {
				$detail['period'] = \str_replace( '-', '', (string) $line->start_date ) . '..' . \str_replace( '-', '', (string) $line->end_date );
			}

			if ( null !== $line->account_moneybird_project_id && '' !== $line->account_moneybird_project_id ) {
				$detail['project_id'] = (string) $line->account_moneybird_project_id;
			}

			$details[] = $detail;
		}

		if ( self::to_cents( (string) $invoice->subtotal ) !== $sum ) {
			throw new InvalidArgumentException(
				\sprintf(
					'The sum of the line totals (%s) does not match the subtotal (%s).',
					\number_format( $sum / 100, 2, '.', '' ),
					(string) $invoice->subtotal
				)
			);
		}

		$purchase_invoice = [
			'contact_id'          => $settings['contact_id'],
			'reference'           => (string) $invoice->invoice_number,
			'date'                => (string) $invoice->invoice_date,
			'prices_are_incl_tax' => false,
			'details_attributes'  => $details,
		];

		if ( null !== $invoice->currency ) {
			$purchase_invoice['currency'] = (string) $invoice->currency;
		}

		return $purchase_invoice;
	}

	/**
	 * Push an invoice to Moneybird as a purchase invoice, with the PDF as attachment.
	 *
	 * @param object $invoice Invoice.
	 * @param bool   $force   Also push an invoice dated before the start date.
	 * @return string Moneybird purchase invoice ID.
	 * @throws InvalidArgumentException When the invoice cannot be pushed.
	 * @throws RuntimeException When Moneybird fails.
	 */
	public function push( object $invoice, bool $force = false ): string {
		if ( null !== $invoice->moneybird_status ) {
			throw new InvalidArgumentException( \sprintf( 'The invoice is already %s in Moneybird.', (string) $invoice->moneybird_status ) );
		}

		$start_date = self::get_setting( 'start_date' );

		if ( ! $force && '' === $start_date ) {
			throw new InvalidArgumentException( 'The Moneybird start date is not set, use `--force` to push the invoice anyway.' );
		}

		if ( ! $force && (string) $invoice->invoice_date < $start_date ) {
			throw new InvalidArgumentException( 'The invoice is dated before the Moneybird start date, use `--force` to push it anyway.' );
		}

		$purchase_invoice = $this->build_purchase_invoice( $invoice );

		$file = $this->get_pdf_file( $invoice );

		$client = $this->get_client();

		$result = $client->create_purchase_invoice( $purchase_invoice );

		$moneybird_id = (string) ( $result['id'] ?? '' );

		if ( '' === $moneybird_id ) {
			throw new RuntimeException( 'Moneybird did not return a purchase invoice ID.' );
		}

		$this->mark( (int) $invoice->id, 'pushed', $moneybird_id );

		try {
			$client->add_purchase_invoice_attachment( $moneybird_id, (string) \file_get_contents( $file ), (string) $invoice->file_name );
		} catch ( RuntimeException $e ) {
			throw new RuntimeException( \sprintf( 'Purchase invoice %s is created, but attaching the PDF failed: %s', $moneybird_id, $e->getMessage() ), 0, $e );
		}

		return $moneybird_id;
	}

	/**
	 * Mark the Moneybird status of an invoice.
	 *
	 * @param int         $id           Invoice ID.
	 * @param string      $status       Moneybird status: `pushed`, `linked` or `skipped`.
	 * @param string|null $moneybird_id Moneybird purchase invoice ID.
	 * @return void
	 */
	public function mark( int $id, string $status, ?string $moneybird_id = null ): void {
		$now = \current_time( 'mysql', true );

		$this->plugin->invoices->update(
			$id,
			[
				'updated_at'       => $now,
				'moneybird_id'     => $moneybird_id,
				'moneybird_status' => $status,
				'moneybird_at'     => $now,
			]
		);
	}

	/**
	 * Link invoices to the purchase invoices of the SiteGround contact that already exist in Moneybird.
	 *
	 * Purchase invoices are matched on the reference and the invoice number.
	 *
	 * @param bool $dry_run Report without linking.
	 * @return array{linked: array<int, string>, already_linked: int, mismatches: string[], unmatched: string[], open_before: object[]}
	 * @throws InvalidArgumentException When the contact ID is not set.
	 */
	public function backfill( bool $dry_run ): array {
		$contact_id = self::get_setting( 'contact_id' );

		if ( '' === $contact_id ) {
			throw new InvalidArgumentException( 'Set the Moneybird setting `contact_id` first.' );
		}

		$purchase_invoices = $this->get_client()->get_all(
			'/documents/purchase_invoices.json',
			[
				'filter' => 'contact_id:' . $contact_id,
			]
		);

		$invoices = $this->plugin->invoices->get_by_invoice_numbers();

		$report = [
			'linked'         => [],
			'already_linked' => 0,
			'mismatches'     => [],
			'unmatched'      => [],
			'open_before'    => [],
		];

		foreach ( $purchase_invoices as $purchase_invoice ) {
			if ( (string) ( $purchase_invoice['contact_id'] ?? '' ) !== $contact_id ) {
				continue;
			}

			$moneybird_id = (string) $purchase_invoice['id'];
			$reference    = \trim( (string) ( $purchase_invoice['reference'] ?? '' ) );

			$invoice = $invoices[ \strtolower( $reference ) ] ?? null;

			if ( null === $invoice ) {
				$report['unmatched'][] = \sprintf( '%s (%s, %s)', $reference, (string) ( $purchase_invoice['date'] ?? '' ), $moneybird_id );

				continue;
			}

			if ( null !== $invoice->moneybird_status ) {
				++$report['already_linked'];

				if ( null !== $invoice->moneybird_id && $moneybird_id !== (string) $invoice->moneybird_id ) {
					$report['mismatches'][] = \sprintf( 'Invoice %d (%s) is linked to %s, Moneybird also has %s.', (int) $invoice->id, $reference, (string) $invoice->moneybird_id, $moneybird_id );
				}

				continue;
			}

			$total = (string) ( $purchase_invoice['total_price_excl_tax'] ?? '' );

			if ( null !== $invoice->subtotal && \is_numeric( $total ) && self::to_cents( \number_format( (float) $total, 2, '.', '' ) ) !== self::to_cents( (string) $invoice->subtotal ) ) {
				$report['mismatches'][] = \sprintf( 'Invoice %d (%s): Moneybird total %s differs from subtotal %s.', (int) $invoice->id, $reference, $total, (string) $invoice->subtotal );
			}

			if ( ! $dry_run ) {
				$this->mark( (int) $invoice->id, 'linked', $moneybird_id );
			}

			$invoice->moneybird_status = 'linked';

			$report['linked'][ (int) $invoice->id ] = $moneybird_id;
		}

		$start_date = self::get_setting( 'start_date' );

		if ( '' !== $start_date ) {
			$report['open_before'] = \array_values(
				\array_filter(
					$this->plugin->invoices->get_moneybird_open_before( $start_date ),
					fn( $invoice ) => ! \array_key_exists( (int) $invoice->id, $report['linked'] )
				)
			);
		}

		return $report;
	}

	/**
	 * Get the active Moneybird projects.
	 *
	 * @link https://developer.moneybird.com/api/projects#list-all-projects-of-an-administration
	 * @return array<int, array<string, mixed>>
	 * @throws InvalidArgumentException When the API token or administration ID is not set.
	 * @throws RuntimeException When Moneybird fails.
	 */
	public function get_projects(): array {
		return $this->get_client()->get_all( '/projects.json' );
	}

	/**
	 * Find the Moneybird projects whose name exactly matches a SiteGround account name.
	 *
	 * @param array<int, array<string, mixed>> $projects     Moneybird projects.
	 * @param string                           $account_name Account name.
	 * @return array<int, array<string, mixed>> Matching projects.
	 */
	public static function find_projects( array $projects, string $account_name ): array {
		return \array_values(
			\array_filter(
				$projects,
				fn( $project ) => 0 === \strcasecmp( \trim( (string) ( $project['name'] ?? '' ) ), $account_name )
			)
		);
	}

	/**
	 * Get the PDF file of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return string Absolute file path.
	 * @throws InvalidArgumentException When the PDF does not exist.
	 */
	private function get_pdf_file( object $invoice ): string {
		$base_dir = \realpath( InvoiceService::get_base_dir() );
		$uploads  = \wp_upload_dir( null, false );
		$file     = \realpath( \trailingslashit( $uploads['basedir'] ) . $invoice->file_path );

		if ( false === $base_dir || false === $file || ! \str_starts_with( $file, $base_dir . \DIRECTORY_SEPARATOR ) ) {
			throw new InvalidArgumentException( 'The invoice PDF does not exist.' );
		}

		return $file;
	}

	/**
	 * Convert a decimal string with at most two decimals to cents.
	 *
	 * @param string $amount Amount, for example `-14.99`.
	 * @return int
	 * @throws InvalidArgumentException When the amount is not a decimal string.
	 */
	private static function to_cents( string $amount ): int {
		if ( 1 !== \preg_match( '/^(-?)(\d+)(?:\.(\d{1,2})0*)?$/', \trim( $amount ), $matches ) ) {
			throw new InvalidArgumentException( \sprintf( 'Invalid amount "%s".', $amount ) );
		}

		$cents = (int) $matches[2] * 100 + (int) \str_pad( $matches[3] ?? '', 2, '0' );

		return '-' === $matches[1] ? -$cents : $cents;
	}

	/**
	 * Trim the trailing decimal zeros of a decimal string.
	 *
	 * @param string $value Value, for example `1.00`.
	 * @return string Value, for example `1`.
	 */
	private static function trim_decimals( string $value ): string {
		return \str_contains( $value, '.' ) ? \rtrim( \rtrim( $value, '0' ), '.' ) : $value;
	}
}
