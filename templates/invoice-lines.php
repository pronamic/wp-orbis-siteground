<?php
/**
 * SiteGround invoice lines
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object                $invoice  SiteGround invoice.
 * @var array<string, object> $accounts SiteGround accounts of the line domains, keyed by name.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$line_items = Helpers::get_invoice_line_items( $invoice );

$currency = (string) $invoice->currency;

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround invoice lines', 'orbis-siteground' ); ?></div>

	<div class="table-responsive">
		<table class="table table-striped table-condense mb-0">
			<thead>
				<tr>
					<th><?php \esc_html_e( 'Description', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Type', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Product', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Period', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Domain', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Quantity', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Unit price', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'VAT', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Total', 'orbis-siteground' ); ?></th>
				</tr>
			</thead>
			<tbody>

				<?php foreach ( $line_items as $line_item ) : ?>

					<?php

					$line_domain = (string) ( $line_item['domain'] ?? '' );
					$account     = $accounts[ $line_domain ] ?? null;

					?>

					<tr>
						<td><?php echo \esc_html( (string) ( $line_item['description'] ?? '' ) ); ?></td>
						<td><?php echo \esc_html( Helpers::get_line_type_label( $line_item['type'] ?? null ) ); ?></td>
						<td><?php echo \esc_html( (string) ( $line_item['product'] ?? '' ) ); ?></td>
						<td><?php echo \esc_html( (string) ( $line_item['period'] ?? '' ) ); ?></td>
						<td>
							<?php if ( null !== $account && null !== $account->post_id ) : ?>

								<a href="<?php echo \esc_url( (string) \get_permalink( (int) $account->post_id ) ); ?>"><?php echo \esc_html( $line_domain ); ?></a>

							<?php else : ?>

								<?php echo \esc_html( $line_domain ); ?>

							<?php endif; ?>
						</td>
						<td class="text-end"><?php echo \esc_html( \is_numeric( $line_item['quantity'] ?? null ) ? \number_format_i18n( (float) $line_item['quantity'] ) : '' ); ?></td>
						<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $line_item['unit_price'] ?? null, $currency ) ); ?></td>
						<td class="text-end text-nowrap"><?php echo \esc_html( \is_numeric( $line_item['vat_rate_percent'] ?? null ) ? $line_item['vat_rate_percent'] . '%' : '' ); ?></td>
						<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $line_item['line_total'] ?? null, $currency ) ); ?></td>
					</tr>

				<?php endforeach; ?>

			</tbody>
			<tfoot>
				<tr>
					<th colspan="8" class="text-end"><?php \esc_html_e( 'Subtotal', 'orbis-siteground' ); ?></th>
					<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $invoice->subtotal, $currency ) ); ?></td>
				</tr>
				<tr>
					<th colspan="8" class="text-end"><?php \esc_html_e( 'VAT', 'orbis-siteground' ); ?></th>
					<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $invoice->vat_amount, $currency ) ); ?></td>
				</tr>
				<tr>
					<th colspan="8" class="text-end"><?php \esc_html_e( 'Total', 'orbis-siteground' ); ?></th>
					<th class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $invoice->total, $currency ) ); ?></th>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
