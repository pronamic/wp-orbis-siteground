<?php
/**
 * SiteGround account invoices
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object[] $lines SiteGround invoice lines of the account.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( [] === $lines ) {
	return;
}

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround invoices', 'orbis-siteground' ); ?></div>

	<div class="table-responsive">
		<table class="table table-striped table-condense mb-0">
			<thead>
				<tr>
					<th><?php \esc_html_e( 'Invoice', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Date', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Description', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Period', 'orbis-siteground' ); ?></th>
					<th class="text-end"><?php \esc_html_e( 'Total', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'PDF', 'orbis-siteground' ); ?></th>
				</tr>
			</thead>
			<tbody>

				<?php foreach ( $lines as $line ) : ?>

					<tr>
						<td class="text-nowrap">
							<?php if ( null !== $line->invoice_post_id ) : ?>

								<a href="<?php echo \esc_url( (string) \get_permalink( (int) $line->invoice_post_id ) ); ?>"><?php echo \esc_html( (string) $line->invoice_number ); ?></a>

							<?php else : ?>

								<?php echo \esc_html( (string) $line->invoice_number ); ?>

							<?php endif; ?>
						</td>
						<td class="text-nowrap"><?php echo \esc_html( Helpers::format_day( $line->invoice_date ) ); ?></td>
						<td><?php echo \esc_html( (string) $line->description ); ?></td>
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
						<td class="text-end text-nowrap"><?php echo \esc_html( Helpers::format_amount( $line->line_total, (string) $line->currency ) ); ?></td>
						<td>
							<?php Helpers::render_invoice_pdf_link( (object) [ 'id' => $line->invoice_id ] ); ?>
						</td>
					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>
	</div>
</div>
