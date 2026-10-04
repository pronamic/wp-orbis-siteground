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
	 * Invoice line repository.
	 *
	 * @var InvoiceLineRepository
	 */
	public readonly InvoiceLineRepository $invoice_lines;

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

		$this->invoice_lines = new InvoiceLineRepository();

		\add_action( 'init', $this->maybe_install( ... ), 20 );

		( new PostTypeController() )->setup();
		( new ImportController( $this ) )->setup();
		( new InvoiceUploadController( $this ) )->setup();
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

		$wpdb->orbis_siteground_invoice_lines = $wpdb->prefix . 'orbis_siteground_invoice_lines';
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
		$db_version = \get_option( 'orbis_siteground_db_version' );

		if ( '2.4.0' === $db_version ) {
			return;
		}

		$this->install();

		$this->add_foreign_keys();

		\flush_rewrite_rules();

		\update_option( 'orbis_siteground_db_version', '2.4.0' );
	}

	/**
	 * Add foreign keys.
	 *
	 * `dbDelta` does not support foreign keys, so they are added separately
	 * when they do not exist yet. References that would violate a foreign
	 * key are cleaned up first.
	 *
	 * @return void
	 */
	private function add_foreign_keys(): void {
		global $wpdb;

		$foreign_keys = [
			[
				'name'      => $wpdb->prefix . 'orbis_sg_accounts_post_id',
				'table'     => $wpdb->orbis_siteground_accounts,
				'column'    => 'post_id',
				'reference' => "$wpdb->posts ( ID )",
				'on_delete' => 'SET NULL',
				'cleanup'   => "UPDATE $wpdb->orbis_siteground_accounts SET post_id = NULL WHERE post_id IS NOT NULL AND post_id NOT IN ( SELECT ID FROM $wpdb->posts );",
			],
			[
				'name'      => $wpdb->prefix . 'orbis_sg_websites_post_id',
				'table'     => $wpdb->orbis_siteground_websites,
				'column'    => 'post_id',
				'reference' => "$wpdb->posts ( ID )",
				'on_delete' => 'SET NULL',
				'cleanup'   => "UPDATE $wpdb->orbis_siteground_websites SET post_id = NULL WHERE post_id IS NOT NULL AND post_id NOT IN ( SELECT ID FROM $wpdb->posts );",
			],
			[
				'name'      => $wpdb->prefix . 'orbis_sg_invoices_post_id',
				'table'     => $wpdb->orbis_siteground_invoices,
				'column'    => 'post_id',
				'reference' => "$wpdb->posts ( ID )",
				'on_delete' => 'SET NULL',
				'cleanup'   => "UPDATE $wpdb->orbis_siteground_invoices SET post_id = NULL WHERE post_id IS NOT NULL AND post_id NOT IN ( SELECT ID FROM $wpdb->posts );",
			],
			[
				'name'      => $wpdb->prefix . 'orbis_sg_invoice_lines_invoice_id',
				'table'     => $wpdb->orbis_siteground_invoice_lines,
				'column'    => 'invoice_id',
				'reference' => "$wpdb->orbis_siteground_invoices ( id )",
				'on_delete' => 'CASCADE',
				'cleanup'   => "DELETE FROM $wpdb->orbis_siteground_invoice_lines WHERE invoice_id NOT IN ( SELECT id FROM $wpdb->orbis_siteground_invoices );",
			],
			[
				'name'      => $wpdb->prefix . 'orbis_sg_invoice_lines_account_id',
				'table'     => $wpdb->orbis_siteground_invoice_lines,
				'column'    => 'account_id',
				'reference' => "$wpdb->orbis_siteground_accounts ( id )",
				'on_delete' => 'SET NULL',
				'cleanup'   => "UPDATE $wpdb->orbis_siteground_invoice_lines SET account_id = NULL WHERE account_id IS NOT NULL AND account_id NOT IN ( SELECT id FROM $wpdb->orbis_siteground_accounts );",
			],
		];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- `dbDelta` does not support foreign keys, the queries are built from table names only.
		foreach ( $foreign_keys as $foreign_key ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s AND CONSTRAINT_TYPE = 'FOREIGN KEY';",
					$foreign_key['table'],
					$foreign_key['name']
				)
			);

			if ( null !== $exists ) {
				continue;
			}

			$wpdb->query( $foreign_key['cleanup'] );

			$wpdb->query(
				\sprintf(
					'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY ( %s ) REFERENCES %s ON DELETE %s;',
					$foreign_key['table'],
					$foreign_key['name'],
					$foreign_key['column'],
					$foreign_key['reference'],
					$foreign_key['on_delete']
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
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
				user_id BIGINT(20) UNSIGNED DEFAULT NULL,
				file_sha256 CHAR(64) NOT NULL,
				file_path VARCHAR(255) NOT NULL,
				file_name VARCHAR(191) NOT NULL,
				file_size INT(10) UNSIGNED NOT NULL,
				text LONGTEXT DEFAULT NULL,
				invoice_number VARCHAR(64) DEFAULT NULL,
				document_type VARCHAR(32) DEFAULT NULL,
				invoice_date DATE DEFAULT NULL,
				currency CHAR(3) DEFAULT NULL,
				payment_method VARCHAR(64) DEFAULT NULL,
				subtotal DECIMAL(12,2) DEFAULT NULL,
				vat_amount DECIMAL(12,2) DEFAULT NULL,
				total DECIMAL(12,2) DEFAULT NULL,
				tax_scheme VARCHAR(32) DEFAULT NULL,
				data LONGTEXT DEFAULT NULL,
				processed_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY file_sha256 (file_sha256),
				UNIQUE KEY invoice_number (invoice_number),
				UNIQUE KEY post_id (post_id),
				KEY invoice_date (invoice_date),
				KEY processed_at (processed_at)
			) $charset_collate;
			CREATE TABLE $wpdb->orbis_siteground_invoice_lines (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				invoice_id BIGINT(20) UNSIGNED NOT NULL,
				line_number SMALLINT(5) UNSIGNED NOT NULL,
				account_id BIGINT(20) UNSIGNED DEFAULT NULL,
				account_name VARCHAR(191) DEFAULT NULL,
				description VARCHAR(255) NOT NULL,
				type VARCHAR(32) DEFAULT NULL,
				product VARCHAR(191) DEFAULT NULL,
				period VARCHAR(32) DEFAULT NULL,
				period_months SMALLINT(5) UNSIGNED DEFAULT NULL,
				quantity DECIMAL(12,2) DEFAULT NULL,
				vat_rate_percent DECIMAL(5,2) DEFAULT NULL,
				unit_price DECIMAL(12,2) DEFAULT NULL,
				line_total DECIMAL(12,2) DEFAULT NULL,
				start_date DATE DEFAULT NULL,
				end_date DATE DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY invoice_line (invoice_id,line_number),
				KEY account_id (account_id),
				KEY account_name (account_name),
				KEY start_date (start_date),
				KEY end_date (end_date)
			) $charset_collate;
			SQL;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		\dbDelta( $sql );
	}
}
