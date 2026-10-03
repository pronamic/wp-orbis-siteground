<?php
/**
 * Archive SiteGround invoices
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

$siteground_invoices = Plugin::instance()->invoices->get_by_post_ids( \wp_list_pluck( $wp_query->posts, 'ID' ) );

\get_header();

?>
<div class="card">
	<div class="card-body">
		<?php \get_template_part( 'templates/search_form' ); ?>
	</div>

	<?php if ( \have_posts() ) : ?>

		<div class="table-responsive">
			<table class="table table-striped table-condense table-hover">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Invoice', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Date', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Description', 'orbis-siteground' ); ?></th>
						<th class="text-end"><?php \esc_html_e( 'Total', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'VAT', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'PDF', 'orbis-siteground' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php

					while ( \have_posts() ) :
						\the_post();

						$invoice = $siteground_invoices[ \get_the_ID() ] ?? null;

						?>

						<tr id="post-<?php \the_ID(); ?>" <?php \post_class(); ?>>
							<td>
								<a href="<?php \the_permalink(); ?>"><?php echo \esc_html( null === $invoice ? \get_the_title() : (string) $invoice->invoice_number ); ?></a>

								<?php \get_template_part( 'templates/table-cell-comments' ); ?>
							</td>

							<?php if ( null === $invoice ) : ?>

								<td colspan="5"></td>

							<?php else : ?>

								<td>
									<?php echo \esc_html( (string) \mysql2date( \get_option( 'date_format' ), $invoice->invoice_date ) ); ?>
								</td>
								<td>
									<?php

									$line_descriptions = \wp_list_pluck( Helpers::get_invoice_line_items( $invoice ), 'description' );

									echo \wp_kses( \implode( '<br />', \array_map( esc_html( ... ), $line_descriptions ) ), [ 'br' => [] ] );

									?>
								</td>
								<td class="text-end text-nowrap">
									<?php echo \esc_html( Helpers::format_amount( $invoice->total, (string) $invoice->currency ) ); ?>
								</td>
								<td>
									<?php echo \esc_html( Helpers::get_tax_scheme_label( $invoice->tax_scheme ) ); ?>
								</td>
								<td>
									<?php Helpers::render_invoice_pdf_link( $invoice ); ?>
								</td>

							<?php endif; ?>
						</tr>

					<?php endwhile; ?>
				</tbody>
			</table>
		</div>

	<?php else : ?>

		<?php \get_template_part( 'templates/content-none' ); ?>

	<?php endif; ?>
</div>

<?php

if ( \function_exists( 'orbis_content_nav' ) ) {
	\orbis_content_nav();
} else {
	\the_posts_pagination();
}

\get_footer();
