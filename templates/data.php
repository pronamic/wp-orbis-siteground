<?php
/**
 * SiteGround data
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @var object $item         SiteGround account or website.
 * @var string $card_heading Card heading.
 */

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( null === $item->data ) {
	return;
}

$data = \json_decode( (string) $item->data );

?>
<div class="card mb-3">
	<div class="card-header"><?php echo \esc_html( $card_heading ); ?></div>
	<div class="card-body">
		<pre class="mb-0"><?php echo \esc_html( (string) \wp_json_encode( $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) ); ?></pre>
	</div>
</div>
