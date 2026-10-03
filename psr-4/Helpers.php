<?php
/**
 * Helpers
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

/**
 * Helpers class
 */
final class Helpers {
	/**
	 * Format a UTC MySQL datetime as a local date.
	 *
	 * @param string|null $value  UTC MySQL datetime.
	 * @param string      $format Date format.
	 * @return string
	 */
	public static function format_date( ?string $value, string $format = 'D j M Y' ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		$date = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );

		return (string) \wp_date( $format, $date->getTimestamp() );
	}

	/**
	 * Format a UTC MySQL datetime as a local date and time.
	 *
	 * @param string|null $value UTC MySQL datetime.
	 * @return string
	 */
	public static function format_datetime( ?string $value ): string {
		return self::format_date( $value, 'D j M Y H:i' );
	}

	/**
	 * Get the status badges of an account.
	 *
	 * @param object $account Account.
	 * @return array<int, array{variation: string, content: string}>
	 */
	public static function get_status_badges( object $account ): array {
		$badges = [];

		$variations = [
			'active'        => 'success',
			'expiring soon' => 'warning',
			'expired'       => 'danger',
		];

		$labels = [
			'active'        => \__( 'Active', 'orbis-siteground' ),
			'expiring soon' => \__( 'Expiring soon', 'orbis-siteground' ),
			'expired'       => \__( 'Expired', 'orbis-siteground' ),
		];

		$badges[] = [
			'variation' => $variations[ $account->status ] ?? 'secondary',
			'content'   => $labels[ $account->status ] ?? (string) $account->status,
		];

		if ( null !== $account->suspended_at ) {
			$badges[] = [
				'variation' => 'danger',
				'content'   => \__( 'Suspended', 'orbis-siteground' ),
			];
		}

		if ( null !== $account->removed_at ) {
			$badges[] = [
				'variation' => 'dark',
				'content'   => \__( 'Removed', 'orbis-siteground' ),
			];
		}

		return $badges;
	}

	/**
	 * Get the status badges of a website.
	 *
	 * @param object $website Website.
	 * @return array<int, array{variation: string, content: string}>
	 */
	public static function get_website_status_badges( object $website ): array {
		$badges = [];

		$variations = [
			'active'          => 'success',
			'offline_mode'    => 'warning',
			'account_expired' => 'danger',
		];

		$labels = [
			'active'          => \__( 'Active', 'orbis-siteground' ),
			'offline_mode'    => \__( 'Offline mode', 'orbis-siteground' ),
			'account_expired' => \__( 'Account expired', 'orbis-siteground' ),
		];

		$badges[] = [
			'variation' => $variations[ $website->status ] ?? 'secondary',
			'content'   => $labels[ $website->status ] ?? (string) $website->status,
		];

		if ( (int) $website->suspended > 0 ) {
			$badges[] = [
				'variation' => 'danger',
				'content'   => \__( 'Suspended', 'orbis-siteground' ),
			];
		}

		if ( null !== $website->removed_at ) {
			$badges[] = [
				'variation' => 'dark',
				'content'   => \__( 'Removed', 'orbis-siteground' ),
			];
		}

		return $badges;
	}

	/**
	 * Get the label of a website CMS.
	 *
	 * @param string|null $cms SiteGround CMS, for example `wordpress`.
	 * @return string
	 */
	public static function get_cms_label( ?string $cms ): string {
		$labels = [
			'wordpress'   => 'WordPress',
			'woocommerce' => 'WooCommerce',
			'general'     => \__( 'General', 'orbis-siteground' ),
		];

		return $labels[ $cms ] ?? (string) $cms;
	}

	/**
	 * Get the URL of a hosting account in the SiteGround Client Area.
	 *
	 * @param string $siteground_id SiteGround account ID.
	 * @return string
	 */
	public static function get_client_area_account_url( string $siteground_id ): string {
		return 'https://my.siteground.com/services/hosting/' . \rawurlencode( $siteground_id );
	}

	/**
	 * Get the URL of a website in the SiteGround Client Area.
	 *
	 * @param string $siteground_id SiteGround website ID.
	 * @return string
	 */
	public static function get_client_area_website_url( string $siteground_id ): string {
		return 'https://my.siteground.com/websites/list/' . \rawurlencode( $siteground_id );
	}

	/**
	 * Get the URL of the SiteGround Site Tools of a website.
	 *
	 * @param string $siteground_id SiteGround website ID.
	 * @return string
	 */
	public static function get_site_tools_url( string $siteground_id ): string {
		return 'https://tools.siteground.com/dashboard?siteId=' . \rawurlencode( $siteground_id );
	}

	/**
	 * Render the SiteGround links of a website.
	 *
	 * @param object $website Website.
	 * @return void
	 */
	public static function render_website_siteground_links( object $website ): void {
		\printf(
			'<a href="%s">%s</a> · <a href="%s">%s</a>',
			\esc_url( self::get_client_area_website_url( $website->siteground_id ) ),
			\esc_html__( 'Client Area', 'orbis-siteground' ),
			\esc_url( self::get_site_tools_url( $website->siteground_id ) ),
			\esc_html__( 'Site Tools', 'orbis-siteground' )
		);
	}

	/**
	 * Render the SiteGround link of an account.
	 *
	 * @param object $account Account.
	 * @return void
	 */
	public static function render_account_siteground_link( object $account ): void {
		\printf(
			'<a href="%s">%s</a>',
			\esc_url( self::get_client_area_account_url( $account->siteground_id ) ),
			\esc_html__( 'Client Area', 'orbis-siteground' )
		);
	}

	/**
	 * Render a link to the SiteGround account of a website.
	 *
	 * Falls back to the account name when the account is not imported.
	 *
	 * @param object $website Website.
	 * @return void
	 */
	public static function render_website_account_link( object $website ): void {
		$account = null === $website->account_siteground_id ? null : Plugin::instance()->accounts->get_by_siteground_id( $website->account_siteground_id );

		if ( null === $account || null === $account->post_id ) {
			echo \esc_html( (string) $website->account_name );

			return;
		}

		\printf(
			'<a href="%s">%s</a>',
			\esc_url( (string) \get_permalink( (int) $account->post_id ) ),
			\esc_html( (string) $account->name )
		);
	}

	/**
	 * Get the URL of the PDF of an invoice.
	 *
	 * The PDFs are protected against direct access and served by the download
	 * handler of the admin controller.
	 *
	 * @param object $invoice Invoice.
	 * @return string
	 */
	public static function get_invoice_pdf_url( object $invoice ): string {
		return \add_query_arg(
			[
				'action'     => 'orbis_siteground_invoice_pdf',
				'invoice_id' => (int) $invoice->id,
			],
			\admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Render the PDF link of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return void
	 */
	public static function render_invoice_pdf_link( object $invoice ): void {
		\printf(
			'<a href="%s">%s</a>',
			\esc_url( self::get_invoice_pdf_url( $invoice ) ),
			\esc_html__( 'PDF', 'orbis-siteground' )
		);
	}

	/**
	 * Render the status badge of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @param string $context Context, `admin` or `theme`.
	 * @return void
	 */
	public static function render_invoice_status_badge( object $invoice, string $context = 'admin' ): void {
		$badge = [
			'variation' => 'success',
			'content'   => \__( 'Processed', 'orbis-siteground' ),
		];

		if ( null === $invoice->processed_at ) {
			$badge = [
				'variation' => 'warning',
				'content'   => \__( 'Unprocessed', 'orbis-siteground' ),
			];
		}

		self::render_badges( [ $badge ], $context );
	}

	/**
	 * Get the decoded data of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return array<string, mixed>
	 */
	public static function get_invoice_data( object $invoice ): array {
		$data = \json_decode( (string) $invoice->data, true );

		return \is_array( $data ) ? $data : [];
	}

	/**
	 * Get the line items of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_invoice_line_items( object $invoice ): array {
		$line_items = self::get_invoice_data( $invoice )['line_items'] ?? [];

		return \is_array( $line_items ) ? \array_values( \array_filter( $line_items, is_array( ... ) ) ) : [];
	}

	/**
	 * Get the domains of the lines of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return string[]
	 */
	public static function get_invoice_domains( object $invoice ): array {
		$domains = \array_map(
			fn( array $line_item ) => (string) ( $line_item['domain'] ?? '' ),
			self::get_invoice_line_items( $invoice )
		);

		return \array_values( \array_unique( \array_filter( $domains ) ) );
	}

	/**
	 * Format an amount.
	 *
	 * @param mixed  $amount   Amount.
	 * @param string $currency ISO 4217 currency code.
	 * @return string
	 */
	public static function format_amount( $amount, string $currency ): string {
		if ( ! \is_numeric( $amount ) ) {
			return '';
		}

		$symbols = [
			'EUR' => '€',
			'USD' => '$',
			'GBP' => '£',
		];

		return \trim( ( $symbols[ $currency ] ?? $currency ) . ' ' . \number_format_i18n( (float) $amount, 2 ) );
	}

	/**
	 * Get the label of a document type.
	 *
	 * @param string|null $document_type Document type, `invoice` or `credit_note`.
	 * @return string
	 */
	public static function get_document_type_label( ?string $document_type ): string {
		$labels = [
			'invoice'     => \__( 'Invoice', 'orbis-siteground' ),
			'credit_note' => \__( 'Credit note', 'orbis-siteground' ),
		];

		return $labels[ $document_type ] ?? (string) $document_type;
	}

	/**
	 * Get the label of a tax scheme.
	 *
	 * @param string|null $tax_scheme Tax scheme, for example `reverse_charge`.
	 * @return string
	 */
	public static function get_tax_scheme_label( ?string $tax_scheme ): string {
		$labels = [
			'standard'       => \__( 'Standard', 'orbis-siteground' ),
			'reverse_charge' => \__( 'Reverse charge', 'orbis-siteground' ),
			'exempt'         => \__( 'Exempt', 'orbis-siteground' ),
			'none'           => \__( 'None', 'orbis-siteground' ),
		];

		return $labels[ $tax_scheme ] ?? (string) $tax_scheme;
	}

	/**
	 * Get the label of an invoice line type.
	 *
	 * @param string|null $type Line type, for example `renewal`.
	 * @return string
	 */
	public static function get_line_type_label( ?string $type ): string {
		$labels = [
			'new'     => \__( 'New', 'orbis-siteground' ),
			'renewal' => \__( 'Renewal', 'orbis-siteground' ),
			'upgrade' => \__( 'Upgrade', 'orbis-siteground' ),
			'addon'   => \__( 'Add-on', 'orbis-siteground' ),
			'other'   => \__( 'Other', 'orbis-siteground' ),
		];

		return $labels[ $type ] ?? (string) $type;
	}

	/**
	 * Render the status badges of an account.
	 *
	 * @param object $account Account.
	 * @param string $context Context, `admin` or `theme`.
	 * @return void
	 */
	public static function render_status_badges( object $account, string $context = 'admin' ): void {
		self::render_badges( self::get_status_badges( $account ), $context );
	}

	/**
	 * Render the status badges of a website.
	 *
	 * @param object $website Website.
	 * @param string $context Context, `admin` or `theme`.
	 * @return void
	 */
	public static function render_website_status_badges( object $website, string $context = 'admin' ): void {
		self::render_badges( self::get_website_status_badges( $website ), $context );
	}

	/**
	 * Render badges.
	 *
	 * @param array<int, array{variation: string, content: string}> $badges  Badges.
	 * @param string                                                $context Context, `admin` or `theme`.
	 * @return void
	 */
	private static function render_badges( array $badges, string $context ): void {
		foreach ( $badges as $badge ) {
			if ( 'theme' === $context ) {
				\printf(
					'<span class="badge text-bg-%s">%s</span> ',
					\esc_attr( $badge['variation'] ),
					\esc_html( $badge['content'] )
				);

				continue;
			}

			\printf(
				'<code>%s</code> ',
				\esc_html( $badge['content'] )
			);
		}
	}
}
