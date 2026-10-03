<?php
/**
 * Plugin
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Plugin class
 */
final class Plugin {
	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Account repository.
	 *
	 * @var AccountRepository
	 */
	public readonly AccountRepository $accounts;

	/**
	 * Website repository.
	 *
	 * @var WebsiteRepository
	 */
	public readonly WebsiteRepository $websites;

	/**
	 * Invoice repository.
	 *
	 * @var InvoiceRepository
	 */
	public readonly InvoiceRepository $invoices;

	/**
	 * Return instance of this class.
	 *
	 * @param string $file Plugin file.
	 * @return self A single instance of this class.
	 */
	public static function instance( string $file = '' ): self {
		self::$instance ??= new self( $file );

		return self::$instance;
	}

	/**
	 * Construct.
	 *
	 * @param string $file Plugin file.
	 */
	private function __construct(
		/**
		 * Plugin file.
		 */
		public readonly string $file
	) {
		self::register_tables();

		$this->accounts = new AccountRepository();
		$this->websites = new WebsiteRepository();
		$this->invoices = new InvoiceRepository();

		\add_action( 'init', $this->maybe_install( ... ), 20 );

		( new PostTypeController() )->setup();
		( new ImportController( $this ) )->setup();
		( new TemplateController( $this ) )->setup();
		( new AbilitiesController( $this ) )->setup();

		if ( \is_admin() ) {
			( new AdminController( $this ) )->setup();
		}
	}

	/**
	 * Register custom tables on `$wpdb`.
	 *
	 * @return void
	 */
	private static function register_tables(): void {
		global $wpdb;

		$wpdb->orbis_siteground_accounts = $wpdb->prefix . 'orbis_siteground_accounts';
		$wpdb->orbis_siteground_websites = $wpdb->prefix . 'orbis_siteground_websites';
		$wpdb->orbis_siteground_invoices = $wpdb->prefix . 'orbis_siteground_invoices';
	}

	/**
	 * Activate.
	 *
	 * Removes the database version so the tables are installed and the
	 * rewrite rules are flushed on the next `init`.
	 *
	 * @return void
	 */
	public static function activate(): void {
		\delete_option( 'orbis_siteground_db_version' );
	}

	/**
	 * Maybe install.
	 *
	 * Runs after the post type is registered so the rewrite rules include it.
	 *
	 * @return void
	 */
	private function maybe_install(): void {
		if ( '2.2.0' === \get_option( 'orbis_siteground_db_version' ) ) {
			return;
		}

		$this->install();

		\flush_rewrite_rules();

		// The last import is stored per import type since version 2.1.0.
		\delete_option( 'orbis_siteground_last_import' );

		\update_option( 'orbis_siteground_db_version', '2.2.0' );
	}

	/**
	 * Install.
	 *
	 * @link https://codex.wordpress.org/Creating_Tables_with_Plugins
	 * @return void
	 */
	private function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = <<<SQL
			CREATE TABLE $wpdb->orbis_siteground_accounts (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				post_id BIGINT(20) UNSIGNED DEFAULT NULL,
				siteground_id VARCHAR(64) NOT NULL,
				name VARCHAR(191) NOT NULL,
				status VARCHAR(32) NOT NULL,
				plan_type VARCHAR(32) DEFAULT NULL,
				plan_description VARCHAR(191) DEFAULT NULL,
				server_id VARCHAR(64) DEFAULT NULL,
				server_ip VARCHAR(45) DEFAULT NULL,
				server_location VARCHAR(191) DEFAULT NULL,
				datacenter_name VARCHAR(191) DEFAULT NULL,
				siteground_created_at DATETIME DEFAULT NULL,
				expires_at DATETIME DEFAULT NULL,
				suspended_at DATETIME DEFAULT NULL,
				next_billing_date DATETIME DEFAULT NULL,
				billing_cycle SMALLINT(5) UNSIGNED DEFAULT NULL,
				auto_renew TINYINT(1) DEFAULT NULL,
				data LONGTEXT DEFAULT NULL,
				first_seen_at DATETIME NOT NULL,
				last_seen_at DATETIME NOT NULL,
				removed_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY siteground_id (siteground_id),
				UNIQUE KEY post_id (post_id),
				KEY name (name),
				KEY status (status),
				KEY expires_at (expires_at),
				KEY removed_at (removed_at)
			) $charset_collate;
			CREATE TABLE $wpdb->orbis_siteground_websites (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				post_id BIGINT(20) UNSIGNED DEFAULT NULL,
				siteground_id VARCHAR(64) NOT NULL,
				account_siteground_id VARCHAR(64) DEFAULT NULL,
				domain VARCHAR(191) NOT NULL,
				account_name VARCHAR(191) DEFAULT NULL,
				status VARCHAR(32) NOT NULL,
				account_status VARCHAR(32) DEFAULT NULL,
				account_type VARCHAR(32) DEFAULT NULL,
				cms VARCHAR(32) DEFAULT NULL,
				admin_url VARCHAR(255) DEFAULT NULL,
				server_id VARCHAR(64) DEFAULT NULL,
				server_ip VARCHAR(45) DEFAULT NULL,
				server_location VARCHAR(191) DEFAULT NULL,
				datacenter_name VARCHAR(191) DEFAULT NULL,
				siteground_created_at DATETIME DEFAULT NULL,
				suspended TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
				data LONGTEXT DEFAULT NULL,
				first_seen_at DATETIME NOT NULL,
				last_seen_at DATETIME NOT NULL,
				removed_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY siteground_id (siteground_id),
				UNIQUE KEY post_id (post_id),
				KEY account_siteground_id (account_siteground_id),
				KEY domain (domain),
				KEY status (status),
				KEY removed_at (removed_at)
			) $charset_collate;
			CREATE TABLE $wpdb->orbis_siteground_invoices (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				post_id BIGINT(20) UNSIGNED DEFAULT NULL,
				invoice_number VARCHAR(64) NOT NULL,
				document_type VARCHAR(32) NOT NULL,
				invoice_date DATE NOT NULL,
				currency CHAR(3) NOT NULL,
				payment_method VARCHAR(64) DEFAULT NULL,
				subtotal DECIMAL(12,2) NOT NULL,
				vat_amount DECIMAL(12,2) NOT NULL,
				total DECIMAL(12,2) NOT NULL,
				tax_scheme VARCHAR(32) DEFAULT NULL,
				file_path VARCHAR(255) NOT NULL,
				file_name VARCHAR(191) DEFAULT NULL,
				file_size INT(10) UNSIGNED NOT NULL,
				file_sha256 CHAR(64) NOT NULL,
				data LONGTEXT NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY invoice_number (invoice_number),
				UNIQUE KEY post_id (post_id),
				KEY invoice_date (invoice_date),
				KEY total (total)
			) $charset_collate;
			SQL;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		\dbDelta( $sql );
	}
}
