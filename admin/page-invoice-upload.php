<?php
/**
 * Admin page invoice upload
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var array<int, array{status: string, message: string, invoice_id?: int}>|false $results            Upload results.
 * @var object[]                                                                   $unprocessed        Unprocessed invoices.
 * @var int                                                                        $unprocessed_total  Number of unprocessed invoices.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_classes = [
	'created'   => 'notice-success',
	'duplicate' => 'notice-warning',
	'error'     => 'notice-error',
];

?>
<div class="wrap">
	<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

	<?php if ( \is_array( $results ) ) : ?>

		<?php foreach ( $results as $result ) : ?>

			<div class="notice <?php echo \esc_attr( $notice_classes[ $result['status'] ] ?? 'notice-info' ); ?> is-dismissible">
				<p><?php echo \esc_html( $result['message'] ); ?></p>
			</div>

		<?php endforeach; ?>

	<?php endif; ?>

	<p>
		<?php \esc_html_e( 'Upload SiteGround invoice PDFs from the SiteGround Client Area (Billing → Invoices). Each PDF is stored once, a PDF that was uploaded before is skipped. An AI client reads the uploaded PDFs with the get invoice ability and registers the invoice data with the update invoice ability.', 'orbis-siteground' ); ?>
	</p>

	<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="orbis_siteground_upload_invoices" />

		<?php \wp_nonce_field( 'orbis_siteground_upload_invoices', 'orbis_siteground_upload_invoices_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="orbis_siteground_invoices"><?php \esc_html_e( 'SiteGround invoice PDFs', 'orbis-siteground' ); ?></label>
				</th>
				<td>
					<input type="file" id="orbis_siteground_invoices" name="orbis_siteground_invoices[]" accept=".pdf,application/pdf" multiple required />
				</td>
			</tr>
		</table>

		<?php \submit_button( \__( 'Upload', 'orbis-siteground' ) ); ?>
	</form>

	<h2>
		<?php

		\printf(
			/* translators: %s: number of unprocessed invoices */
			\esc_html__( 'Unprocessed invoices (%s)', 'orbis-siteground' ),
			\esc_html( \number_format_i18n( $unprocessed_total ) )
		);

		?>
	</h2>

	<?php if ( [] === $unprocessed ) : ?>

		<p><?php \esc_html_e( 'All uploaded invoices have been processed.', 'orbis-siteground' ); ?></p>

	<?php else : ?>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php \esc_html_e( 'ID', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'File name', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'File size', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Text', 'orbis-siteground' ); ?></th>
					<th><?php \esc_html_e( 'Uploaded', 'orbis-siteground' ); ?></th>
				</tr>
			</thead>
			<tbody>

				<?php foreach ( $unprocessed as $invoice ) : ?>

					<tr>
						<td><?php echo \esc_html( (string) $invoice->id ); ?></td>
						<td>
							<a href="<?php echo \esc_url( Helpers::get_invoice_pdf_url( $invoice ) ); ?>"><?php echo \esc_html( (string) $invoice->file_name ); ?></a>
						</td>
						<td><?php echo \esc_html( (string) \size_format( (int) $invoice->file_size ) ); ?></td>
						<td><?php echo null === $invoice->text ? \esc_html__( 'Not extracted', 'orbis-siteground' ) : \esc_html__( 'Extracted', 'orbis-siteground' ); ?></td>
						<td><?php echo \esc_html( Helpers::format_datetime( $invoice->created_at ) ); ?></td>
					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>

	<?php endif; ?>
</div>
