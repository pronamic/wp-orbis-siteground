<?php
/**
 * Plugin Name: Orbis SiteGround
 * Plugin URI: https://www.orbiswp.com/
 * Description: The Orbis SiteGround plugin compares hosting packages domains against Orbis subscriptions.
 *
 * Version: 1.0.0
 * Requires at least: 5.2
 *
 * Author: Pronamic
 * Author URI: https://www.pronamic.eu/
 *
 * Text Domain: orbis-siteground
 * Domain Path: /languages/
 *
 * License: GPL-3.0-or-later
 *
 * GitHub URI: https://github.com/wp-orbis/wp-orbis-siteground
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2020 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Orbis\SiteGround
 */

/**
 * Orbis SiteGround bootstrap.
 *
 * @return void
 */
function orbis_siteground_bootstrap() {
	require_once 'classes/orbis-siteground-plugin.php';
	require_once 'classes/orbis-siteground-admin.php';

	global $orbis_siteground_plugin;

	$orbis_siteground_plugin = new Orbis_SiteGround_Plugin( __FILE__ );
}

add_action( 'orbis_bootstrap', 'orbis_siteground_bootstrap' );
