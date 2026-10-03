<?php
/**
 * SiteGround account data
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

if ( null === $account->data ) {
	return;
}

$data = \json_decode( (string) $account->data );

?>
<div class="card mb-3">
	<div class="card-header"><?php \esc_html_e( 'SiteGround data', 'orbis-siteground' ); ?></div>
	<div class="card-body">
		<pre class="mb-0"><?php echo \esc_html( (string) \wp_json_encode( $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) ); ?></pre>
	</div>
</div>
