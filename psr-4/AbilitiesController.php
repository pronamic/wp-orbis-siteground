<?php
/**
 * Abilities controller
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use InvalidArgumentException;
use RuntimeException;
use WP_Error;

/**
 * Abilities controller class
 *
 * @link https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/
 */
final readonly class AbilitiesController {
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
		\add_action( 'wp_abilities_api_categories_init', $this->register_ability_categories( ... ) );
		\add_action( 'wp_abilities_api_init', $this->register_abilities( ... ) );

		\add_filter( 'orbis_mcp_server_tools', $this->mcp_server_tools( ... ) );
	}

	/**
	 * Add abilities to the Orbis MCP server tools.
	 *
	 * @link https://github.com/pronamic/orbis-mcp-server
	 * @param string[] $tools Ability names.
	 * @return string[]
	 */
	private function mcp_server_tools( $tools ) {
		$tools[] = 'orbis-siteground/search-accounts';
		$tools[] = 'orbis-siteground/search-websites';
		$tools[] = 'orbis-siteground/search-invoices';
		$tools[] = 'orbis-siteground/upload-invoice';

		return $tools;
	}

	/**
	 * Register ability categories.
	 *
	 * @return void
	 */
	private function register_ability_categories(): void {
		\wp_register_ability_category(
			'orbis-siteground',
			[
				'label'       => \__( 'Orbis SiteGround', 'orbis-siteground' ),
				'description' => \__( 'Abilities for working with SiteGround hosting accounts, websites and invoices in Orbis.', 'orbis-siteground' ),
			]
		);
	}

	/**
	 * Register abilities.
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		$nullable_string = [
			'type' => [ 'string', 'null' ],
		];

		$link = [
			'type'       => 'object',
			'properties' => [
				'href'  => [ 'type' => 'string' ],
				'title' => [ 'type' => 'string' ],
			],
		];

		$account_links = [
			'type'        => 'object',
			'description' => \__( 'HAL links of the account. Each link has an href and a title that describes what the link is for.', 'orbis-siteground' ),
			'properties'  => [
				'self'               => $link,
				'siteground-hosting' => $link,
			],
		];

		$website_links = [
			'type'        => 'object',
			'description' => \__( 'HAL links of the website. Each link has an href and a title that describes what the link is for.', 'orbis-siteground' ),
			'properties'  => [
				'self'                  => $link,
				'siteground-website'    => $link,
				'siteground-site-tools' => $link,
				'admin'                 => $link,
			],
		];

		\wp_register_ability(
			'orbis-siteground/search-accounts',
			[
				'label'               => \__( 'Search SiteGround accounts', 'orbis-siteground' ),
				'description'         => \__( 'Searches the SiteGround hosting accounts (one account per website/domain) that are imported into Orbis and returns their status, plan, server and expiration and billing dates. Accounts that are no longer present at SiteGround are excluded unless include_removed is true. Dates are in UTC. The _links of each account point to the Orbis page and to the hosting account in the SiteGround Client Area (my.siteground.com).', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'search'          => [
							'type'        => 'string',
							'description' => \__( 'Search term, matched against the account name (domain name), the plan description, the server hostname, the server IP address and the data center.', 'orbis-siteground' ),
						],
						'status'          => [
							'type'        => 'string',
							'description' => \__( 'Limit the results to accounts with this SiteGround status.', 'orbis-siteground' ),
							'enum'        => [ 'any', 'active', 'expiring soon', 'expired' ],
							'default'     => 'any',
						],
						'plan_type'       => [
							'type'        => 'string',
							'description' => \__( 'Limit the results to accounts with this SiteGround plan type, for example shared (StartUp), shared_plus (GrowBig), shared_geek (GoGeek) or cloud_lxc (Cloud).', 'orbis-siteground' ),
						],
						'include_removed' => [
							'type'        => 'boolean',
							'description' => \__( 'Also return accounts that were not present in the latest SiteGround import.', 'orbis-siteground' ),
							'default'     => false,
						],
						'expires_before'  => [
							'type'        => 'string',
							'format'      => 'date',
							'description' => \__( 'Only return accounts that expire before this date (YYYY-MM-DD).', 'orbis-siteground' ),
						],
						'expires_after'   => [
							'type'        => 'string',
							'format'      => 'date',
							'description' => \__( 'Only return accounts that expire on or after this date (YYYY-MM-DD).', 'orbis-siteground' ),
						],
						'per_page'        => [
							'type'        => 'integer',
							'description' => \__( 'Maximum number of accounts to return.', 'orbis-siteground' ),
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
						],
						'page'            => [
							'type'        => 'integer',
							'description' => \__( 'Page of results to return.', 'orbis-siteground' ),
							'minimum'     => 1,
							'default'     => 1,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'total'    => [ 'type' => 'integer' ],
						'page'     => [ 'type' => 'integer' ],
						'per_page' => [ 'type' => 'integer' ],
						'accounts' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'_links'            => $account_links,
									'id'                => [ 'type' => 'integer' ],
									'post_id'           => [ 'type' => [ 'integer', 'null' ] ],
									'siteground_id'     => [ 'type' => 'string' ],
									'name'              => [ 'type' => 'string' ],
									'status'            => [ 'type' => 'string' ],
									'plan_type'         => $nullable_string,
									'plan_description'  => $nullable_string,
									'server'            => [
										'type'       => [ 'object', 'null' ],
										'properties' => [
											'ip'       => $nullable_string,
											'location' => $nullable_string,
										],
									],
									'datacenter'        => $nullable_string,
									'created'           => $nullable_string,
									'expires'           => $nullable_string,
									'suspended'         => $nullable_string,
									'next_billing_date' => $nullable_string,
									'billing_cycle'     => [ 'type' => [ 'integer', 'null' ] ],
									'auto_renew'        => [ 'type' => [ 'boolean', 'null' ] ],
									'first_seen_at'     => [ 'type' => 'string' ],
									'last_seen_at'      => [ 'type' => 'string' ],
									'removed_at'        => $nullable_string,
								],
							],
						],
					],
				],
				'execute_callback'    => $this->search_accounts( ... ),
				'permission_callback' => fn() => \current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		\wp_register_ability(
			'orbis-siteground/search-websites',
			[
				'label'               => \__( 'Search SiteGround websites', 'orbis-siteground' ),
				'description'         => \__( 'Searches the SiteGround websites that are imported into Orbis and returns their domain, SiteGround account, status, CMS and server. One SiteGround account can host several websites. Websites that are no longer present at SiteGround are excluded unless include_removed is true. Dates are in UTC. SiteGround has two environments: the Client Area (my.siteground.com) for the SiteGround account, services, billing and websites, and Site Tools (tools.siteground.com) for managing a single website (files, databases, email, DNS, SSL, caching, backups and WordPress). The _links of each website point to the Orbis page, the website in the SiteGround Client Area, the Site Tools and the CMS admin.', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'search'          => [
							'type'        => 'string',
							'description' => \__( 'Search term, matched against the domain name, the account name, the server hostname, the server IP address and the data center.', 'orbis-siteground' ),
						],
						'status'          => [
							'type'        => 'string',
							'description' => \__( 'Limit the results to websites with this SiteGround status.', 'orbis-siteground' ),
							'enum'        => [ 'any', 'active', 'offline_mode', 'account_expired' ],
							'default'     => 'any',
						],
						'cms'             => [
							'type'        => 'string',
							'description' => \__( 'Limit the results to websites with this CMS, for example wordpress, woocommerce or general.', 'orbis-siteground' ),
						],
						'account_id'      => [
							'type'        => 'string',
							'description' => \__( 'Limit the results to the websites of the SiteGround account with this SiteGround ID (siteground_id of the search-accounts ability).', 'orbis-siteground' ),
						],
						'include_removed' => [
							'type'        => 'boolean',
							'description' => \__( 'Also return websites that were not present in the latest SiteGround import.', 'orbis-siteground' ),
							'default'     => false,
						],
						'per_page'        => [
							'type'        => 'integer',
							'description' => \__( 'Maximum number of websites to return.', 'orbis-siteground' ),
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
						],
						'page'            => [
							'type'        => 'integer',
							'description' => \__( 'Page of results to return.', 'orbis-siteground' ),
							'minimum'     => 1,
							'default'     => 1,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'total'    => [ 'type' => 'integer' ],
						'page'     => [ 'type' => 'integer' ],
						'per_page' => [ 'type' => 'integer' ],
						'websites' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'_links'        => $website_links,
									'id'            => [ 'type' => 'integer' ],
									'post_id'       => [ 'type' => [ 'integer', 'null' ] ],
									'siteground_id' => [ 'type' => 'string' ],
									'domain'        => [ 'type' => 'string' ],
									'account'       => [
										'type'       => 'object',
										'properties' => [
											'_links'    => $account_links,
											'siteground_id' => $nullable_string,
											'name'      => $nullable_string,
											'post_id'   => [ 'type' => [ 'integer', 'null' ] ],
											'status'    => $nullable_string,
											'plan_type' => $nullable_string,
										],
									],
									'status'        => [ 'type' => 'string' ],
									'cms'           => $nullable_string,
									'server'        => [
										'type'       => [ 'object', 'null' ],
										'properties' => [
											'ip'       => $nullable_string,
											'location' => $nullable_string,
										],
									],
									'datacenter'    => $nullable_string,
									'created'       => $nullable_string,
									'suspended'     => [ 'type' => 'boolean' ],
									'first_seen_at' => [ 'type' => 'string' ],
									'last_seen_at'  => [ 'type' => 'string' ],
									'removed_at'    => $nullable_string,
								],
							],
						],
					],
				],
				'execute_callback'    => $this->search_websites( ... ),
				'permission_callback' => fn() => \current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		$invoice_links = [
			'type'        => 'object',
			'description' => \__( 'HAL links of the invoice. Each link has an href and a title that describes what the link is for.', 'orbis-siteground' ),
			'properties'  => [
				'self' => $link,
				'pdf'  => $link,
			],
		];

		$invoice_schema = JsonSchema::load( 'siteground-invoice' );

		\wp_register_ability(
			'orbis-siteground/upload-invoice',
			[
				'label'               => \__( 'Upload SiteGround invoice', 'orbis-siteground' ),
				'description'         => \__( 'Uploads a SiteGround invoice PDF together with the structured invoice data to Orbis. SiteGround only provides invoices as PDF: first read the PDF and extract the data exactly according to the invoice schema (copy values literally, use null for values that are not on the PDF and never guess), then pass the data as invoice and the original PDF file as base64 encoded pdf. The PDF is stored in a protected directory. Uploading an invoice with an existing invoice number replaces the stored data and PDF of that invoice.', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'invoice'   => $invoice_schema,
						'pdf'       => [
							'type'        => 'string',
							'description' => \__( 'The original invoice PDF file, base64 encoded (maximum 10 MB).', 'orbis-siteground' ),
							'minLength'   => 1,
						],
						'file_name' => [
							'type'        => 'string',
							'description' => \__( 'Original file name of the PDF, for example invoice-4869562.pdf.', 'orbis-siteground' ),
						],
					],
					'required'             => [ 'invoice', 'pdf' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'_links'         => $invoice_links,
						'id'             => [ 'type' => 'integer' ],
						'post_id'        => [ 'type' => [ 'integer', 'null' ] ],
						'created'        => [
							'type'        => 'boolean',
							'description' => \__( 'True when the invoice is new, false when an existing invoice was replaced.', 'orbis-siteground' ),
						],
						'invoice_number' => [ 'type' => 'string' ],
						'invoice_date'   => [ 'type' => 'string' ],
						'currency'       => [ 'type' => 'string' ],
						'total'          => [ 'type' => 'number' ],
					],
				],
				'execute_callback'    => $this->upload_invoice( ... ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		\wp_register_ability(
			'orbis-siteground/search-invoices',
			[
				'label'               => \__( 'Search SiteGround invoices', 'orbis-siteground' ),
				'description'         => \__( 'Searches the SiteGround invoices that are uploaded to Orbis, newest first, and returns the invoice totals and lines. Amounts are decimal numbers in the currency of the invoice. The _links of each invoice point to the Orbis page and the original PDF.', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'search'      => [
							'type'        => 'string',
							'description' => \__( 'Search term, matched against the invoice number and all invoice data, for example a domain name or product of an invoice line.', 'orbis-siteground' ),
						],
						'date_after'  => [
							'type'        => 'string',
							'format'      => 'date',
							'description' => \__( 'Only return invoices dated on or after this date (YYYY-MM-DD).', 'orbis-siteground' ),
						],
						'date_before' => [
							'type'        => 'string',
							'format'      => 'date',
							'description' => \__( 'Only return invoices dated before this date (YYYY-MM-DD).', 'orbis-siteground' ),
						],
						'per_page'    => [
							'type'        => 'integer',
							'description' => \__( 'Maximum number of invoices to return.', 'orbis-siteground' ),
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
						],
						'page'        => [
							'type'        => 'integer',
							'description' => \__( 'Page of results to return.', 'orbis-siteground' ),
							'minimum'     => 1,
							'default'     => 1,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'total'    => [ 'type' => 'integer' ],
						'page'     => [ 'type' => 'integer' ],
						'per_page' => [ 'type' => 'integer' ],
						'invoices' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'_links'         => $invoice_links,
									'id'             => [ 'type' => 'integer' ],
									'post_id'        => [ 'type' => [ 'integer', 'null' ] ],
									'invoice_number' => [ 'type' => 'string' ],
									'document_type'  => [ 'type' => 'string' ],
									'invoice_date'   => [ 'type' => 'string' ],
									'currency'       => [ 'type' => 'string' ],
									'payment_method' => $nullable_string,
									'subtotal'       => [ 'type' => 'number' ],
									'vat_amount'     => [ 'type' => 'number' ],
									'total'          => [ 'type' => 'number' ],
									'tax_scheme'     => $nullable_string,
									'line_items'     => $invoice_schema['properties']['line_items'] ?? [ 'type' => 'array' ],
								],
							],
						],
					],
				],
				'execute_callback'    => $this->search_invoices( ... ),
				'permission_callback' => fn() => \current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Search accounts.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	private function search_accounts( $input = [] ): array {
		$result = $this->plugin->accounts->search( (array) $input );

		$result['accounts'] = \array_map( $this->format_account( ... ), $result['accounts'] );

		return $result;
	}

	/**
	 * Format account row for ability output.
	 *
	 * @param object $row Database row.
	 * @return array<string, mixed>
	 */
	private function format_account( object $row ): array {
		$server = null;

		if ( null !== $row->server_ip || null !== $row->server_location ) {
			$server = [
				'ip'       => $row->server_ip,
				'location' => $row->server_location,
			];
		}

		$post_id = null === $row->post_id ? null : (int) $row->post_id;

		return [
			'_links'            => $this->get_account_links( $row->siteground_id, $post_id ),
			'id'                => (int) $row->id,
			'post_id'           => $post_id,
			'siteground_id'     => $row->siteground_id,
			'name'              => $row->name,
			'status'            => $row->status,
			'plan_type'         => $row->plan_type,
			'plan_description'  => $row->plan_description,
			'server'            => $server,
			'datacenter'        => $row->datacenter_name,
			'created'           => $row->siteground_created_at,
			'expires'           => $row->expires_at,
			'suspended'         => $row->suspended_at,
			'next_billing_date' => $row->next_billing_date,
			'billing_cycle'     => null === $row->billing_cycle ? null : (int) $row->billing_cycle,
			'auto_renew'        => null === $row->auto_renew ? null : '1' === (string) $row->auto_renew,
			'first_seen_at'     => $row->first_seen_at,
			'last_seen_at'      => $row->last_seen_at,
			'removed_at'        => $row->removed_at,
		];
	}

	/**
	 * Get the HAL links of an account.
	 *
	 * @param string|null $siteground_id SiteGround account ID.
	 * @param int|null    $post_id       Post ID.
	 * @return array<string, array{href: string, title: string}>
	 */
	private function get_account_links( ?string $siteground_id, ?int $post_id ): array {
		$links = [];

		if ( null !== $post_id ) {
			$links['self'] = [
				'href'  => (string) \get_permalink( $post_id ),
				'title' => \__( 'Orbis page of this SiteGround account.', 'orbis-siteground' ),
			];
		}

		if ( null !== $siteground_id ) {
			$links['siteground-hosting'] = [
				'href'  => Helpers::get_client_area_account_url( $siteground_id ),
				'title' => \__( 'This hosting account in the SiteGround Client Area (my.siteground.com), the environment of the SiteGround account for the hosting plan, renewal and billing.', 'orbis-siteground' ),
			];
		}

		return $links;
	}

	/**
	 * Search websites.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	private function search_websites( $input = [] ): array {
		$result = $this->plugin->websites->search( (array) $input );

		$result['websites'] = \array_map( $this->format_website( ... ), $result['websites'] );

		return $result;
	}

	/**
	 * Format website row for ability output.
	 *
	 * @param object $row Database row.
	 * @return array<string, mixed>
	 */
	private function format_website( object $row ): array {
		$server = null;

		if ( null !== $row->server_ip || null !== $row->server_location ) {
			$server = [
				'ip'       => $row->server_ip,
				'location' => $row->server_location,
			];
		}

		$post_id         = null === $row->post_id ? null : (int) $row->post_id;
		$account_post_id = null === $row->account_post_id ? null : (int) $row->account_post_id;

		$links = [];

		if ( null !== $post_id ) {
			$links['self'] = [
				'href'  => (string) \get_permalink( $post_id ),
				'title' => \__( 'Orbis page of this SiteGround website.', 'orbis-siteground' ),
			];
		}

		$links['siteground-website'] = [
			'href'  => Helpers::get_client_area_website_url( $row->siteground_id ),
			'title' => \__( 'This website in the SiteGround Client Area (my.siteground.com), the environment of the SiteGround account for websites, services and billing.', 'orbis-siteground' ),
		];

		$links['siteground-site-tools'] = [
			'href'  => Helpers::get_site_tools_url( $row->siteground_id ),
			'title' => \__( 'SiteGround Site Tools (tools.siteground.com) of this website, the environment for managing the website itself: files, databases, email, DNS, SSL, caching, backups and WordPress.', 'orbis-siteground' ),
		];

		if ( null !== $row->admin_url ) {
			$links['admin'] = [
				'href'  => $row->admin_url,
				'title' => \__( 'Admin dashboard of the CMS of this website, for example the WordPress admin.', 'orbis-siteground' ),
			];
		}

		return [
			'_links'        => $links,
			'id'            => (int) $row->id,
			'post_id'       => $post_id,
			'siteground_id' => $row->siteground_id,
			'domain'        => $row->domain,
			'account'       => [
				'_links'        => $this->get_account_links( $row->account_siteground_id, $account_post_id ),
				'siteground_id' => $row->account_siteground_id,
				'name'          => $row->account_name,
				'post_id'       => $account_post_id,
				'status'        => $row->account_status,
				'plan_type'     => $row->account_type,
			],
			'status'        => $row->status,
			'cms'           => $row->cms,
			'server'        => $server,
			'datacenter'    => $row->datacenter_name,
			'created'       => $row->siteground_created_at,
			'suspended'     => (int) $row->suspended > 0,
			'first_seen_at' => $row->first_seen_at,
			'last_seen_at'  => $row->last_seen_at,
			'removed_at'    => $row->removed_at,
		];
	}

	/**
	 * Upload invoice.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	private function upload_invoice( $input = [] ) {
		$input = (array) $input;

		$uploader = new InvoiceUploader( $this->plugin->invoices );

		try {
			$result = $uploader->upload(
				(array) ( $input['invoice'] ?? [] ),
				(string) ( $input['pdf'] ?? '' ),
				isset( $input['file_name'] ) ? (string) $input['file_name'] : null
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'orbis_siteground_invalid_invoice', $e->getMessage() );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'orbis_siteground_invoice_upload_failed', $e->getMessage() );
		}

		$invoice = $result['invoice'];

		return [
			'_links'         => $this->get_invoice_links( $invoice ),
			'id'             => (int) $invoice->id,
			'post_id'        => null === $invoice->post_id ? null : (int) $invoice->post_id,
			'created'        => $result['created'],
			'invoice_number' => $invoice->invoice_number,
			'invoice_date'   => $invoice->invoice_date,
			'currency'       => $invoice->currency,
			'total'          => (float) $invoice->total,
		];
	}

	/**
	 * Search invoices.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	private function search_invoices( $input = [] ): array {
		$result = $this->plugin->invoices->search( (array) $input );

		$result['invoices'] = \array_map( $this->format_invoice( ... ), $result['invoices'] );

		return $result;
	}

	/**
	 * Format invoice row for ability output.
	 *
	 * @param object $row Database row.
	 * @return array<string, mixed>
	 */
	private function format_invoice( object $row ): array {
		return [
			'_links'         => $this->get_invoice_links( $row ),
			'id'             => (int) $row->id,
			'post_id'        => null === $row->post_id ? null : (int) $row->post_id,
			'invoice_number' => $row->invoice_number,
			'document_type'  => $row->document_type,
			'invoice_date'   => $row->invoice_date,
			'currency'       => $row->currency,
			'payment_method' => $row->payment_method,
			'subtotal'       => (float) $row->subtotal,
			'vat_amount'     => (float) $row->vat_amount,
			'total'          => (float) $row->total,
			'tax_scheme'     => $row->tax_scheme,
			'line_items'     => Helpers::get_invoice_line_items( $row ),
		];
	}

	/**
	 * Get the HAL links of an invoice.
	 *
	 * @param object $invoice Invoice.
	 * @return array<string, array{href: string, title: string}>
	 */
	private function get_invoice_links( object $invoice ): array {
		$links = [];

		if ( null !== $invoice->post_id ) {
			$links['self'] = [
				'href'  => (string) \get_permalink( (int) $invoice->post_id ),
				'title' => \__( 'Orbis page of this SiteGround invoice.', 'orbis-siteground' ),
			];
		}

		$links['pdf'] = [
			'href'  => Helpers::get_invoice_pdf_url( $invoice ),
			'title' => \__( 'Original PDF of this SiteGround invoice, only accessible when logged in to Orbis.', 'orbis-siteground' ),
		];

		return $links;
	}
}
