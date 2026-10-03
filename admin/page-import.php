<?php
/**
 * Admin page import
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var array<string, mixed>|false $last_import Last import.
 * @var string|false               $error       Import error.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only used to show a notice.
$is_imported = \array_key_exists( 'imported', $_GET );

?>
<div class="wrap">
	<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

	<?php if ( false !== $error ) : ?>

		<div class="notice notice-error is-dismissible">
			<p><?php echo \esc_html( $error ); ?></p>
		</div>

	<?php endif; ?>

	<?php if ( $is_imported && \is_array( $last_import ) ) : ?>

		<div class="notice notice-success is-dismissible">
			<p><?php \esc_html_e( 'The SiteGround accounts have been imported.', 'orbis-siteground' ); ?></p>
		</div>

	<?php endif; ?>

	<p>
		<?php

		\printf(
			/* translators: 1: SiteGround Client Area page, 2: API endpoint */
			\esc_html__( 'Upload the JSON response that SiteGround loads on the %1$s page (%2$s). New accounts are added, existing accounts are updated and accounts that are no longer in the file are marked as removed.', 'orbis-siteground' ),
			'<strong>' . \esc_html__( 'Services → Hosting', 'orbis-siteground' ) . '</strong>',
			'<code>https://uapi.siteground.com/v1/accounts?sort_field=expires&amp;sort_order=ASC</code>'
		);

		?>
	</p>

	<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="orbis_siteground_import" />

		<?php \wp_nonce_field( 'orbis_siteground_import', 'orbis_siteground_import_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="orbis_siteground_accounts_file"><?php \esc_html_e( 'SiteGround accounts JSON file', 'orbis-siteground' ); ?></label>
				</th>
				<td>
					<input type="file" id="orbis_siteground_accounts_file" name="orbis_siteground_accounts_file" accept=".json,application/json" required />
				</td>
			</tr>
		</table>

		<?php \submit_button( \__( 'Import', 'orbis-siteground' ) ); ?>
	</form>

	<?php if ( \is_array( $last_import ) && \is_array( $last_import['result'] ?? null ) ) : ?>

		<h2><?php \esc_html_e( 'Last import', 'orbis-siteground' ); ?></h2>

		<?php

		$import_user = \get_userdata( (int) ( $last_import['user_id'] ?? 0 ) );

		$rows = [
			\__( 'Date', 'orbis-siteground' )      => (string) \wp_date( 'D j M Y H:i', (int) $last_import['time'] ),
			\__( 'File', 'orbis-siteground' )      => (string) ( $last_import['file_name'] ?? '' ),
			\__( 'User', 'orbis-siteground' )      => false === $import_user ? '' : $import_user->display_name,
			\__( 'Accounts', 'orbis-siteground' )  => (string) $last_import['result']['total'],
			\__( 'Created', 'orbis-siteground' )   => (string) $last_import['result']['created'],
			\__( 'Updated', 'orbis-siteground' )   => (string) $last_import['result']['updated'],
			\__( 'Unchanged', 'orbis-siteground' ) => (string) $last_import['result']['unchanged'],
			\__( 'Restored', 'orbis-siteground' )  => (string) $last_import['result']['restored'],
			\__( 'Removed', 'orbis-siteground' )   => (string) $last_import['result']['removed'],
		];

		?>

		<table class="widefat striped" style="max-width: 40em;">
			<tbody>

				<?php foreach ( $rows as $label => $value ) : ?>

					<tr>
						<th scope="row"><?php echo \esc_html( $label ); ?></th>
						<td><?php echo \esc_html( $value ); ?></td>
					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>

	<?php endif; ?>
</div>
