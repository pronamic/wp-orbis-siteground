<?php
/**
 * Admin controller
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Admin controller class
 *
 * The SiteGround admin menu is the menu of the account post type, with the
 * import and comparison pages added as sub pages.
 */
final class AdminController {
	/**
	 * Accounts of the posts in the current list table, keyed by post ID.
	 *
	 * @var array<int, object>|null
	 */
	private ?array $list_accounts = null;

	/**
	 * Construct.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct(
		/**
		 * Plugin.
		 */
		private readonly Plugin $plugin
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		\add_action( 'admin_menu', $this->admin_menu( ... ) );

		\add_filter( 'manage_orbis_sg_account_posts_columns', $this->posts_columns( ... ) );
		\add_action( 'manage_orbis_sg_account_posts_custom_column', $this->posts_custom_column( ... ), 10, 2 );
	}

	/**
	 * Get import page URL.
	 *
	 * @return string
	 */
	public static function get_import_url(): string {
		return \add_query_arg( 'page', 'orbis_siteground_import', \admin_url( 'edit.php?post_type=orbis_sg_account' ) );
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	private function admin_menu(): void {
		\add_submenu_page(
			'edit.php?post_type=orbis_sg_account',
			\__( 'Import SiteGround accounts', 'orbis-siteground' ),
			\__( 'Import', 'orbis-siteground' ),
			'manage_options',
			'orbis_siteground_import',
			$this->page_import( ... )
		);

		\add_submenu_page(
			'edit.php?post_type=orbis_sg_account',
			\__( 'Orbis SiteGround comparison', 'orbis-siteground' ),
			\__( 'Comparison', 'orbis-siteground' ),
			'manage_options',
			'orbis_siteground_comparison',
			$this->page_comparison( ... )
		);
	}

	/**
	 * Page import.
	 *
	 * @return void
	 */
	private function page_import(): void {
		$last_import = \get_option( 'orbis_siteground_last_import' );

		$error_key = ImportController::get_error_transient_key();

		$error = \get_transient( $error_key );

		\delete_transient( $error_key );

		include __DIR__ . '/../admin/page-import.php';
	}

	/**
	 * Page comparison.
	 *
	 * @return void
	 */
	private function page_comparison(): void {
		$orbis_subscriptions = $this->get_orbis_hosting_subscriptions();
		$siteground_accounts = [];

		foreach ( $this->plugin->accounts->get_present() as $account ) {
			$siteground_accounts[ $account->name ] = $account;
		}

		$names = \array_unique(
			\array_merge(
				\array_keys( $orbis_subscriptions ),
				\array_keys( $siteground_accounts )
			)
		);

		\sort( $names );

		include __DIR__ . '/../admin/page-comparison.php';
	}

	/**
	 * Get Orbis hosting subscriptions, keyed by subscription name.
	 *
	 * @return array<string, object[]>
	 */
	private function get_orbis_hosting_subscriptions(): array {
		global $wpdb;

		if ( ! isset( $wpdb->orbis_subscriptions, $wpdb->orbis_products ) ) {
			return [];
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					subscription.id,
					subscription.post_id,
					subscription.name,
					subscription.expiration_date,
					subscription.cancel_date
				FROM
					$wpdb->orbis_subscriptions AS subscription
						LEFT JOIN
					$wpdb->orbis_products AS product
							ON subscription.product_id = product.id
				WHERE
					subscription.expiration_date > NOW()
						AND
					( product.name LIKE %s OR product.name LIKE %s )
				;
				",
				'Hosting%',
				'Webhosting%'
			)
		);

		$subscriptions = [];

		foreach ( $results as $result ) {
			$subscriptions[ $result->name ][] = $result;
		}

		return $subscriptions;
	}

	/**
	 * Posts columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	private function posts_columns( array $columns ): array {
		$date = $columns['date'] ?? null;

		unset( $columns['date'] );

		$columns['orbis_sg_status']  = \__( 'Status', 'orbis-siteground' );
		$columns['orbis_sg_plan']    = \__( 'Plan', 'orbis-siteground' );
		$columns['orbis_sg_server']  = \__( 'Server', 'orbis-siteground' );
		$columns['orbis_sg_expires'] = \__( 'Expires', 'orbis-siteground' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Posts custom column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function posts_custom_column( string $column, int $post_id ): void {
		$account = $this->get_list_account( $post_id );

		if ( null === $account ) {
			return;
		}

		switch ( $column ) {
			case 'orbis_sg_status':
				Helpers::render_status_badges( $account );

				break;
			case 'orbis_sg_plan':
				echo \esc_html( (string) $account->plan_description );

				break;
			case 'orbis_sg_server':
				echo \esc_html( (string) ( $account->server_location ?? $account->datacenter_name ) );

				break;
			case 'orbis_sg_expires':
				echo \esc_html( Helpers::format_date( $account->expires_at ) );

				break;
		}
	}

	/**
	 * Get account for a post in the list table.
	 *
	 * Fetches the accounts of all posts in the list table with one query.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function get_list_account( int $post_id ): ?object {
		global $wp_query;

		if ( null === $this->list_accounts ) {
			$post_ids = \wp_list_pluck( $wp_query->posts ?? [], 'ID' );

			$this->list_accounts = $this->plugin->accounts->get_by_post_ids( $post_ids );
		}

		return $this->list_accounts[ $post_id ] ?? null;
	}
}
