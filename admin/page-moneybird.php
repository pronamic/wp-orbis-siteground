<?php
/**
 * Admin page Moneybird
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

$fields = [
	'orbis_siteground_moneybird_api_token'         => [
		'label'       => \__( 'API token', 'orbis-siteground' ),
		'type'        => 'password',
		'description' => \__( 'Personal API token from Moneybird, with access to the sales and purchase documents and settings.', 'orbis-siteground' ),
	],
	'orbis_siteground_moneybird_administration_id' => [
		'label'       => \__( 'Administration ID', 'orbis-siteground' ),
		'type'        => 'text',
		'description' => '',
	],
	'orbis_siteground_moneybird_contact_id'        => [
		'label'       => \__( 'SiteGround contact ID', 'orbis-siteground' ),
		'type'        => 'text',
		'description' => \__( 'Moneybird contact of SiteGround, the supplier of the purchase invoices.', 'orbis-siteground' ),
	],
	'orbis_siteground_moneybird_ledger_account_id' => [
		'label'       => \__( 'Ledger account ID', 'orbis-siteground' ),
		'type'        => 'text',
		'description' => \__( 'Purchase ledger account of all invoice lines.', 'orbis-siteground' ),
	],
	'orbis_siteground_moneybird_tax_rate_id'       => [
		'label'       => \__( 'Tax rate ID', 'orbis-siteground' ),
		'type'        => 'text',
		'description' => \__( 'Purchase tax rate of all invoice lines.', 'orbis-siteground' ),
	],
	'orbis_siteground_moneybird_start_date'        => [
		'label'       => \__( 'Start date', 'orbis-siteground' ),
		'type'        => 'date',
		'description' => \__( 'Invoices dated before this date are not pushed to Moneybird.', 'orbis-siteground' ),
	],
];

?>
<div class="wrap">
	<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

	<p>
		<?php

		\printf(
			/* translators: %s: WP-CLI command */
			\esc_html__( 'SiteGround invoices are pushed to Moneybird as purchase invoices with WP-CLI. Run %s to check the settings and list the IDs to fill in.', 'orbis-siteground' ),
			'<code>wp orbis siteground moneybird-check</code>'
		);

		?>
	</p>

	<form method="post" action="options.php">
		<?php \settings_fields( 'orbis_siteground_moneybird' ); ?>

		<table class="form-table" role="presentation">

			<?php foreach ( $fields as $name => $field ) : ?>

				<tr>
					<th scope="row">
						<label for="<?php echo \esc_attr( $name ); ?>"><?php echo \esc_html( $field['label'] ); ?></label>
					</th>
					<td>
						<input name="<?php echo \esc_attr( $name ); ?>" id="<?php echo \esc_attr( $name ); ?>" type="<?php echo \esc_attr( $field['type'] ); ?>" value="<?php echo \esc_attr( (string) \get_option( $name, '' ) ); ?>" class="regular-text code" autocomplete="off" />

						<?php if ( '' !== $field['description'] ) : ?>

							<p class="description"><?php echo \esc_html( $field['description'] ); ?></p>

						<?php endif; ?>
					</td>
				</tr>

			<?php endforeach; ?>

		</table>

		<?php \submit_button(); ?>
	</form>
</div>
