<?php
/**
 * Orbis SiteGround plugin.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2020 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Orbis\SiteGround
 */

/**
 * Title: Orbis SiteGround plugin.
 * Description:
 * Copyright: Copyright (c) 2005 - 2020
 * Company: Pronamic
 *
 * @author  Reüel van der Steege
 * @version 1.0.0
 */
class Orbis_SiteGround_Plugin extends Orbis_Plugin {
	/**
	 * Admin.
	 *
	 * @var Orbis_SiteGround_Admin
	 */
	protected $admin;

	/**
	 * Construct and initialize an Orbis SiteGround plugin.
	 *
	 * @param string $file Plugin file path.
	 * @return void
	 */
	public function __construct( $file ) {
		parent::__construct( $file );

		$this->set_name( 'orbis_siteground' );
		$this->set_db_version( '1.0.0' );

		// Admin.
		if ( is_admin() ) {
			$this->admin = new Orbis_SiteGround_Admin( $this );
		}
	}

	/**
	 * Loaded.
	 *
	 * @return void
	 */
	public function loaded() {
		$this->load_textdomain( 'orbis-siteground', '/languages/' );
	}

	/**
	 * Get Orbis subscriptions.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_orbis_subscriptions() {
		global $wpdb;

		$subscriptions = array();

		// Query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
					SELECT
						subscription.name AS subscription_name
					FROM
						$wpdb->orbis_subscriptions AS subscription
							LEFT JOIN
						$wpdb->orbis_products AS product
								ON subscription.product_id = product.id
					WHERE
						subscription.cancel_date IS NULL
							AND
						( product.name LIKE %s OR product.name LIKE %s )
					;
				",
				'Hosting%',
				'Webhosting%'
			)
		);

		// Loop subscriptions.
		foreach ( $results as $result ) {
			if ( ! isset( $subscriptions[ $result->subscription_name ] ) ) {
				$subscriptions[ $result->subscription_name ] = array();
			}

			$subscriptions[ $result->subscription_name ][] = $result;
		}

		return $subscriptions;
	}

	/**
	 * Get SiteGround hosting plan domains.
	 *
	 * @return array<string, object>
	 */
	public function get_siteground_hosting_domains() {
		// Try to get attachment ID from transient.
		$attachment_id = get_transient( 'orbis_siteground_hosting_attachment_id' );

		if ( false === $attachment_id ) {
			return array();
		}

		$path = get_attached_file( $attachment_id );

		if ( empty( $path ) ) {
			return array();
		}

		$json = file_get_contents( $path );

		$data = json_decode( $json );

		if ( ! isset( $data->data->accounts ) ) {
			return array();
		}

		// Loop accounts.
		$domains = array();

		foreach ( $data->data->accounts as $account ) {
			$domains[ $account->name ] = $account;
		}

		return $domains;
	}
}
