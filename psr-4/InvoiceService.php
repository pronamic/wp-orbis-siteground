<?php
/**
 * Invoice service
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
use InvalidArgumentException;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Invoice service class
 *
 * SiteGround invoices are processed in two steps:
 *
 * 1. A user uploads the PDF in the WordPress admin. The PDF is stored in the
 *    protected `orbis-siteground` uploads directory, the text of the PDF is
 *    extracted and an unprocessed invoice is created, keyed by the SHA-256
 *    hash of the PDF.
 * 2. An AI client reads the text of the PDF with the get invoice ability and
 *    updates the invoice with the structured data, valid against the
 *    `siteground-invoice` JSON schema, with the update invoice ability.
 *
 * The lines of a processed invoice are stored in the invoice lines table,
 * linked to the SiteGround hosting account and with the period the line
 * applies to. The period is not on the invoice, it is derived from the
 * account data.
 *
 * @link https://github.com/pronamic/pronamic-spar-pos-data-hub/blob/7bfd0a7f0d1a545b04b0a0836d326f8817bc2007/psr-4/Pages/UploadStorePage.php#L97-L119
 */
final readonly class InvoiceService {
	/**
	 * Construct.
	 *
	 * @param InvoiceRepository     $repository Invoice repository.
	 * @param AccountRepository     $accounts   Account repository.
	 * @param InvoiceLineRepository $lines      Invoice line repository.
	 */
	public function __construct(
		/**
		 * Invoice repository.
		 */
		private InvoiceRepository $repository,
		/**
		 * Account repository.
		 */
		private AccountRepository $accounts,
		/**
		 * Invoice line repository.
		 */
		private InvoiceLineRepository $lines
	) {
	}

	/**
	 * Upload invoice PDF.
	 *
	 * @param string $tmp_file  Path of the uploaded file.
	 * @param string $file_name Original file name.
	 * @return array{invoice: object, created: bool} The existing invoice and `created` false when the PDF was uploaded before.
	 * @throws InvalidArgumentException When the file is not a PDF.
	 * @throws RuntimeException When the PDF cannot be stored.
	 */
	public function upload( string $tmp_file, string $file_name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		$content = \file_get_contents( $tmp_file );

		if ( false === $content || ! \str_starts_with( $content, '%PDF-' ) ) {
			throw new InvalidArgumentException(
				\sprintf(
					/* translators: %s: file name */
					\__( '"%s" is not a PDF file.', 'orbis-siteground' ),
					$file_name
				)
			);
		}

		$file_sha256 = \hash( 'sha256', $content );

		$stored = $this->repository->get_by_file_sha256( $file_sha256 );

		if ( null !== $stored ) {
			return [
				'invoice' => $stored,
				'created' => false,
			];
		}

		$file_name = \sanitize_file_name( $file_name );

		$path = \gmdate( 'Y/m' );

		$dir = $this->ensure_upload_dir( $path );

		$filename = \wp_unique_filename( $dir, $file_name );

		if ( ! \move_uploaded_file( $tmp_file, $dir . '/' . $filename ) ) {
			throw new RuntimeException(
				\sprintf(
					/* translators: %s: file name */
					\__( 'Could not move uploaded file "%s".', 'orbis-siteground' ),
					$file_name
				)
			);
		}

		$now = \current_time( 'mysql', true );

		$id = $this->repository->insert(
			[
				'created_at'  => $now,
				'updated_at'  => $now,
				'user_id'     => \get_current_user_id(),
				'file_sha256' => $file_sha256,
				'file_path'   => 'orbis-siteground/' . $path . '/' . $filename,
				'file_name'   => $file_name,
				'file_size'   => \strlen( $content ),
				'text'        => $this->extract_text( $content ),
			]
		);

		if ( 0 === $id ) {
			throw new RuntimeException( \__( 'The invoice could not be saved.', 'orbis-siteground' ) );
		}

		$invoice = $this->repository->get_by_id( $id );

		if ( null === $invoice ) {
			throw new RuntimeException( \__( 'The invoice could not be saved.', 'orbis-siteground' ) );
		}

		$this->repository->update( $id, [ 'post_id' => $this->sync_post( $invoice ) ] );

		return [
			'invoice' => $this->repository->get_by_id( $id ) ?? $invoice,
			'created' => true,
		];
	}

	/**
	 * Update invoice with the structured invoice data.
	 *
	 * @param int                  $id   Invoice ID.
	 * @param array<string, mixed> $data Invoice data, valid against the `siteground-invoice` JSON schema.
	 * @return object
	 * @throws InvalidArgumentException When the invoice does not exist or the data is invalid.
	 * @throws RuntimeException When the invoice cannot be saved.
	 */
	public function update( int $id, array $data ): object {
		$invoice = $this->repository->get_by_id( $id );

		if ( null === $invoice ) {
			throw new InvalidArgumentException( \__( 'SiteGround invoice not found.', 'orbis-siteground' ) );
		}

		$data = self::normalize_data( $data );

		$invoice_number = \trim( (string) ( $data['invoice_number'] ?? '' ) );
		$invoice_date   = (string) ( $data['invoice_date'] ?? '' );

		if ( '' === $invoice_number ) {
			throw new InvalidArgumentException( \__( 'The invoice number is empty.', 'orbis-siteground' ) );
		}

		if ( 1 !== \preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $invoice_date, $matches ) || ! \checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			throw new InvalidArgumentException( \__( 'The invoice date is not a valid YYYY-MM-DD date.', 'orbis-siteground' ) );
		}

		$other = $this->repository->get_by_invoice_number( $invoice_number );

		if ( null !== $other && (int) $other->id !== $id ) {
			throw new InvalidArgumentException(
				\sprintf(
					/* translators: 1: invoice number, 2: invoice ID */
					\__( 'Invoice number %1$s is already registered for SiteGround invoice %2$d.', 'orbis-siteground' ),
					$invoice_number,
					(int) $other->id
				)
			);
		}

		$now = \current_time( 'mysql', true );

		$this->repository->update(
			$id,
			[
				'updated_at'     => $now,
				'processed_at'   => $now,
				'invoice_number' => $invoice_number,
				'document_type'  => $this->string_or_null( $data['document_type'] ?? null ),
				'invoice_date'   => $invoice_date,
				'currency'       => $this->string_or_null( $data['currency'] ?? null ),
				'payment_method' => $this->string_or_null( $data['payment_method'] ?? null ),
				'subtotal'       => $data['totals']['subtotal'] ?? null,
				'vat_amount'     => $data['totals']['vat_amount'] ?? null,
				'total'          => $data['totals']['total'] ?? null,
				'tax_scheme'     => $this->string_or_null( $data['tax']['scheme'] ?? null ),
				'data'           => (string) \wp_json_encode( $data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ),
			]
		);

		$invoice = $this->repository->get_by_id( $id );

		if ( null === $invoice ) {
			throw new RuntimeException( \__( 'The invoice could not be saved.', 'orbis-siteground' ) );
		}

		$post_id = $this->sync_post( $invoice );

		$stored_post_id = null === $invoice->post_id ? null : (int) $invoice->post_id;

		if ( $stored_post_id !== $post_id ) {
			$this->repository->update( $id, [ 'post_id' => $post_id ] );

			$invoice->post_id = $post_id;
		}

		$this->sync_lines( $invoice );

		return $invoice;
	}

	/**
	 * Sync the lines of an invoice to the invoice lines table.
	 *
	 * @param object $invoice Invoice.
	 * @return array<int, array<string, mixed>> Lines keyed by line number.
	 */
	public function sync_lines( object $invoice ): array {
		$lines = $this->derive_lines( $invoice );

		foreach ( $lines as $line_number => $line ) {
			$this->lines->upsert( (int) $invoice->id, $line_number, $line );
		}

		$this->lines->delete_after( (int) $invoice->id, \count( $lines ) );

		return $lines;
	}

	/**
	 * Derive the lines of an invoice from the invoice data and the account data.
	 *
	 * @param object $invoice Invoice.
	 * @return array<int, array<string, mixed>> Lines keyed by line number.
	 */
	private function derive_lines( object $invoice ): array {
		$line_items = Helpers::get_invoice_line_items( $invoice );

		$account_names = \array_map( Helpers::get_line_account_name( ... ), $line_items );

		$accounts = [];

		foreach ( $this->accounts->get_by_names( \array_filter( $account_names ) ) as $name => $account ) {
			$accounts[ \strtolower( $name ) ] = $account;
		}

		$lines = [];

		foreach ( $line_items as $index => $line_item ) {
			$account_name = $account_names[ $index ];

			$account = null === $account_name ? null : ( $accounts[ \strtolower( $account_name ) ] ?? null );

			$type = $this->string_or_null( $line_item['type'] ?? null );

			$period_months = Helpers::parse_period_months( $line_item['period'] ?? null );

			[ $start_date, $end_date ] = null === $invoice->invoice_date ? [ null, null ] : self::derive_period(
				(string) $type,
				(string) $invoice->invoice_date,
				$period_months,
				null === $account ? null : $account->expires_at
			);

			$lines[ $index + 1 ] = [
				'account_id'       => null === $account ? null : (int) $account->id,
				'account_name'     => $account_name,
				'description'      => \mb_substr( (string) ( $line_item['description'] ?? '' ), 0, 255 ),
				'type'             => $type,
				'product'          => $this->string_or_null( $line_item['product'] ?? null ),
				'period'           => $this->string_or_null( $line_item['period'] ?? null ),
				'period_months'    => $period_months,
				'quantity'         => $this->number_or_null( $line_item['quantity'] ?? null ),
				'vat_rate_percent' => $this->number_or_null( $line_item['vat_rate_percent'] ?? null ),
				'unit_price'       => $this->number_or_null( $line_item['unit_price'] ?? null ),
				'line_total'       => $this->number_or_null( $line_item['line_total'] ?? null ),
				'start_date'       => $start_date,
				'end_date'         => $end_date,
			];
		}

		return $lines;
	}

	/**
	 * Derive the period of an invoice line.
	 *
	 * SiteGround bills a renewal some days before the account expires, the
	 * renewal period starts at the expiration date. The expiration dates of
	 * an account are a series with steps of the period, starting from the
	 * current expiration date of the account. The start of a renewal is the
	 * expiration date in that series closest to the invoice date.
	 *
	 * Other lines, and renewals without account data, start at the invoice date.
	 *
	 * @param string      $type          Line type, for example `renewal`.
	 * @param string      $invoice_date  Invoice date in YYYY-MM-DD format.
	 * @param int|null    $period_months Number of months of the line period.
	 * @param string|null $expires_at    Current expiration date of the account (UTC MySQL datetime).
	 * @return array{0: string|null, 1: string|null} Start date and end date in YYYY-MM-DD format.
	 */
	public static function derive_period( string $type, string $invoice_date, ?int $period_months, ?string $expires_at ): array {
		if ( null === $period_months || $period_months < 1 ) {
			return [ null, null ];
		}

		$utc = new DateTimeZone( 'UTC' );

		$date = new DateTimeImmutable( \substr( $invoice_date, 0, 10 ), $utc );

		if ( 'renewal' !== $type || null === $expires_at || '' === $expires_at ) {
			return [
				$date->format( 'Y-m-d' ),
				self::add_months( $date, $period_months )->format( 'Y-m-d' ),
			];
		}

		$anchor = new DateTimeImmutable( \substr( $expires_at, 0, 10 ), $utc );

		$months = ( (int) $date->format( 'Y' ) - (int) $anchor->format( 'Y' ) ) * 12 + (int) $date->format( 'n' ) - (int) $anchor->format( 'n' );

		$step = (int) \floor( $months / $period_months );

		$best_step = $step;
		$best_diff = null;

		for ( $i = $step - 1; $i <= $step + 2; $i++ ) {
			$candidate = self::add_months( $anchor, $i * $period_months );

			$diff = \abs( $candidate->getTimestamp() - $date->getTimestamp() );

			if ( null === $best_diff || $diff <= $best_diff ) {
				$best_step = $i;
				$best_diff = $diff;
			}
		}

		return [
			self::add_months( $anchor, $best_step * $period_months )->format( 'Y-m-d' ),
			self::add_months( $anchor, ( $best_step + 1 ) * $period_months )->format( 'Y-m-d' ),
		];
	}

	/**
	 * Add months to a date, clamped to the last day of the month.
	 *
	 * For example 2026-01-31 plus 1 month is 2026-02-28.
	 *
	 * @param DateTimeImmutable $date   Date.
	 * @param int               $months Number of months, can be negative.
	 * @return DateTimeImmutable
	 */
	private static function add_months( DateTimeImmutable $date, int $months ): DateTimeImmutable {
		$first = $date->modify( 'first day of this month' )->modify( \sprintf( '%+d months', $months ) );

		$day = \min( (int) $date->format( 'j' ), (int) $first->format( 't' ) );

		return $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'n' ), $day );
	}

	/**
	 * Normalize the amounts of invoice data to numeric strings.
	 *
	 * Amounts are never processed as floats, to prevent floating point
	 * rounding errors.
	 *
	 * @param array<string, mixed> $data Invoice data.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When an amount is not numeric.
	 */
	public static function normalize_data( array $data ): array {
		if ( isset( $data['totals'] ) && \is_array( $data['totals'] ) ) {
			foreach ( [ 'subtotal', 'vat_amount', 'total' ] as $key ) {
				if ( \array_key_exists( $key, $data['totals'] ) ) {
					$data['totals'][ $key ] = self::normalize_amount( $data['totals'][ $key ] );
				}
			}
		}

		if ( isset( $data['line_items'] ) && \is_array( $data['line_items'] ) ) {
			foreach ( $data['line_items'] as $index => $line_item ) {
				if ( ! \is_array( $line_item ) ) {
					continue;
				}

				foreach ( [ 'unit_price', 'line_total' ] as $key ) {
					if ( \array_key_exists( $key, $line_item ) ) {
						$data['line_items'][ $index ][ $key ] = self::normalize_amount( $line_item[ $key ] );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Normalize an amount to a numeric string with at least two decimals.
	 *
	 * @param mixed $amount Amount.
	 * @return string|null
	 * @throws InvalidArgumentException When the amount is not numeric.
	 */
	public static function normalize_amount( $amount ): ?string {
		if ( null === $amount ) {
			return null;
		}

		$value = \is_string( $amount ) ? \trim( $amount ) : '';

		if ( 1 !== \preg_match( '/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches ) ) {
			throw new InvalidArgumentException(
				\sprintf(
					/* translators: %s: amount */
					\__( 'The amount "%s" is not a numeric string.', 'orbis-siteground' ),
					\is_scalar( $amount ) ? (string) $amount : \gettype( $amount )
				)
			);
		}

		return $matches[1] . $matches[2] . '.' . \str_pad( $matches[3] ?? '', 2, '0' );
	}

	/**
	 * Get the absolute path of the invoices upload directory.
	 *
	 * @return string
	 */
	public static function get_base_dir(): string {
		$uploads = \wp_upload_dir( null, false );

		return \trailingslashit( $uploads['basedir'] ) . 'orbis-siteground';
	}

	/**
	 * Get the post title of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return string
	 */
	public static function get_title( object $invoice ): string {
		if ( null === $invoice->invoice_number ) {
			return (string) $invoice->file_name;
		}

		return \sprintf(
			/* translators: %s: invoice number */
			\__( 'SiteGround invoice %s', 'orbis-siteground' ),
			$invoice->invoice_number
		);
	}

	/**
	 * Extract the text of a PDF.
	 *
	 * @param string $content PDF content.
	 * @return string|null
	 */
	private function extract_text( string $content ): ?string {
		try {
			$text = \trim( ( new Parser() )->parseContent( $content )->getText() );
		} catch ( Throwable ) {
			return null;
		}

		return '' === $text ? null : $text;
	}

	/**
	 * Ensure upload directory.
	 *
	 * Also protects the `orbis-siteground` directory against direct access,
	 * the PDFs are served by the download handler of the admin controller.
	 *
	 * @param string $path Path relative to the `orbis-siteground` directory.
	 * @return string Absolute path.
	 */
	private function ensure_upload_dir( string $path ): string {
		$base_dir = self::get_base_dir();

		$dir = $base_dir . '/' . $path;

		if ( ! \is_dir( $dir ) ) {
			\wp_mkdir_p( $dir );
		}

		$files = [
			'.htaccess' => "Require all denied\n",
			'index.php' => "<?php\n// Silence is golden.\n",
		];

		foreach ( $files as $name => $file_content ) {
			if ( ! \file_exists( $base_dir . '/' . $name ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct write to the protected uploads directory.
				\file_put_contents( $base_dir . '/' . $name, $file_content );
			}
		}

		return $dir;
	}

	/**
	 * Sync the post of an invoice.
	 *
	 * Creates the post when it does not exist (anymore), restores a trashed
	 * post and updates the title and date.
	 *
	 * @param object $invoice Invoice.
	 * @return int|null Post ID.
	 */
	private function sync_post( object $invoice ): ?int {
		$post = null === $invoice->post_id ? null : \get_post( (int) $invoice->post_id );

		$date = null === $invoice->invoice_date ? $invoice->created_at : $invoice->invoice_date . ' 00:00:00';

		$postarr = [
			'post_type'     => 'orbis_sg_invoice',
			'post_status'   => 'publish',
			'post_title'    => self::get_title( $invoice ),
			'post_date_gmt' => $date,
			'post_date'     => null === $invoice->invoice_date ? \get_date_from_gmt( $date ) : $date,
		];

		if ( null === $post ) {
			$result = \wp_insert_post( $postarr, true );

			return \is_wp_error( $result ) ? null : $result;
		}

		if ( 'trash' === $post->post_status ) {
			\wp_untrash_post( $post->ID );
		}

		$postarr['ID'] = $post->ID;

		\wp_update_post( $postarr );

		return $post->ID;
	}

	/**
	 * Number or null.
	 *
	 * Numbers are returned as strings, to prevent floating point rounding errors.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private function number_or_null( $value ): ?string {
		if ( \is_int( $value ) || \is_float( $value ) || ( \is_string( $value ) && \is_numeric( \trim( $value ) ) ) ) {
			return \trim( (string) $value );
		}

		return null;
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
}
