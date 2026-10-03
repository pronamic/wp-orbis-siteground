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
 * @link https://github.com/pronamic/pronamic-spar-pos-data-hub/blob/7bfd0a7f0d1a545b04b0a0836d326f8817bc2007/psr-4/Pages/UploadStorePage.php#L97-L119
 */
final readonly class InvoiceService {
	/**
	 * Construct.
	 *
	 * @param InvoiceRepository $repository Invoice repository.
	 */
	public function __construct(
		/**
		 * Invoice repository.
		 */
		private InvoiceRepository $repository
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
				'subtotal'       => (float) ( $data['totals']['subtotal'] ?? 0 ),
				'vat_amount'     => (float) ( $data['totals']['vat_amount'] ?? 0 ),
				'total'          => (float) ( $data['totals']['total'] ?? 0 ),
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

		return $invoice;
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
