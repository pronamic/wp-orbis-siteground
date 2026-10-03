<?php
/**
 * Orbis SiteGround
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 *
 * @wordpress-plugin
 * Plugin Name:       Orbis SiteGround
 * Plugin URI:        https://www.orbiswp.com/
 * Description:       The Orbis SiteGround plugin keeps a shadow database of SiteGround hosting accounts and websites and compares the accounts against Orbis subscriptions.
 * Version:           2.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Pronamic
 * Author URI:        https://www.pronamic.eu/
 * Text Domain:       orbis-siteground
 * Domain Path:       /languages/
 * License:           GPL-3.0-or-later
 * GitHub URI:        https://github.com/wp-orbis/wp-orbis-siteground
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function (): void {
	$autoload_path = __DIR__ . '/vendor/autoload_packages.php';

	if ( \file_exists( $autoload_path ) ) {
		require_once $autoload_path;
	}

	\register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );

	Plugin::instance( __FILE__ );
} )();
