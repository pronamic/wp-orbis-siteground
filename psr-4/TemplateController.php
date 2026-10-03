<?php
/**
 * Template controller
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use WP_Query;

/**
 * Template controller class
 */
final readonly class TemplateController {
	/**
	 * Construct.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct(
		/**
		 * Plugin.
		 */
		private Plugin $plugin
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'pre_get_posts', $this->pre_get_posts( ... ) );

		\add_filter( 'template_include', $this->template_include( ... ) );

		\add_action( 'orbis_before_side_content', $this->maybe_include_account_details( ... ) );
		\add_action( 'orbis_after_main_content', $this->maybe_include_account_data( ... ) );
	}

	/**
	 * Sort the accounts archive by name.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	private function pre_get_posts( WP_Query $query ): void {
		if ( \is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'orbis_sg_account' ) ) {
			return;
		}

		if ( '' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Template include.
	 *
	 * Uses the archive template of this plugin, unless the theme has one.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	private function template_include( $template ) {
		if ( ! \is_post_type_archive( 'orbis_sg_account' ) ) {
			return $template;
		}

		$file = 'archive-orbis_sg_account.php';

		if ( '' !== \locate_template( $file ) ) {
			return $template;
		}

		return __DIR__ . '/../templates/' . $file;
	}

	/**
	 * Maybe include account details.
	 *
	 * @return void
	 */
	private function maybe_include_account_details(): void {
		$account = $this->get_singular_account();

		if ( null === $account ) {
			return;
		}

		include __DIR__ . '/../templates/account-details.php';
	}

	/**
	 * Maybe include account data.
	 *
	 * @return void
	 */
	private function maybe_include_account_data(): void {
		$account = $this->get_singular_account();

		if ( null === $account ) {
			return;
		}

		include __DIR__ . '/../templates/account-data.php';
	}

	/**
	 * Get the account of the current singular account post.
	 *
	 * @return object|null
	 */
	private function get_singular_account(): ?object {
		if ( ! \is_singular( 'orbis_sg_account' ) ) {
			return null;
		}

		return $this->plugin->accounts->get_by_post_id( (int) \get_the_ID() );
	}
}
