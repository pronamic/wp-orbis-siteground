<?php
/**
 * SiteGround account websites
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object[] $websites SiteGround websites of the account.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( [] === $websites ) {
	return;
}

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround websites', 'orbis-siteground' ); ?></div>

	<ul class="list-group list-group-flush">

		<?php foreach ( $websites as $website ) : ?>

			<li class="list-group-item">
				<?php if ( null !== $website->post_id ) : ?>

					<a href="<?php echo \esc_url( (string) \get_permalink( (int) $website->post_id ) ); ?>"><?php echo \esc_html( (string) $website->domain ); ?></a>

				<?php else : ?>

					<?php echo \esc_html( (string) $website->domain ); ?>

				<?php endif; ?>

				<br />

				<small class="text-muted"><?php echo \esc_html( Helpers::get_cms_label( $website->cms ) ); ?></small>

				<?php Helpers::render_website_status_badges( $website, 'theme' ); ?>
			</li>

		<?php endforeach; ?>

	</ul>
</div>
