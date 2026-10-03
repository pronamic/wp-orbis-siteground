<?php
/**
 * Admin page import
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var array<string, array<string, mixed>|false> $last_imports Last import per type.
 * @var string|false                              $error        Import error.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only used to show a notice.
$imported_type = \is_string( $_GET['imported'] ?? null ) ? \sanitize_key( \wp_unslash( $_GET['imported'] ) ) : null;

$type_labels = [
	'accounts' => \__( 'Accounts', 'orbis-siteground' ),
	'websites' => \__( 'Websites', 'orbis-siteground' ),
];

$imported_messages = [
	'accounts' => \__( 'The SiteGround accounts have been imported.', 'orbis-siteground' ),
	'websites' => \__( 'The SiteGround websites have been imported.', 'orbis-siteground' ),
];

?>
<div class="wrap">
	<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

	<?php if ( false !== $error ) : ?>

		<div class="notice notice-error is-dismissible">
			<p><?php echo \esc_html( $error ); ?></p>
		</div>

	<?php endif; ?>

	<?php if ( null !== $imported_type && \array_key_exists( $imported_type, $imported_messages ) ) : ?>

		<div class="notice notice-success is-dismissible">
			<p><?php echo \esc_html( $imported_messages[ $imported_type ] ); ?></p>
		</div>

	<?php endif; ?>

	<p>
		<?php \esc_html_e( 'Upload a JSON response from the SiteGround Client Area. The file type is detected automatically. New items are added, existing items are updated and items that are no longer in the file are marked as removed.', 'orbis-siteground' ); ?>
	</p>

	<ul class="ul-disc">
		<li>
			<?php

			\printf(
				/* translators: 1: SiteGround Client Area page, 2: API endpoint */
				\esc_html__( 'Accounts: the response that SiteGround loads on the %1$s page (%2$s).', 'orbis-siteground' ),
				'<strong>' . \esc_html__( 'Services → Hosting', 'orbis-siteground' ) . '</strong>',
				'<code>https://uapi.siteground.com/v1/accounts?sort_field=expires&amp;sort_order=ASC</code>'
			);

			?>
		</li>
		<li>
			<?php

			\printf(
				/* translators: 1: SiteGround Client Area page, 2: API endpoint */
				\esc_html__( 'Websites: the response that SiteGround loads on the %1$s page (%2$s).', 'orbis-siteground' ),
				'<strong>' . \esc_html__( 'Websites', 'orbis-siteground' ) . '</strong>',
				'<code>https://uapi.siteground.com/v1/sites/list</code>'
			);

			?>
		</li>
	</ul>

	<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="orbis_siteground_import" />

		<?php \wp_nonce_field( 'orbis_siteground_import', 'orbis_siteground_import_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="orbis_siteground_file"><?php \esc_html_e( 'SiteGround JSON file', 'orbis-siteground' ); ?></label>
				</th>
				<td>
					<input type="file" id="orbis_siteground_file" name="orbis_siteground_file" accept=".json,application/json" required />
				</td>
			</tr>
		</table>

		<?php \submit_button( \__( 'Import', 'orbis-siteground' ) ); ?>
	</form>

	<?php foreach ( $last_imports as $import_type => $last_import ) : ?>

		<?php

		if ( ! \is_array( $last_import ) || ! \is_array( $last_import['result'] ?? null ) ) {
			continue;
		}

		$import_user = \get_userdata( (int) ( $last_import['user_id'] ?? 0 ) );

		$rows = [
			\__( 'Date', 'orbis-siteground' )      => (string) \wp_date( 'D j M Y H:i', (int) $last_import['time'] ),
			\__( 'File', 'orbis-siteground' )      => (string) ( $last_import['file_name'] ?? '' ),
			\__( 'User', 'orbis-siteground' )      => false === $import_user ? '' : $import_user->display_name,
			\__( 'Total', 'orbis-siteground' )     => (string) $last_import['result']['total'],
			\__( 'Created', 'orbis-siteground' )   => (string) $last_import['result']['created'],
			\__( 'Updated', 'orbis-siteground' )   => (string) $last_import['result']['updated'],
			\__( 'Unchanged', 'orbis-siteground' ) => (string) $last_import['result']['unchanged'],
			\__( 'Restored', 'orbis-siteground' )  => (string) $last_import['result']['restored'],
			\__( 'Removed', 'orbis-siteground' )   => (string) $last_import['result']['removed'],
		];

		?>

		<h2>
			<?php

			\printf(
				/* translators: %s: import type, for example "Accounts" */
				\esc_html__( 'Last import: %s', 'orbis-siteground' ),
				\esc_html( $type_labels[ $import_type ] ?? $import_type )
			);

			?>
		</h2>

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

	<?php endforeach; ?>
</div>
