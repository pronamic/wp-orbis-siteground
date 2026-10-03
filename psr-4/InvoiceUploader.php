<?php
/**
 * Invoice uploader
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
 * Invoice uploader class
 *
 * Stores a SiteGround invoice PDF in the protected `orbis-siteground` uploads
 * directory and upserts the invoice data, keyed by the invoice number.
 *
 * @link https://github.com/pronamic/pronamic-spar-pos-data-hub/blob/7bfd0a7f0d1a545b04b0a0836d326f8817bc2007/psr-4/Pages/UploadStorePage.php#L97-L119
 */
final readonly class InvoiceUploader {
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
	 * Upload invoice.
	 *
	 * @param array<string, mixed> $invoice   Invoice data, valid against the `siteground-invoice` JSON schema.
	 * @param string               $pdf       Base64 encoded PDF.
	 * @param string|null          $file_name Original file name.
	 * @return array{invoice: object, created: bool}
	 * @throws InvalidArgumentException When the PDF or invoice data is invalid.
	 * @throws RuntimeException When the PDF cannot be stored.
	 */
	public function upload( array $invoice, string $pdf, ?string $file_name = null ): array {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The PDF is passed base64 encoded by the ability.
		$content = \base64_decode( $pdf, true );

		if ( false === $content || ! \str_starts_with( $content, '%PDF-' ) ) {
			throw new InvalidArgumentException( \__( 'The PDF is not a valid base64 encoded PDF file.', 'orbis-siteground' ) );
		}

		if ( \strlen( $content ) > 10 * \MB_IN_BYTES ) {
			throw new InvalidArgumentException( \__( 'The PDF is larger than 10 MB.', 'orbis-siteground' ) );
		}

		$invoice_number = \trim( (string) ( $invoice['invoice_number'] ?? '' ) );
		$invoice_date   = (string) ( $invoice['invoice_date'] ?? '' );

		if ( '' === $invoice_number ) {
			throw new InvalidArgumentException( \__( 'The invoice number is empty.', 'orbis-siteground' ) );
		}

		if ( 1 !== \preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $invoice_date, $matches ) || ! \checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			throw new InvalidArgumentException( \__( 'The invoice date is not a valid YYYY-MM-DD date.', 'orbis-siteground' ) );
		}

		$file_path = $this->store_pdf( $content, $invoice_number, $matches[1], $matches[2] );

		$now = \current_time( 'mysql', true );

		$values = [
			'invoice_number' => $invoice_number,
			'document_type'  => (string) ( $invoice['document_type'] ?? 'invoice' ),
			'invoice_date'   => $invoice_date,
			'currency'       => (string) ( $invoice['currency'] ?? '' ),
			'payment_method' => $this->string_or_null( $invoice['payment_method'] ?? null ),
			'subtotal'       => (float) ( $invoice['totals']['subtotal'] ?? 0 ),
			'vat_amount'     => (float) ( $invoice['totals']['vat_amount'] ?? 0 ),
			'total'          => (float) ( $invoice['totals']['total'] ?? 0 ),
			'tax_scheme'     => $this->string_or_null( $invoice['tax']['scheme'] ?? null ),
			'file_path'      => $file_path,
			'file_name'      => $this->string_or_null( null === $file_name ? null : \sanitize_file_name( $file_name ) ),
			'file_size'      => \strlen( $content ),
			'file_sha256'    => \hash( 'sha256', $content ),
			'data'           => (string) \wp_json_encode( $invoice, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ),
			'updated_at'     => $now,
		];

		$stored = $this->repository->get_by_invoice_number( $invoice_number );

		if ( null === $stored ) {
			$values['post_id']    = $this->sync_post( null, $invoice_number, $invoice_date );
			$values['created_at'] = $now;

			$id = $this->repository->insert( $values );
		} else {
			$id = (int) $stored->id;

			$values['post_id'] = $this->sync_post( null === $stored->post_id ? null : (int) $stored->post_id, $invoice_number, $invoice_date );

			$this->repository->update( $id, $values );

			$this->maybe_delete_old_pdf( (string) $stored->file_path, $file_path );
		}

		$stored_invoice = $this->repository->get_by_id( $id );

		if ( null === $stored_invoice ) {
			throw new RuntimeException( \__( 'The invoice could not be saved.', 'orbis-siteground' ) );
		}

		return [
			'invoice' => $stored_invoice,
			'created' => null === $stored,
		];
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
	 * Store PDF.
	 *
	 * @param string $content        PDF content.
	 * @param string $invoice_number Invoice number.
	 * @param string $year           Invoice year.
	 * @param string $month          Invoice month.
	 * @return string File path relative to the uploads base directory.
	 * @throws RuntimeException When the PDF cannot be stored.
	 */
	private function store_pdf( string $content, string $invoice_number, string $year, string $month ): string {
		$path = \sprintf( 'orbis-siteground/%s/%s', $year, $month );

		$dir = $this->ensure_upload_dir( $path );

		$filename = \sanitize_file_name( 'siteground-invoice-' . $invoice_number . '.pdf' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct write to the protected uploads directory.
		if ( false === \file_put_contents( $dir . '/' . $filename, $content ) ) {
			throw new RuntimeException( \__( 'The PDF could not be stored.', 'orbis-siteground' ) );
		}

		return $path . '/' . $filename;
	}

	/**
	 * Ensure upload directory.
	 *
	 * Also protects the `orbis-siteground` directory against direct access,
	 * the PDFs are served by the download handler of the admin controller.
	 *
	 * @param string $path Path relative to the uploads base directory.
	 * @return string Absolute path.
	 */
	private function ensure_upload_dir( string $path ): string {
		$uploads = \wp_upload_dir( null, false );

		$dir = \trailingslashit( $uploads['basedir'] ) . $path;

		if ( ! \is_dir( $dir ) ) {
			\wp_mkdir_p( $dir );
		}

		$base_dir = self::get_base_dir();

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
	 * Delete the previous PDF of an invoice when it was stored at another path.
	 *
	 * @param string $old_path Old file path relative to the uploads base directory.
	 * @param string $new_path New file path relative to the uploads base directory.
	 * @return void
	 */
	private function maybe_delete_old_pdf( string $old_path, string $new_path ): void {
		if ( '' === $old_path || $old_path === $new_path || ! \str_starts_with( $old_path, 'orbis-siteground/' ) ) {
			return;
		}

		$uploads = \wp_upload_dir( null, false );

		\wp_delete_file( \trailingslashit( $uploads['basedir'] ) . $old_path );
	}

	/**
	 * Sync the post of an invoice.
	 *
	 * Creates the post when it does not exist (anymore), restores a trashed
	 * post and updates the title and date.
	 *
	 * @param int|null $post_id        Post ID.
	 * @param string   $invoice_number Invoice number.
	 * @param string   $invoice_date   Invoice date (YYYY-MM-DD).
	 * @return int|null Post ID.
	 */
	private function sync_post( ?int $post_id, string $invoice_number, string $invoice_date ): ?int {
		$post = null === $post_id ? null : \get_post( $post_id );

		$postarr = [
			'post_type'     => 'orbis_sg_invoice',
			'post_status'   => 'publish',
			'post_title'    => \sprintf(
				/* translators: %s: invoice number */
				\__( 'SiteGround invoice %s', 'orbis-siteground' ),
				$invoice_number
			),
			'post_date_gmt' => $invoice_date . ' 00:00:00',
			'post_date'     => $invoice_date . ' 00:00:00',
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
