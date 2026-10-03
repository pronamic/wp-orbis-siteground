<?php
/**
 * SiteGround invoice text
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

if ( null === $invoice->text ) {
	return;
}

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround invoice PDF text', 'orbis-siteground' ); ?></div>
	<div class="card-body">
		<pre class="mb-0"><?php echo \esc_html( (string) $invoice->text ); ?></pre>
	</div>
</div>
