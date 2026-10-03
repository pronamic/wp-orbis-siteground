<?php
/**
 * Archive SiteGround websites
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

$siteground_websites = Plugin::instance()->websites->get_by_post_ids( \wp_list_pluck( $wp_query->posts, 'ID' ) );

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
						<th><?php \esc_html_e( 'Name', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Account', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Status', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'CMS', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Server', 'orbis-siteground' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php

					while ( \have_posts() ) :
						\the_post();

						$website = $siteground_websites[ \get_the_ID() ] ?? null;

						?>

						<tr id="post-<?php \the_ID(); ?>" <?php \post_class(); ?>>
							<td>
								<a href="<?php \the_permalink(); ?>"><?php \the_title(); ?></a>

								<?php \get_template_part( 'templates/table-cell-comments' ); ?>
							</td>

							<?php if ( null === $website ) : ?>

								<td colspan="4"></td>

							<?php else : ?>

								<td>
									<?php Helpers::render_website_account_link( $website ); ?>
								</td>
								<td>
									<?php Helpers::render_website_status_badges( $website, 'theme' ); ?>
								</td>
								<td>
									<?php echo \esc_html( Helpers::get_cms_label( $website->cms ) ); ?>
								</td>
								<td>
									<?php echo \esc_html( (string) ( $website->server_location ?? $website->datacenter_name ) ); ?>
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
