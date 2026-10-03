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
		$tools[] = 'orbis-siteground/get-invoice';
		$tools[] = 'orbis-siteground/update-invoice';

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

		/**
		 * Unprocessed invoices have no line items yet, so the minimum of
		 * the invoice schema only applies to the input of update-invoice.
		 * The line item definition is inlined because the output schemas
		 * have no `$defs` to resolve the reference against.
		 */
		$line_items_schema = $invoice_schema['properties']['line_items'] ?? [ 'type' => 'array' ];

		unset( $line_items_schema['minItems'] );

		if ( isset( $invoice_schema['$defs']['line_item'] ) ) {
			$line_items_schema['items'] = $invoice_schema['$defs']['line_item'];
		}

		$nullable_amount = [
			'type'    => [ 'string', 'null' ],
			'pattern' => '^-?\\d+\\.\\d{2}$',
		];

		$invoice_properties = [
			'_links'         => $invoice_links,
			'id'             => [ 'type' => 'integer' ],
			'post_id'        => [ 'type' => [ 'integer', 'null' ] ],
			'status'         => [
				'type'        => 'string',
				'description' => \__( 'Unprocessed when the invoice data has not been registered yet with the update-invoice ability, processed otherwise.', 'orbis-siteground' ),
				'enum'        => [ 'unprocessed', 'processed' ],
			],
			'file_name'      => [ 'type' => 'string' ],
			'file_size'      => [ 'type' => 'integer' ],
			'file_sha256'    => [ 'type' => 'string' ],
			'uploaded_at'    => [ 'type' => 'string' ],
			'processed_at'   => $nullable_string,
			'invoice_number' => $nullable_string,
			'document_type'  => $nullable_string,
			'invoice_date'   => $nullable_string,
			'currency'       => $nullable_string,
			'payment_method' => $nullable_string,
			'subtotal'       => $nullable_amount,
			'vat_amount'     => $nullable_amount,
			'total'          => $nullable_amount,
			'tax_scheme'     => $nullable_string,
			'line_items'     => $line_items_schema,
		];

		$invoice_id = [
			'type'        => 'integer',
			'description' => \__( 'Orbis ID of the SiteGround invoice (id of the search-invoices ability).', 'orbis-siteground' ),
			'minimum'     => 1,
		];

		\wp_register_ability(
			'orbis-siteground/search-invoices',
			[
				'label'               => \__( 'Search SiteGround invoices', 'orbis-siteground' ),
				'description'         => \__( 'Searches the SiteGround invoice PDFs that are uploaded to Orbis, unprocessed invoices first and then newest first. An unprocessed invoice is a PDF that has been uploaded but whose data has not been registered yet: read it with the get-invoice ability and register the data with the update-invoice ability. Amounts are numeric strings, for example "14.99", in the currency of the invoice. The _links of each invoice point to the Orbis page and the original PDF.', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'search'      => [
							'type'        => 'string',
							'description' => \__( 'Search term, matched against the invoice number, the file name and all invoice data, for example a domain name or product of an invoice line.', 'orbis-siteground' ),
						],
						'status'      => [
							'type'        => 'string',
							'description' => \__( 'Limit the results to unprocessed or processed invoices.', 'orbis-siteground' ),
							'enum'        => [ 'any', 'unprocessed', 'processed' ],
							'default'     => 'any',
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
								'properties' => $invoice_properties,
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

		\wp_register_ability(
			'orbis-siteground/get-invoice',
			[
				'label'               => \__( 'Get SiteGround invoice', 'orbis-siteground' ),
				'description'         => \__( 'Gets a SiteGround invoice with the text that was extracted from the uploaded PDF, so the invoice can be analysed. The text is null when no text could be extracted, request the PDF itself with include_pdf in that case. The data contains the registered invoice data, null for an unprocessed invoice.', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'id'          => $invoice_id,
						'include_pdf' => [
							'type'        => 'boolean',
							'description' => \__( 'Also return the original PDF file, base64 encoded.', 'orbis-siteground' ),
							'default'     => false,
						],
					],
					'required'             => [ 'id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => \array_merge(
						$invoice_properties,
						[
							'text' => [
								'type'        => [ 'string', 'null' ],
								'description' => \__( 'Text extracted from the PDF.', 'orbis-siteground' ),
							],
							'data' => [
								'type'        => [ 'object', 'null' ],
								'description' => \__( 'Registered invoice data, valid against the invoice schema of the update-invoice ability.', 'orbis-siteground' ),
							],
							'pdf'  => [
								'type'        => 'string',
								'description' => \__( 'The original PDF file, base64 encoded, only when include_pdf is true.', 'orbis-siteground' ),
							],
						]
					),
				],
				'execute_callback'    => $this->get_invoice( ... ),
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
			'orbis-siteground/update-invoice',
			[
				'label'               => \__( 'Update SiteGround invoice', 'orbis-siteground' ),
				'description'         => \__( 'Registers the structured data of an uploaded SiteGround invoice PDF. First read the invoice with the get-invoice ability and extract the data exactly according to the invoice schema: copy values literally, use null for values that are not on the PDF and never guess. The data replaces the previously registered data of the invoice and marks the invoice as processed. An invoice number can only be registered for one invoice.', 'orbis-siteground' ),
				'category'            => 'orbis-siteground',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'id'      => $invoice_id,
						'invoice' => $invoice_schema,
					],
					'required'             => [ 'id', 'invoice' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => $invoice_properties,
				],
				'execute_callback'    => $this->update_invoice( ... ),
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
	 * Get invoice.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	private function get_invoice( $input = [] ) {
		$input = (array) $input;

		$invoice = $this->plugin->invoices->get_by_id( (int) ( $input['id'] ?? 0 ) );

		if ( null === $invoice ) {
			return new WP_Error( 'orbis_siteground_invoice_not_found', \__( 'SiteGround invoice not found.', 'orbis-siteground' ) );
		}

		$result = $this->format_invoice( $invoice );

		$result['text'] = $invoice->text;
		$result['data'] = null === $invoice->data ? null : Helpers::get_invoice_data( $invoice );

		if ( true === ( $input['include_pdf'] ?? false ) ) {
			$uploads = \wp_upload_dir( null, false );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in the protected uploads directory.
			$content = \file_get_contents( \trailingslashit( $uploads['basedir'] ) . $invoice->file_path );

			if ( false === $content ) {
				return new WP_Error( 'orbis_siteground_invoice_pdf_not_found', \__( 'SiteGround invoice PDF not found.', 'orbis-siteground' ) );
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The PDF is returned base64 encoded.
			$result['pdf'] = \base64_encode( $content );
		}

		return $result;
	}

	/**
	 * Update invoice.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	private function update_invoice( $input = [] ) {
		$input = (array) $input;

		$service = new InvoiceService( $this->plugin->invoices );

		try {
			$invoice = $service->update( (int) ( $input['id'] ?? 0 ), (array) ( $input['invoice'] ?? [] ) );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'orbis_siteground_invalid_invoice', $e->getMessage() );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'orbis_siteground_invoice_update_failed', $e->getMessage() );
		}

		return $this->format_invoice( $invoice );
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
			'status'         => null === $row->processed_at ? 'unprocessed' : 'processed',
			'file_name'      => $row->file_name,
			'file_size'      => (int) $row->file_size,
			'file_sha256'    => $row->file_sha256,
			'uploaded_at'    => $row->created_at,
			'processed_at'   => $row->processed_at,
			'invoice_number' => $row->invoice_number,
			'document_type'  => $row->document_type,
			'invoice_date'   => $row->invoice_date,
			'currency'       => $row->currency,
			'payment_method' => $row->payment_method,
			'subtotal'       => $row->subtotal,
			'vat_amount'     => $row->vat_amount,
			'total'          => $row->total,
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
