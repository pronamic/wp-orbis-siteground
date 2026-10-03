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

		\add_action( 'orbis_before_side_content', $this->maybe_include_details( ... ) );
		\add_action( 'orbis_after_main_content', $this->maybe_include_data( ... ) );
	}

	/**
	 * Sort the account and website archives by name.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	private function pre_get_posts( WP_Query $query ): void {
		if ( \is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( [ 'orbis_sg_account', 'orbis_sg_website' ] ) ) {
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
	 * Uses the archive templates of this plugin, unless the theme has one.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	private function template_include( $template ) {
		foreach ( [ 'orbis_sg_account', 'orbis_sg_website', 'orbis_sg_invoice' ] as $post_type ) {
			if ( ! \is_post_type_archive( $post_type ) ) {
				continue;
			}

			$file = 'archive-' . $post_type . '.php';

			if ( '' !== \locate_template( $file ) ) {
				return $template;
			}

			return __DIR__ . '/../templates/' . $file;
		}

		return $template;
	}

	/**
	 * Maybe include account, website or invoice details.
	 *
	 * @return void
	 */
	private function maybe_include_details(): void {
		if ( \is_singular( 'orbis_sg_account' ) ) {
			$account = $this->plugin->accounts->get_by_post_id( (int) \get_the_ID() );

			if ( null === $account ) {
				return;
			}

			$websites = $this->plugin->websites->get_by_account_siteground_id( $account->siteground_id );

			include __DIR__ . '/../templates/account-details.php';
			include __DIR__ . '/../templates/account-websites.php';
		}

		if ( \is_singular( 'orbis_sg_website' ) ) {
			$website = $this->plugin->websites->get_by_post_id( (int) \get_the_ID() );

			if ( null === $website ) {
				return;
			}

			include __DIR__ . '/../templates/website-details.php';
		}

		if ( \is_singular( 'orbis_sg_invoice' ) ) {
			$invoice = $this->plugin->invoices->get_by_post_id( (int) \get_the_ID() );

			if ( null === $invoice ) {
				return;
			}

			include __DIR__ . '/../templates/invoice-details.php';
		}
	}

	/**
	 * Maybe include account, website or invoice data.
	 *
	 * @return void
	 */
	private function maybe_include_data(): void {
		if ( \is_singular( 'orbis_sg_account' ) ) {
			$item = $this->plugin->accounts->get_by_post_id( (int) \get_the_ID() );

			$card_heading = \__( 'SiteGround account data', 'orbis-siteground' );
		} elseif ( \is_singular( 'orbis_sg_website' ) ) {
			$item = $this->plugin->websites->get_by_post_id( (int) \get_the_ID() );

			$card_heading = \__( 'SiteGround website data', 'orbis-siteground' );
		} elseif ( \is_singular( 'orbis_sg_invoice' ) ) {
			$item = $this->plugin->invoices->get_by_post_id( (int) \get_the_ID() );

			if ( null === $item ) {
				return;
			}

			$invoice  = $item;
			$accounts = $this->plugin->accounts->get_by_names( Helpers::get_invoice_domains( $invoice ) );

			if ( null !== $invoice->processed_at ) {
				include __DIR__ . '/../templates/invoice-lines.php';
			}

			include __DIR__ . '/../templates/invoice-pdf.php';
			include __DIR__ . '/../templates/invoice-text.php';

			$card_heading = \__( 'SiteGround invoice data', 'orbis-siteground' );
		} else {
			return;
		}

		if ( null === $item ) {
			return;
		}

		include __DIR__ . '/../templates/data.php';
	}
}
