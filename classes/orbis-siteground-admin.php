<?php
/**
 * Orbis SiteGround admin.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2020 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Orbis\SiteGround
 */

/**
 * Title: Orbis SiteGround admin.
 * Description:
 * Copyright: Copyright (c) 2005 - 2020
 * Company: Pronamic
 *
 * @author Reüel van der Steege
 * @version 1.0.0
 */
class Orbis_SiteGround_Admin {
	/**
	 * Plugin
	 *
	 * @var Orbis_SiteGround_Plugin
	 */
	private $plugin;

	/**
	 * Constructs and initialize an Orbis SiteGround admin.
	 *
	 * @param Orbis_SiteGround_Plugin $plugin Plugin.
	 * @return void
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Actions.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_siteground_hosting_attachment' ) );

		// Filters.
		add_filter( 'upload_mimes', array( $this, 'upload_mime_json' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'upload_check_filetype_and_ext' ), 10, 5 );
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=orbis_subscription',
			__( 'Orbis SiteGround', 'orbis-siteground' ),
			__( 'SiteGround', 'orbis-siteground' ),
			'manage_options',
			'orbis_siteground',
			array( $this, 'page_orbis_siteground' )
		);
	}

	/**
	 * Admin enqueue scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_media();
	}

	/**
	 * Allow JSON file uploads.
	 *
	 * @param array $mimes MIME types.
	 *
	 * @return array<string, string>
	 */
	public function upload_mime_json( $mimes ) {
		$mimes['json'] = 'application/json';

		return $mimes;
	}

	/**
	 * Check filetype and extension on upload.
	 *
	 * @return array<string, string>
	 */
	function upload_check_filetype_and_ext( $check_filetype_ext, $file, $filename, $mimes, $real_mime ) {
		if ( 'text/plain' !== $real_mime ) {
			return $check_filetype_ext;
		}

		$upload_mimes = apply_filters( 'upload_mimes', array() );

		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		if ( array_key_exists( $extension, $upload_mimes ) ) {
			$check_filetype_ext['ext']             = $extension;
			$check_filetype_ext['type']            = $upload_mimes[ $extension ];
			$check_filetype_ext['proper_filename'] = $filename;
		}

		return $check_filetype_ext;
	}

	/**
	 * Maybe save SiteGround hosting attachment.
	 *
	 * @return void
	 */
	public function maybe_save_siteground_hosting_attachment() {
		if ( ! filter_has_var( \INPUT_POST, 'orbis_siteground_hosting_attachment_id' ) ) {
			return;
		}

		// Verify nonce.
		$nonce = filter_input( \INPUT_POST, 'orbis_siteground_hosting_nonce', \FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $nonce, 'orbis_save_siteground_hosting_attachment' ) ) {
			return;
		}

		// Update attachment ID transient.
		$attachment_id = filter_input( \INPUT_POST, 'orbis_siteground_hosting_attachment_id' );

		set_transient( 'orbis_siteground_hosting_attachment_id', $attachment_id, MONTH_IN_SECONDS );

		// Redirect.
		$url = add_query_arg(
			array(
				'post_type' => 'orbis_subscription',
				'page'      => 'orbis_siteground',
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $url );
	}

	/**
	 * Page Orbis SiteGround.
	 *
	 * @return void
	 */
	public function page_orbis_siteground() {
		$plugin = $this->plugin;

		include plugin_dir_path( $this->plugin->file ) . 'admin/page-orbis-siteground.php';
	}
}
