<?php
/**
 * SiteGround invoice details
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object $invoice SiteGround invoice.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$invoice_data = Helpers::get_invoice_data( $invoice );

$currency = (string) $invoice->currency;

$items = [
	\__( 'Invoice number', 'orbis-siteground' )      => $invoice->invoice_number,
	\__( 'Type', 'orbis-siteground' )                => null === $invoice->document_type ? null : Helpers::get_document_type_label( $invoice->document_type ),
	\__( 'Date', 'orbis-siteground' )                => null === $invoice->invoice_date ? null : \mysql2date( \get_option( 'date_format' ), $invoice->invoice_date ),
	\__( 'Subtotal', 'orbis-siteground' )            => Helpers::format_amount( $invoice->subtotal, $currency ),
	\__( 'VAT', 'orbis-siteground' )                 => Helpers::format_amount( $invoice->vat_amount, $currency ),
	\__( 'Total', 'orbis-siteground' )               => Helpers::format_amount( $invoice->total, $currency ),
	\__( 'Payment method', 'orbis-siteground' )      => $invoice->payment_method,
	\__( 'VAT scheme', 'orbis-siteground' )          => null === $invoice->tax_scheme ? null : Helpers::get_tax_scheme_label( $invoice->tax_scheme ),
	\__( 'VAT note', 'orbis-siteground' )            => $invoice_data['tax']['note'] ?? null,
	\__( 'Supplier', 'orbis-siteground' )            => $invoice_data['supplier']['name'] ?? null,
	\__( 'Customer', 'orbis-siteground' )            => $invoice_data['customer']['company'] ?? $invoice_data['customer']['name'] ?? null,
	\__( 'Customer VAT number', 'orbis-siteground' ) => $invoice_data['customer']['vat_number'] ?? null,
	\__( 'File name', 'orbis-siteground' )           => $invoice->file_name,
	\__( 'File size', 'orbis-siteground' )           => \size_format( (int) $invoice->file_size ),
	\__( 'SHA-256', 'orbis-siteground' )             => $invoice->file_sha256,
	\__( 'Uploaded', 'orbis-siteground' )            => Helpers::format_datetime( $invoice->created_at ),
	\__( 'Processed', 'orbis-siteground' )           => Helpers::format_datetime( $invoice->processed_at ),
];

$items = \array_filter(
	$items,
	fn( $value ) => null !== $value && '' !== $value && false !== $value
);

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround invoice', 'orbis-siteground' ); ?></div>
	<div class="card-body">
		<div class="content">
			<dl>
				<dt><?php \esc_html_e( 'Status', 'orbis-siteground' ); ?></dt>
				<dd>
					<?php Helpers::render_invoice_status_badge( $invoice, 'theme' ); ?>
				</dd>

				<dt><?php \esc_html_e( 'PDF', 'orbis-siteground' ); ?></dt>
				<dd>
					<?php Helpers::render_invoice_pdf_link( $invoice ); ?>
				</dd>

				<?php foreach ( $items as $label => $value ) : ?>

					<dt><?php echo \esc_html( $label ); ?></dt>
					<dd><?php echo \esc_html( (string) $value ); ?></dd>

				<?php endforeach; ?>
			</dl>
		</div>
	</div>
</div>
