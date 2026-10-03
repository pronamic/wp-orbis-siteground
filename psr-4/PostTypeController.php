<?php
/**
 * Post type controller
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Post type controller class
 */
final class PostTypeController {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'init', $this->register_post_types( ... ), 0 );
	}

	/**
	 * Register post types.
	 *
	 * @return void
	 */
	private function register_post_types(): void {
		\register_post_type(
			'orbis_sg_account',
			[
				'label'         => \__( 'SiteGround accounts', 'orbis-siteground' ),
				'labels'        => [
					'name'               => \_x( 'SiteGround accounts', 'post type general name', 'orbis-siteground' ),
					'singular_name'      => \_x( 'SiteGround account', 'post type singular name', 'orbis-siteground' ),
					'all_items'          => \__( 'Accounts', 'orbis-siteground' ),
					'edit_item'          => \__( 'Edit SiteGround account', 'orbis-siteground' ),
					'view_item'          => \__( 'View SiteGround account', 'orbis-siteground' ),
					'view_items'         => \__( 'View SiteGround accounts', 'orbis-siteground' ),
					'search_items'       => \__( 'Search SiteGround accounts', 'orbis-siteground' ),
					'not_found'          => \__( 'No SiteGround accounts found', 'orbis-siteground' ),
					'not_found_in_trash' => \__( 'No SiteGround accounts found in Trash', 'orbis-siteground' ),
					'menu_name'          => \__( 'SiteGround', 'orbis-siteground' ),
				],
				'public'        => true,
				'menu_position' => 31,
				'menu_icon'     => 'dashicons-cloud',
				'show_in_rest'  => true,
				'rest_base'     => 'orbis/siteground-accounts',
				'supports'      => [
					'title',
					'comments',
				],
				'has_archive'   => 'siteground/accounts',
				'rewrite'       => [
					'slug'       => 'siteground/accounts',
					'with_front' => false,
				],
				'map_meta_cap'  => true,
				'capabilities'  => [
					// Accounts are only created by the SiteGround accounts import.
					'create_posts' => 'do_not_allow',
				],
			]
		);
	}
}
