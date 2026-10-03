<?php
/**
 * SiteGround invoice PDF
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object $invoice SiteGround invoice.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pdf_url = Helpers::get_invoice_pdf_url( $invoice );

?>
<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center">
		<?php \esc_html_e( 'SiteGround invoice PDF', 'orbis-siteground' ); ?>

		<a href="<?php echo \esc_url( $pdf_url ); ?>" target="_blank"><?php \esc_html_e( 'Open PDF', 'orbis-siteground' ); ?></a>
	</div>

	<div class="ratio" style="--bs-aspect-ratio: 141.42%;">
		<iframe src="<?php echo \esc_url( $pdf_url ); ?>" title="<?php \esc_attr_e( 'SiteGround invoice PDF', 'orbis-siteground' ); ?>"></iframe>
	</div>
</div>
