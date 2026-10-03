<?php
/**
 * SiteGround account details
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object $account SiteGround account.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$billing_cycle = null;

if ( null !== $account->billing_cycle ) {
	$billing_cycle = \sprintf(
		/* translators: %d: number of months */
		\_n( '%d month', '%d months', (int) $account->billing_cycle, 'orbis-siteground' ),
		(int) $account->billing_cycle
	);
}

$auto_renew = null;

if ( null !== $account->auto_renew ) {
	$auto_renew = '1' === (string) $account->auto_renew ? \__( 'Yes', 'orbis-siteground' ) : \__( 'No', 'orbis-siteground' );
}

$items = [
	\__( 'Plan', 'orbis-siteground' )              => $account->plan_description,
	\__( 'Plan type', 'orbis-siteground' )         => $account->plan_type,
	\__( 'Server', 'orbis-siteground' )            => $account->server_location,
	\__( 'IP address', 'orbis-siteground' )        => $account->server_ip,
	\__( 'Data center', 'orbis-siteground' )       => $account->datacenter_name,
	\__( 'Created', 'orbis-siteground' )           => Helpers::format_date( $account->siteground_created_at ),
	\__( 'Expires', 'orbis-siteground' )           => Helpers::format_date( $account->expires_at ),
	\__( 'Suspended', 'orbis-siteground' )         => Helpers::format_date( $account->suspended_at ),
	\__( 'Next billing date', 'orbis-siteground' ) => Helpers::format_date( $account->next_billing_date ),
	\__( 'Billing cycle', 'orbis-siteground' )     => $billing_cycle,
	\__( 'Auto renew', 'orbis-siteground' )        => $auto_renew,
	\__( 'SiteGround ID', 'orbis-siteground' )     => $account->siteground_id,
	\__( 'First seen', 'orbis-siteground' )        => Helpers::format_datetime( $account->first_seen_at ),
	\__( 'Last seen', 'orbis-siteground' )         => Helpers::format_datetime( $account->last_seen_at ),
	\__( 'Removed', 'orbis-siteground' )           => Helpers::format_datetime( $account->removed_at ),
];

$items = \array_filter(
	$items,
	fn( $value ) => null !== $value && '' !== $value
);

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround account', 'orbis-siteground' ); ?></div>
	<div class="card-body">
		<div class="content">
			<dl>
				<dt><?php \esc_html_e( 'Status', 'orbis-siteground' ); ?></dt>
				<dd>
					<?php Helpers::render_status_badges( $account, 'theme' ); ?>
				</dd>

				<?php foreach ( $items as $label => $value ) : ?>

					<dt><?php echo \esc_html( $label ); ?></dt>
					<dd><?php echo \esc_html( (string) $value ); ?></dd>

				<?php endforeach; ?>
			</dl>
		</div>
	</div>
</div>
