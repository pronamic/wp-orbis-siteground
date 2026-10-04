<?php
/**
 * SiteGround invoice lines
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object   $invoice SiteGround invoice.
 * @var object[] $lines   SiteGround invoice lines.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
					<th><?php \esc_html_e( 'Account', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Quantity', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Unit price', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'VAT', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Total', 'orbis-siteground' ); ?></th>
				</tr>
			</thead>
			<tbody>

				<?php foreach ( $lines as $line ) : ?>

					<tr>
						<td><?php echo \esc_html( (string) $line->description ); ?></td>
						<td><?php echo \esc_html( Helpers::get_line_type_label( $line->type ) ); ?></td>
						<td><?php echo \esc_html( (string) $line->product ); ?></td>
						<td>
							<?php echo \esc_html( (string) $line->period ); ?>

							<?php if ( null !== $line->start_date && null !== $line->end_date ) : ?>

								<br />
								<small class="text-muted text-nowrap">
									<?php

									echo \esc_html(
										\sprintf(
											/* translators: 1: start date, 2: end date */
											\__( '%1$s – %2$s', 'orbis-siteground' ),
											Helpers::format_day( $line->start_date ),
											Helpers::format_day( $line->end_date )
										)
									);

									?>
								</small>

							<?php endif; ?>
						</td>
						<td>
							<?php if ( null !== $line->account_post_id ) : ?>

								<a href="<?php echo \esc_url( (string) \get_permalink( (int) $line->account_post_id ) ); ?>"><?php echo \esc_html( (string) $line->account_name ); ?></a>

							<?php else : ?>

								<?php echo \esc_html( (string) $line->account_name ); ?>

							<?php endif; ?>
						</td>
						<td class="text-end"><?php echo \esc_html( null === $line->quantity ? '' : \number_format_i18n( (float) $line->quantity ) ); ?></td>
						<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $line->unit_price, $currency ) ); ?></td>
						<td class="text-end text-nowrap"><?php echo \esc_html( null === $line->vat_rate_percent ? '' : \number_format_i18n( (float) $line->vat_rate_percent ) . '%' ); ?></td>
						<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $line->line_total, $currency ) ); ?></td>
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
