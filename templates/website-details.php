<?php
/**
 * SiteGround website details
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object $website SiteGround website.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = [
	\__( 'CMS', 'orbis-siteground' )           => Helpers::get_cms_label( $website->cms ),
	\__( 'Server', 'orbis-siteground' )        => $website->server_location,
	\__( 'IP address', 'orbis-siteground' )    => $website->server_ip,
	\__( 'Data center', 'orbis-siteground' )   => $website->datacenter_name,
	\__( 'Created', 'orbis-siteground' )       => Helpers::format_date( $website->siteground_created_at ),
	\__( 'SiteGround ID', 'orbis-siteground' ) => $website->siteground_id,
	\__( 'First seen', 'orbis-siteground' )    => Helpers::format_datetime( $website->first_seen_at ),
	\__( 'Last seen', 'orbis-siteground' )     => Helpers::format_datetime( $website->last_seen_at ),
	\__( 'Removed', 'orbis-siteground' )       => Helpers::format_datetime( $website->removed_at ),
];

$items = \array_filter(
	$items,
	fn( $value ) => null !== $value && '' !== $value
);

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround website', 'orbis-siteground' ); ?></div>
	<div class="card-body">
		<div class="content">
			<dl>
				<dt><?php \esc_html_e( 'Status', 'orbis-siteground' ); ?></dt>
				<dd>
					<?php Helpers::render_website_status_badges( $website, 'theme' ); ?>
				</dd>

				<dt><?php \esc_html_e( 'Account', 'orbis-siteground' ); ?></dt>
				<dd>
					<?php Helpers::render_website_account_link( $website ); ?>
				</dd>

				<?php if ( null !== $website->admin_url ) : ?>

					<dt><?php \esc_html_e( 'Admin URL', 'orbis-siteground' ); ?></dt>
					<dd>
						<a href="<?php echo \esc_url( $website->admin_url ); ?>"><?php echo \esc_html( $website->admin_url ); ?></a>
					</dd>

				<?php endif; ?>

				<?php foreach ( $items as $label => $value ) : ?>

					<dt><?php echo \esc_html( $label ); ?></dt>
					<dd><?php echo \esc_html( (string) $value ); ?></dd>

				<?php endforeach; ?>
			</dl>
		</div>
	</div>
</div>
