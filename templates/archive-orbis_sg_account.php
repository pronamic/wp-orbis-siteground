<?php
/**
 * Archive SiteGround accounts
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

$siteground_accounts = Plugin::instance()->accounts->get_by_post_ids( \wp_list_pluck( $wp_query->posts, 'ID' ) );

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
						<th><?php \esc_html_e( 'Status', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Plan', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Server', 'orbis-siteground' ); ?></th>
						<th><?php \esc_html_e( 'Expires', 'orbis-siteground' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php

					while ( \have_posts() ) :
						\the_post();

						$account = $siteground_accounts[ \get_the_ID() ] ?? null;

						?>

						<tr id="post-<?php \the_ID(); ?>" <?php \post_class(); ?>>
							<td>
								<a href="<?php \the_permalink(); ?>"><?php \the_title(); ?></a>

								<?php \get_template_part( 'templates/table-cell-comments' ); ?>
							</td>

							<?php if ( null === $account ) : ?>

								<td colspan="4"></td>

							<?php else : ?>

								<td>
									<?php Helpers::render_status_badges( $account, 'theme' ); ?>
								</td>
								<td>
									<?php echo \esc_html( (string) $account->plan_description ); ?>
								</td>
								<td>
									<?php echo \esc_html( (string) ( $account->server_location ?? $account->datacenter_name ) ); ?>
								</td>
								<td>
									<?php echo \esc_html( Helpers::format_date( $account->expires_at ) ); ?>
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
