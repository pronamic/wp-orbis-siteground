<?php
/**
 * Moneybird client
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use RuntimeException;

/**
 * Moneybird client class
 *
 * Minimal client for the Moneybird API, authenticated with a personal API token.
 *
 * @link https://developer.moneybird.com/
 */
final readonly class MoneybirdClient {
	/**
	 * Construct.
	 *
	 * @param string $api_token         Moneybird API token.
	 * @param string $administration_id Moneybird administration ID.
	 */
	public function __construct(
		private string $api_token,
		public string $administration_id
	) {
	}

	/**
	 * Get the administrations of the API token.
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws RuntimeException When the request fails.
	 */
	public function get_administrations(): array {
		return $this->request( 'GET', 'https://moneybird.com/api/v2/administrations.json' );
	}

	/**
	 * Get a resource of the administration.
	 *
	 * @param string               $path  Path, for example `/tax_rates.json`.
	 * @param array<string, mixed> $query Query parameters.
	 * @return array<mixed>
	 * @throws RuntimeException When the request fails.
	 */
	public function get( string $path, array $query = [] ): array {
		return $this->request( 'GET', \add_query_arg( \urlencode_deep( $query ), $this->get_url( $path ) ) );
	}

	/**
	 * Get all items of a paginated list of the administration.
	 *
	 * @param string               $path  Path, for example `/documents/purchase_invoices.json`.
	 * @param array<string, mixed> $query Query parameters.
	 * @return array<int, array<string, mixed>>
	 * @throws RuntimeException When a request fails.
	 */
	public function get_all( string $path, array $query = [] ): array {
		$items = [];

		for ( $page = 1; ; $page++ ) {
			$result = $this->get(
				$path,
				[
					'page'     => $page,
					'per_page' => 100,
				] + $query
			);

			if ( [] === $result ) {
				break;
			}

			\array_push( $items, ...$result );

			if ( \count( $result ) < 100 ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Create a purchase invoice.
	 *
	 * @link https://developer.moneybird.com/api/documents-purchase-invoices#create-a-new-purchase-invoice
	 * @param array<string, mixed> $purchase_invoice Purchase invoice attributes.
	 * @return array<string, mixed> Created purchase invoice.
	 * @throws RuntimeException When the request fails.
	 */
	public function create_purchase_invoice( array $purchase_invoice ): array {
		return $this->request(
			'POST',
			$this->get_url( '/documents/purchase_invoices.json' ),
			[
				'Content-Type' => 'application/json',
			],
			(string) \wp_json_encode( [ 'purchase_invoice' => $purchase_invoice ] ),
			201
		);
	}

	/**
	 * Add an attachment to a purchase invoice.
	 *
	 * @param string $id        Moneybird purchase invoice ID.
	 * @param string $content   File content.
	 * @param string $file_name File name.
	 * @return void
	 * @throws RuntimeException When the request fails.
	 */
	public function add_purchase_invoice_attachment( string $id, string $content, string $file_name ): void {
		$boundary = \wp_generate_password( 24, false );

		$body = \implode(
			"\r\n",
			[
				'--' . $boundary,
				\sprintf( 'Content-Disposition: form-data; name="file"; filename="%s"', \str_replace( '"', '', $file_name ) ),
				'Content-Type: application/pdf',
				'',
				$content,
				'--' . $boundary . '--',
				'',
			]
		);

		$this->request(
			'POST',
			$this->get_url( '/documents/purchase_invoices/' . \rawurlencode( $id ) . '/attachments.json' ),
			[
				'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			],
			$body
		);
	}

	/**
	 * Get the API URL of a path of the administration.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function get_url( string $path ): string {
		return 'https://moneybird.com/api/v2/' . \rawurlencode( $this->administration_id ) . $path;
	}

	/**
	 * Request.
	 *
	 * @param string                $method          HTTP method.
	 * @param string                $url             URL.
	 * @param array<string, string> $headers         Extra headers.
	 * @param string|null           $body            Body.
	 * @param int|null              $expected_status Expected status code, `null` for any 2xx status.
	 * @return array<mixed> Decoded response body.
	 * @throws RuntimeException When the request fails or the response is unexpected.
	 */
	private function request( string $method, string $url, array $headers = [], ?string $body = null, ?int $expected_status = null ): array {
		$response = \wp_remote_request(
			$url,
			[
				'method'  => $method,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_token,
					'Accept'        => 'application/json',
				] + $headers,
				'body'    => $body,
				'timeout' => 30,
			]
		);

		if ( \is_wp_error( $response ) ) {
			throw new RuntimeException( \sprintf( 'Moneybird request failed: %s', $response->get_error_message() ) );
		}

		$status = (int) \wp_remote_retrieve_response_code( $response );

		// Moneybird IDs are 64-bit integers, keep them as strings.
		$data = \json_decode( \wp_remote_retrieve_body( $response ), true, 512, \JSON_BIGINT_AS_STRING );

		$valid = null === $expected_status ? ( $status >= 200 && $status < 300 ) : ( $expected_status === $status );

		if ( ! $valid ) {
			$error = \is_array( $data ) && isset( $data['error'] ) ? \wp_json_encode( $data['error'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) : \wp_remote_retrieve_response_message( $response );

			throw new RuntimeException( \sprintf( 'Moneybird responded with HTTP %d: %s', $status, (string) $error ) );
		}

		return \is_array( $data ) ? $data : [];
	}
}
