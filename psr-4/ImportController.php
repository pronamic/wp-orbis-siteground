<?php
/**
 * Import controller
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use InvalidArgumentException;

/**
 * Import controller class
 *
 * Handles the upload of a SiteGround accounts or websites JSON file.
 */
final readonly class ImportController {
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
		\add_action( 'admin_post_orbis_siteground_import', $this->handle_upload( ... ) );
	}

	/**
	 * Handle upload.
	 *
	 * @return void
	 */
	private function handle_upload(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You are not allowed to import SiteGround data.', 'orbis-siteground' ), 403 );
		}

		\check_admin_referer( 'orbis_siteground_import', 'orbis_siteground_import_nonce' );

		try {
			$file = $this->get_uploaded_file();

			[ 'type' => $type, 'result' => $result ] = $this->import_json( $file['contents'] );

			\update_option(
				'orbis_siteground_last_import_' . $type,
				[
					'type'      => $type,
					'time'      => \time(),
					'file_name' => \sanitize_file_name( $file['name'] ),
					'user_id'   => \get_current_user_id(),
					'result'    => $result->to_array(),
				],
				false
			);

			$this->redirect( [ 'imported' => $type ] );
		} catch ( InvalidArgumentException $e ) {
			\set_transient( self::get_error_transient_key(), $e->getMessage(), 5 * \MINUTE_IN_SECONDS );

			$this->redirect( [ 'import_error' => '1' ] );
		}
	}

	/**
	 * Import a SiteGround JSON string.
	 *
	 * Detects from the JSON whether it is a SiteGround accounts or websites
	 * response and imports it with the matching importer.
	 *
	 * @param string $json JSON.
	 * @return array{type: string, result: ImportResult} Import type (`accounts` or `websites`) and result.
	 * @throws InvalidArgumentException When the JSON does not contain SiteGround accounts or websites.
	 */
	private function import_json( string $json ): array {
		$data = \json_decode( $json, true );

		if ( ! \is_array( $data ) ) {
			throw new InvalidArgumentException( \__( 'The file does not contain valid JSON.', 'orbis-siteground' ) );
		}

		if ( isset( $data['data']['accounts'] ) && \is_array( $data['data']['accounts'] ) ) {
			return [
				'type'   => 'accounts',
				'result' => ( new AccountImporter( $this->plugin->accounts ) )->import( $data['data']['accounts'] ),
			];
		}

		if ( isset( $data['data']['websites'] ) && \is_array( $data['data']['websites'] ) ) {
			return [
				'type'   => 'websites',
				'result' => ( new WebsiteImporter( $this->plugin->websites ) )->import( $data['data']['websites'] ),
			];
		}

		throw new InvalidArgumentException( \__( 'The JSON does not contain SiteGround accounts (`data.accounts`) or websites (`data.websites`).', 'orbis-siteground' ) );
	}

	/**
	 * Get uploaded file.
	 *
	 * @return array{name: string, contents: string}
	 * @throws InvalidArgumentException When no valid file was uploaded.
	 */
	private function get_uploaded_file(): array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce is verified in `handle_upload`, file is validated below.
		$file = $_FILES['orbis_siteground_file'] ?? null;

		if ( ! \is_array( $file ) || ! isset( $file['error'], $file['tmp_name'], $file['name'] ) || \UPLOAD_ERR_OK !== $file['error'] ) {
			throw new InvalidArgumentException( \__( 'No file was uploaded.', 'orbis-siteground' ) );
		}

		if ( ! \is_uploaded_file( $file['tmp_name'] ) ) {
			throw new InvalidArgumentException( \__( 'The uploaded file is invalid.', 'orbis-siteground' ) );
		}

		$contents = \file_get_contents( $file['tmp_name'] );

		if ( false === $contents ) {
			throw new InvalidArgumentException( \__( 'The uploaded file could not be read.', 'orbis-siteground' ) );
		}

		return [
			'name'     => (string) $file['name'],
			'contents' => $contents,
		];
	}

	/**
	 * Get the transient key for the import error of the current user.
	 *
	 * @return string
	 */
	public static function get_error_transient_key(): string {
		return 'orbis_siteground_import_error_' . \get_current_user_id();
	}

	/**
	 * Redirect back to the import page.
	 *
	 * @param array<string, string> $args Query arguments.
	 * @return never
	 */
	private function redirect( array $args ): never {
		\wp_safe_redirect( \add_query_arg( $args, AdminController::get_import_url() ) );

		exit;
	}
}
