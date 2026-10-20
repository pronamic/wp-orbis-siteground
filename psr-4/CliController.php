<?php
/**
 * CLI controller.
 *
 * @package Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use InvalidArgumentException;
use RuntimeException;
use WP_CLI;

/**
 * CLI controller class.
 */
final readonly class CliController {
	/**
	 * Construct.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup(): void {
		if ( ! \defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'orbis siteground process-invoice', $this->process_invoice( ... ) );
		WP_CLI::add_command( 'orbis siteground process-invoices', $this->process_invoices( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-check', $this->moneybird_check( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-push', $this->moneybird_push( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-push-invoices', $this->moneybird_push_invoices( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-backfill', $this->moneybird_backfill( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-link', $this->moneybird_link( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-skip', $this->moneybird_skip( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-account-project', $this->moneybird_account_project( ... ) );
		WP_CLI::add_command( 'orbis siteground moneybird-suggest-projects', $this->moneybird_suggest_projects( ... ) );
	}

	/**
	 * Process one invoice: extract the invoice data from the PDF text with the WordPress AI client and update the invoice.
	 *
	 * Processed invoices are processed again, the extracted data replaces the registered data.
	 *
	 * ## OPTIONS
	 *
	 * <invoice-id>
	 * : Invoice ID from the SiteGround invoices table, not the WordPress post ID.
	 *
	 * [--dry-run]
	 * : Print the extracted JSON without updating the invoice.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground process-invoice 12
	 *     wp orbis siteground process-invoice 12 --dry-run
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function process_invoice( array $args, array $assoc_args ): void {
		$invoice_id = \filter_var( $args[0] ?? '', \FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

		if ( false === $invoice_id ) {
			WP_CLI::error( 'Provide a positive SiteGround invoice ID.' );
		}

		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		try {
			$invoice = $this->process( $invoice_id, $dry_run );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			WP_CLI::error( \sprintf( 'Invoice %d: %s', $invoice_id, $e->getMessage() ) );
		}

		if ( $dry_run ) {
			return;
		}

		WP_CLI::success( \sprintf( 'Invoice %d processed: %s.', $invoice_id, InvoiceService::get_title( $invoice ) ) );
	}

	/**
	 * Process the queue of unprocessed invoices with the WordPress AI client.
	 *
	 * Invoices that fail remain unprocessed and are reported, the queue continues with the next invoice.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of invoices to process.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--dry-run]
	 * : Print the extracted JSON without updating the invoices.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground process-invoices
	 *     wp orbis siteground process-invoices --limit=5
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function process_invoices( array $args, array $assoc_args ): void {
		$limit   = \max( 0, (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 ) );
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$invoice_ids = $this->plugin->invoices->get_unprocessed_ids( $limit );

		if ( [] === $invoice_ids ) {
			WP_CLI::success( 'No unprocessed invoices.' );

			return;
		}

		WP_CLI::log( \sprintf( 'Processing %d unprocessed invoice(s).', \count( $invoice_ids ) ) );

		$failed = 0;

		foreach ( $invoice_ids as $invoice_id ) {
			try {
				$invoice = $this->process( $invoice_id, $dry_run );
			} catch ( InvalidArgumentException | RuntimeException $e ) {
				++$failed;

				WP_CLI::warning( \sprintf( 'Invoice %d: %s', $invoice_id, $e->getMessage() ) );

				continue;
			}

			if ( ! $dry_run ) {
				WP_CLI::log( \sprintf( 'Invoice %d processed: %s.', $invoice_id, InvoiceService::get_title( $invoice ) ) );
			}
		}

		$processed = \count( $invoice_ids ) - $failed;

		if ( $failed > 0 ) {
			WP_CLI::error( \sprintf( '%d invoice(s) processed, %d failed.', $processed, $failed ) );
		}

		WP_CLI::success( \sprintf( '%d invoice(s) processed.', $processed ) );
	}

	/**
	 * Check the Moneybird settings and list the IDs to configure.
	 *
	 * Lists the administrations of the API token and, when an administration
	 * is set, the SiteGround contact, the purchase ledger accounts and the
	 * purchase tax rates of the administration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-check
	 *
	 * @return void
	 */
	public function moneybird_check(): void {
		$api_token         = MoneybirdService::get_setting( 'api_token' );
		$administration_id = MoneybirdService::get_setting( 'administration_id' );

		if ( '' === $api_token ) {
			WP_CLI::error( 'Set the Moneybird API token first: wp option update orbis_siteground_moneybird_api_token <token>' );
		}

		$client = new MoneybirdClient( $api_token, $administration_id );

		try {
			WP_CLI::log( 'Administrations:' );

			WP_CLI\Utils\format_items( 'table', $client->get_administrations(), [ 'id', 'name', 'currency' ] );

			if ( '' === $administration_id ) {
				WP_CLI::error( 'Set the Moneybird administration ID: wp option update orbis_siteground_moneybird_administration_id <id>' );
			}

			$contact_id = MoneybirdService::get_setting( 'contact_id' );

			if ( '' !== $contact_id ) {
				$contact = $client->get( '/contacts/' . \rawurlencode( $contact_id ) . '.json' );

				WP_CLI::log( \sprintf( 'Contact: %s (%s)', (string) ( $contact['company_name'] ?? '' ), $contact_id ) );
			}

			WP_CLI::log( 'Ledger accounts (expenses):' );

			WP_CLI\Utils\format_items(
				'table',
				\array_filter(
					$client->get( '/ledger_accounts.json' ),
					fn( $ledger_account ) => \in_array( $ledger_account['account_type'] ?? '', [ 'expenses', 'direct_costs' ], true )
				),
				[ 'id', 'account_id', 'name', 'account_type' ]
			);

			WP_CLI::log( 'Tax rates (purchase invoices):' );

			WP_CLI\Utils\format_items(
				'table',
				$client->get(
					'/tax_rates.json',
					[
						'filter' => 'tax_rate_type:purchase_invoice',
					]
				),
				[ 'id', 'name', 'percentage', 'country' ]
			);
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$missing = \array_filter(
			[ 'contact_id', 'ledger_account_id', 'tax_rate_id', 'start_date' ],
			fn( $name ) => '' === MoneybirdService::get_setting( $name )
		);

		foreach ( $missing as $name ) {
			WP_CLI::warning( \sprintf( 'The setting `orbis_siteground_moneybird_%s` is empty.', $name ) );
		}

		WP_CLI::success( 'The Moneybird API token works.' );
	}

	/**
	 * Push invoices to Moneybird as purchase invoices.
	 *
	 * Invoices that fail are reported, the command continues with the next invoice.
	 *
	 * ## OPTIONS
	 *
	 * <invoice-id>...
	 * : Invoice IDs from the SiteGround invoices table, not the WordPress post IDs.
	 *
	 * [--dry-run]
	 * : Print the purchase invoice JSON without pushing the invoices.
	 *
	 * [--force]
	 * : Also push invoices dated before the Moneybird start date.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-push 12 --dry-run
	 *     wp orbis siteground moneybird-push 12 13
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function moneybird_push( array $args, array $assoc_args ): void {
		$invoices = \array_map( $this->get_invoice_arg( ... ), $args );

		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$force   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		$failed = 0;

		foreach ( $invoices as $invoice ) {
			try {
				$moneybird_id = $this->push( $invoice, $dry_run, $force );
			} catch ( InvalidArgumentException | RuntimeException $e ) {
				++$failed;

				WP_CLI::warning( \sprintf( 'Invoice %d: %s', (int) $invoice->id, $e->getMessage() ) );

				continue;
			}

			if ( null !== $moneybird_id ) {
				WP_CLI::log( \sprintf( 'Invoice %d pushed: %s', (int) $invoice->id, $this->get_moneybird_link( $moneybird_id ) ) );
			}
		}

		if ( $dry_run ) {
			return;
		}

		$pushed = \count( $invoices ) - $failed;

		if ( $failed > 0 ) {
			WP_CLI::error( \sprintf( '%d invoice(s) pushed, %d failed.', $pushed, $failed ) );
		}

		WP_CLI::success( \sprintf( '%d invoice(s) pushed.', $pushed ) );
	}

	/**
	 * Push the processed invoices dated on or after the Moneybird start date that are not in Moneybird yet.
	 *
	 * Invoices that fail are reported, the queue continues with the next invoice.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of invoices to push.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--dry-run]
	 * : Print the purchase invoice JSON without pushing the invoices.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-push-invoices --dry-run
	 *     wp orbis siteground moneybird-push-invoices --limit=5
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function moneybird_push_invoices( array $args, array $assoc_args ): void {
		$limit   = \max( 0, (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 ) );
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$start_date = MoneybirdService::get_setting( 'start_date' );

		if ( '' === $start_date ) {
			WP_CLI::error( 'Set the Moneybird start date first: wp option update orbis_siteground_moneybird_start_date YYYY-MM-DD' );
		}

		$invoice_ids = $this->plugin->invoices->get_moneybird_queue_ids( $start_date, $limit );

		if ( [] === $invoice_ids ) {
			WP_CLI::success( 'No invoices to push to Moneybird.' );

			return;
		}

		WP_CLI::log( \sprintf( 'Pushing %d invoice(s) to Moneybird.', \count( $invoice_ids ) ) );

		$failed = 0;

		foreach ( $invoice_ids as $invoice_id ) {
			$invoice = $this->plugin->invoices->get_by_id( $invoice_id );

			if ( null === $invoice ) {
				continue;
			}

			try {
				$moneybird_id = $this->push( $invoice, $dry_run, false );
			} catch ( InvalidArgumentException | RuntimeException $e ) {
				++$failed;

				WP_CLI::warning( \sprintf( 'Invoice %d: %s', $invoice_id, $e->getMessage() ) );

				continue;
			}

			if ( null !== $moneybird_id ) {
				WP_CLI::log( \sprintf( 'Invoice %d pushed: %s', $invoice_id, $this->get_moneybird_link( $moneybird_id ) ) );
			}
		}

		$pushed = \count( $invoice_ids ) - $failed;

		if ( $failed > 0 ) {
			WP_CLI::error( \sprintf( '%d invoice(s) pushed, %d failed.', $pushed, $failed ) );
		}

		WP_CLI::success( \sprintf( '%d invoice(s) pushed.', $pushed ) );
	}

	/**
	 * Link invoices to the purchase invoices that already exist in Moneybird.
	 *
	 * Fetches all purchase invoices of the SiteGround contact and matches them
	 * on the reference and the invoice number. Invoices with a Moneybird status
	 * are left alone, so the command can be run again safely.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report the matches without linking the invoices.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-backfill --dry-run
	 *     wp orbis siteground moneybird-backfill
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function moneybird_backfill( array $args, array $assoc_args ): void {
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		try {
			$report = ( new MoneybirdService( $this->plugin ) )->backfill( $dry_run );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		foreach ( $report['linked'] as $invoice_id => $moneybird_id ) {
			WP_CLI::log( \sprintf( 'Invoice %d %s: %s', $invoice_id, $dry_run ? 'matches' : 'linked to', $this->get_moneybird_link( (string) $moneybird_id ) ) );
		}

		foreach ( $report['mismatches'] as $mismatch ) {
			WP_CLI::warning( $mismatch );
		}

		foreach ( $report['unmatched'] as $unmatched ) {
			WP_CLI::log( \sprintf( 'Moneybird purchase invoice without SiteGround invoice: %s', $unmatched ) );
		}

		foreach ( $report['open_before'] as $invoice ) {
			WP_CLI::log( \sprintf( 'Invoice %d (%s, %s) is dated before the start date and not in Moneybird.', (int) $invoice->id, (string) $invoice->invoice_number, (string) $invoice->invoice_date ) );
		}

		WP_CLI::success(
			\sprintf(
				'%d invoice(s) %s, %d already linked, %d Moneybird purchase invoice(s) without SiteGround invoice, %d invoice(s) before the start date not in Moneybird.',
				\count( $report['linked'] ),
				$dry_run ? 'to link' : 'linked',
				$report['already_linked'],
				\count( $report['unmatched'] ),
				\count( $report['open_before'] )
			)
		);
	}

	/**
	 * Link an invoice to a purchase invoice that already exists in Moneybird.
	 *
	 * ## OPTIONS
	 *
	 * <invoice-id>
	 * : Invoice ID from the SiteGround invoices table, not the WordPress post ID.
	 *
	 * <moneybird-id>
	 * : Moneybird purchase invoice ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-link 12 471234567890123456
	 *
	 * @param string[] $args Positional arguments.
	 * @return void
	 */
	public function moneybird_link( array $args ): void {
		$invoice = $this->get_invoice_arg( $args[0] ?? '' );

		$moneybird_id = (string) ( $args[1] ?? '' );

		if ( 1 !== \preg_match( '/^\d+$/', $moneybird_id ) ) {
			WP_CLI::error( 'Provide a numeric Moneybird purchase invoice ID.' );
		}

		( new MoneybirdService( $this->plugin ) )->mark( (int) $invoice->id, 'linked', $moneybird_id );

		WP_CLI::success( \sprintf( 'Invoice %d linked to Moneybird: %s', (int) $invoice->id, $this->get_moneybird_link( $moneybird_id ) ) );
	}

	/**
	 * Mark invoices as skipped, so they are never pushed to Moneybird.
	 *
	 * ## OPTIONS
	 *
	 * [<invoice-id>...]
	 * : Invoice IDs from the SiteGround invoices table, not the WordPress post IDs.
	 *
	 * [--before=<date>]
	 * : Skip all processed invoices dated before this date (YYYY-MM-DD) that are not in Moneybird.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-skip 12 13
	 *     wp orbis siteground moneybird-skip --before=2026-01-01
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function moneybird_skip( array $args, array $assoc_args ): void {
		$before = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'before', '' );

		$invoices = \array_map( $this->get_invoice_arg( ... ), $args );

		if ( '' !== $before ) {
			if ( 1 !== \preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before ) ) {
				WP_CLI::error( 'The `--before` date must be in YYYY-MM-DD format.' );
			}

			\array_push( $invoices, ...$this->plugin->invoices->get_moneybird_open_before( $before ) );
		}

		if ( [] === $invoices ) {
			WP_CLI::error( 'Provide invoice IDs or a `--before` date.' );
		}

		$service = new MoneybirdService( $this->plugin );

		$skipped = 0;

		foreach ( $invoices as $invoice ) {
			if ( null !== $invoice->moneybird_status ) {
				WP_CLI::warning( \sprintf( 'Invoice %d is already %s.', (int) $invoice->id, (string) $invoice->moneybird_status ) );

				continue;
			}

			$service->mark( (int) $invoice->id, 'skipped' );

			++$skipped;
		}

		WP_CLI::success( \sprintf( '%d invoice(s) skipped.', $skipped ) );
	}

	/**
	 * Set or clear the Moneybird project of an account.
	 *
	 * ## OPTIONS
	 *
	 * <account-id>
	 * : Account ID from the SiteGround accounts table, not the WordPress post ID.
	 *
	 * [<project-id>]
	 * : Moneybird project ID, omit to clear the project.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-account-project 5 471234567890123456
	 *     wp orbis siteground moneybird-account-project 5
	 *
	 * @param string[] $args Positional arguments.
	 * @return void
	 */
	public function moneybird_account_project( array $args ): void {
		$account_id = \filter_var( $args[0] ?? '', \FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

		$account = false === $account_id ? null : $this->plugin->accounts->get_by_id( $account_id );

		if ( null === $account ) {
			WP_CLI::error( 'Provide an existing SiteGround account ID.' );
		}

		$project_id = (string) ( $args[1] ?? '' );

		if ( '' !== $project_id && 1 !== \preg_match( '/^\d+$/', $project_id ) ) {
			WP_CLI::error( 'Provide a numeric Moneybird project ID.' );
		}

		$this->plugin->accounts->update( (int) $account->id, [ 'moneybird_project_id' => '' === $project_id ? null : $project_id ] );

		WP_CLI::success(
			'' === $project_id
				? \sprintf( 'Moneybird project of account %s cleared.', (string) $account->name )
				: \sprintf( 'Moneybird project of account %s set to %s.', (string) $account->name, $project_id )
		);
	}

	/**
	 * Suggest Moneybird projects for the present accounts without a project.
	 *
	 * Fetches the active Moneybird projects once. A project matches when its
	 * name equals the account name.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of accounts to check.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--apply]
	 * : Store the project of accounts with exactly one matching project.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orbis siteground moneybird-suggest-projects
	 *     wp orbis siteground moneybird-suggest-projects --limit=10
	 *     wp orbis siteground moneybird-suggest-projects --apply
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function moneybird_suggest_projects( array $args, array $assoc_args ): void {
		$limit = \max( 0, (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 ) );
		$apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

		$accounts = \array_values( \array_filter( $this->plugin->accounts->get_present(), fn( $account ) => null === $account->moneybird_project_id ) );

		if ( $limit > 0 ) {
			$accounts = \array_slice( $accounts, 0, $limit );
		}

		if ( [] === $accounts ) {
			WP_CLI::success( 'No accounts without Moneybird project.' );

			return;
		}

		WP_CLI::log( 'Fetching Moneybird projects…' );

		try {
			$projects = ( new MoneybirdService( $this->plugin ) )->get_projects();
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::log( \sprintf( 'Checking %d account(s) without Moneybird project against %d project(s).', \count( $accounts ), \count( $projects ) ) );

		$matched = 0;
		$applied = 0;

		foreach ( $accounts as $account ) {
			$matches = MoneybirdService::find_projects( $projects, (string) $account->name );

			$ids = \implode( ', ', \array_map( fn( $project ) => (string) $project['id'], $matches ) );

			if ( [] === $matches ) {
				WP_CLI::log( \sprintf( 'Account %d (%s): no match.', (int) $account->id, (string) $account->name ) );

				continue;
			}

			if ( \count( $matches ) > 1 ) {
				WP_CLI::warning( \sprintf( 'Account %d (%s): multiple matches: %s.', (int) $account->id, (string) $account->name, $ids ) );

				continue;
			}

			++$matched;

			if ( ! $apply ) {
				WP_CLI::log( \sprintf( 'Account %d (%s): match %s.', (int) $account->id, (string) $account->name, $ids ) );

				continue;
			}

			$this->plugin->accounts->update( (int) $account->id, [ 'moneybird_project_id' => $ids ] );

			++$applied;

			WP_CLI::log( \sprintf( 'Account %d (%s): project %s stored.', (int) $account->id, (string) $account->name, $ids ) );
		}

		WP_CLI::success( $apply ? \sprintf( '%d account project(s) stored.', $applied ) : \sprintf( '%d of %d account(s) with exactly one match.', $matched, \count( $accounts ) ) );
	}

	/**
	 * Push an invoice to Moneybird, or print the purchase invoice JSON.
	 *
	 * @param object $invoice Invoice.
	 * @param bool   $dry_run Print the purchase invoice JSON instead of pushing the invoice.
	 * @param bool   $force   Also push an invoice dated before the Moneybird start date.
	 * @return string|null Moneybird purchase invoice ID, `null` for a dry run.
	 * @throws InvalidArgumentException When the invoice cannot be pushed.
	 * @throws RuntimeException When Moneybird fails.
	 */
	private function push( object $invoice, bool $dry_run, bool $force ): ?string {
		$service = new MoneybirdService( $this->plugin );

		if ( $dry_run ) {
			WP_CLI::log( (string) \wp_json_encode( [ 'purchase_invoice' => $service->build_purchase_invoice( $invoice ) ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) );

			return null;
		}

		return $service->push( $invoice, $force );
	}

	/**
	 * Get the Moneybird URL of a document, or the document ID when no administration is set.
	 *
	 * Used at the end of messages, without trailing punctuation, so it can be selected and copied.
	 *
	 * @param string $moneybird_id Moneybird document ID.
	 * @return string
	 */
	private function get_moneybird_link( string $moneybird_id ): string {
		return MoneybirdService::get_document_url( $moneybird_id ) ?? $moneybird_id;
	}

	/**
	 * Get the invoice of an invoice ID argument, or exit with an error.
	 *
	 * @param string $arg Invoice ID argument.
	 * @return object
	 */
	private function get_invoice_arg( string $arg ): object {
		$invoice_id = \filter_var( $arg, \FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

		$invoice = false === $invoice_id ? null : $this->plugin->invoices->get_by_id( $invoice_id );

		if ( null === $invoice ) {
			WP_CLI::error( \sprintf( 'SiteGround invoice "%s" not found.', $arg ) );
		}

		return $invoice;
	}

	/**
	 * Process invoice.
	 *
	 * @param int  $invoice_id Invoice ID.
	 * @param bool $dry_run    Print the extracted JSON instead of updating the invoice.
	 * @return object
	 * @throws InvalidArgumentException When the invoice does not exist or the data is invalid.
	 */
	private function process( int $invoice_id, bool $dry_run ): object {
		$service = new InvoiceService( $this->plugin->invoices, $this->plugin->accounts, $this->plugin->invoice_lines );

		$invoice = $this->plugin->invoices->get_by_id( $invoice_id );

		if ( null === $invoice ) {
			throw new InvalidArgumentException( 'SiteGround invoice not found.' );
		}

		$data = $service->extract_data( $invoice );

		if ( $dry_run ) {
			WP_CLI::log( (string) \wp_json_encode( $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) );

			return $invoice;
		}

		return $service->update( $invoice_id, $data );
	}
}
