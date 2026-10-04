<?php
/**
 * Invoice upload controller
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
 * Invoice upload controller class
 *
 * Handles the upload of SiteGround invoice PDFs.
 */
final readonly class InvoiceUploadController {
	/**
	 * Construct.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct(
		/**
		 * Plugin.
		 */
		private Plugin $plugin
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'admin_post_orbis_siteground_upload_invoices', $this->handle_upload( ... ) );
	}

	/**
	 * Handle upload.
	 *
	 * @return void
	 */
	private function handle_upload(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You are not allowed to upload SiteGround invoices.', 'orbis-siteground' ), 403 );
		}

		\check_admin_referer( 'orbis_siteground_upload_invoices', 'orbis_siteground_upload_invoices_nonce' );

		$service = new InvoiceService( $this->plugin->invoices, $this->plugin->accounts, $this->plugin->invoice_lines );

		$results = [];

		foreach ( $this->get_uploaded_files() as $file ) {
			if ( \UPLOAD_ERR_OK !== $file['error'] || ! \is_uploaded_file( $file['tmp_name'] ) ) {
				$results[] = [
					'status'  => 'error',
					'message' => \sprintf(
						/* translators: 1: file name, 2: PHP upload error code */
						\__( 'File upload error for "%1$s" (code %2$s).', 'orbis-siteground' ),
						$file['name'],
						(string) $file['error']
					),
				];

				continue;
			}

			try {
				[ 'invoice' => $invoice, 'created' => $created ] = $service->upload( $file['tmp_name'], $file['name'] );

				$results[] = [
					'status'     => $created ? 'created' : 'duplicate',
					'message'    => \sprintf(
						$created
							/* translators: %s: file name */
							? \__( '"%s" has been uploaded.', 'orbis-siteground' )
							/* translators: %s: file name */
							: \__( '"%s" was already uploaded.', 'orbis-siteground' ),
						$file['name']
					),
					'invoice_id' => (int) $invoice->id,
				];
			} catch ( InvalidArgumentException | RuntimeException $e ) {
				$results[] = [
					'status'  => 'error',
					'message' => $e->getMessage(),
				];
			}
		}

		\set_transient( self::get_results_transient_key(), $results, 5 * \MINUTE_IN_SECONDS );

		\wp_safe_redirect( AdminController::get_invoice_upload_url() );

		exit;
	}

	/**
	 * Get uploaded files.
	 *
	 * Normalizes the `$_FILES` array of the multiple file input.
	 *
	 * @return array<int, array{name: string, tmp_name: string, error: int}>
	 */
	private function get_uploaded_files(): array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce is verified in `handle_upload`, files are validated by the invoice service.
		$files = $_FILES['orbis_siteground_invoices'] ?? null;

		if ( ! \is_array( $files ) || ! \is_array( $files['name'] ?? null ) ) {
			return [];
		}

		$result = [];

		foreach ( $files['name'] as $index => $name ) {
			$result[] = [
				'name'     => (string) $name,
				'tmp_name' => (string) ( $files['tmp_name'][ $index ] ?? '' ),
				'error'    => (int) ( $files['error'][ $index ] ?? \UPLOAD_ERR_NO_FILE ),
			];
		}

		return $result;
	}

	/**
	 * Get the transient key for the upload results of the current user.
	 *
	 * @return string
	 */
	public static function get_results_transient_key(): string {
		return 'orbis_siteground_invoice_upload_' . \get_current_user_id();
	}
}
