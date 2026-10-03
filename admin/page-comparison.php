<?php
/**
 * Admin page comparison
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var array<string, object[]> $orbis_subscriptions Orbis hosting subscriptions, keyed by name.
 * @var array<string, object>   $siteground_accounts SiteGround accounts, keyed by name.
 * @var string[]                $names               Names.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_statuses = [
	'active',
	'expiring soon',
];

?>
<div class="wrap">
	<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

	<?php if ( [] === $siteground_accounts ) : ?>

		<p>
			<?php

			\printf(
				/* translators: %s: import page link */
				\esc_html__( 'There are no SiteGround accounts yet, %s.', 'orbis-siteground' ),
				\sprintf(
					'<a href="%s">%s</a>',
					\esc_url( AdminController::get_import_url() ),
					\esc_html__( 'import SiteGround accounts', 'orbis-siteground' )
				)
			);

			?>
		</p>

	<?php else : ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php \esc_html_e( 'Name', 'orbis-siteground' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'Orbis', 'orbis-siteground' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'SiteGround', 'orbis-siteground' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'Status', 'orbis-siteground' ); ?></th>
				</tr>
			</thead>

			<tbody>

				<?php foreach ( $names as $name ) : ?>

					<?php $account = $siteground_accounts[ $name ] ?? null; ?>

					<tr>
						<td>
							<?php if ( null !== $account && null !== $account->post_id ) : ?>

								<a href="<?php echo \esc_url( (string) \get_permalink( (int) $account->post_id ) ); ?>"><?php echo \esc_html( (string) $name ); ?></a>

							<?php else : ?>

								<?php echo \esc_html( (string) $name ); ?>

							<?php endif; ?>
						</td>
						<td>
							<?php echo \esc_html( isset( $orbis_subscriptions[ $name ] ) ? '✅' : '❌' ); ?>
						</td>
						<td>
							<?php echo \esc_html( null !== $account && \in_array( $account->status, $active_statuses, true ) ? '✅' : '❌' ); ?>
						</td>
						<td>
							<?php

							if ( null !== $account ) {
								Helpers::render_status_badges( $account );
							}

							?>
						</td>
					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>

	<?php endif; ?>
</div>
