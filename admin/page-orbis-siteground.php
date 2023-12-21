<?php
/**
 * Orbis SiteGround admin page.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2020 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Orbis\SiteGround
 */

if ( ! isset( $plugin ) ) :
	return;
endif;

?>
<div class="wrap">
	<h2>
		<?php echo esc_html( get_admin_page_title() ); ?>
	</h2>

	<p>
		<form method="post">
			<input id="siteground-data-select-button" type="button" class="button" value="<?php echo esc_attr( __( 'Upload SiteGround data', 'orbis-siteground' ) )?>" />
			<input type="hidden" name="orbis_siteground_hosting_attachment_id" />
			<?php wp_nonce_field( 'orbis_save_siteground_hosting_attachment', 'orbis_siteground_hosting_nonce' ); ?>
		</form>
	</p>

	<script>
	jQuery( document ).ready( function( $ ) {
		var mediaUploader;

		$( '#siteground-data-select-button' ).click( function( e ) {
			e.preventDefault();

			if ( mediaUploader ) {
				mediaUploader.open();

				return;
			}

			mediaUploader = wp.media.frames.file_frame = wp.media( {
				title: '<?php _e( 'Select SiteGround `services-hosting-accounts.json` file', 'orbis-siteground' ); ?>',
				button: {
					text: '<?php _e( 'Select file', 'orbis-siteground' ); ?>'
				},
				multiple: false
			} );

			mediaUploader.on( 'select', function () {
				attachment = mediaUploader.state().get( 'selection' ).first().toJSON();

				$( 'input[name="orbis_siteground_hosting_attachment_id"]' ).val( attachment.id ).parents( 'form' ).submit();
			} );

			mediaUploader.open();
		});
	});
	</script>

	<?php

	$orbis_subscriptions = $plugin->get_orbis_subscriptions();
	$siteground_domains  = $plugin->get_siteground_hosting_domains();

	// Sites.
	$sites = array_unique(
		array_merge(
			array_keys( $orbis_subscriptions ),
			array_keys( $siteground_domains )
		)
	);

	if ( ! empty( $siteground_domains ) ) :
		?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'orbis-siteground' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Orbis', 'orbis-siteground' ); ?></th>
					<th scope="col"><?php esc_html_e( 'SiteGround', 'orbis-siteground' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'orbis-siteground' ); ?></th>
				</tr>
			</thead>

			<tbody>

				<?php foreach ( $sites as $name ) : ?>

					<tr>
						<td>
							<?php echo esc_html( (string) $name ); ?>
						</td>
						<td>
							<?php

							echo esc_html( isset( $orbis_subscriptions[ $name ] ) ? '✅' : '❌' );

							?>
						</td>
						<td>
							<?php

							$item = isset( $siteground_domains[ $name ] ) ? $siteground_domains[ $name ] : null;

							$active_statuses = array(
								'active',
								'expiring soon',
							);

							echo esc_html( null !== $item && in_array( $item->status, $active_statuses, true ) ? '✅' : '❌' );

							?>
						</td>
						<td>
							<?php

							if ( isset( $siteground_domains[ $name ] ) ) :
								$item = $siteground_domains[ $name ];

								printf(
									'<code>%s</code>',
									esc_html( $item->status )
								);

							endif;

							?>
						</td>
					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>

	<?php endif; ?>
</div>
