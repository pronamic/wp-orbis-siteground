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
				'description' => \__( 'Abilities for working with SiteGround hosting accounts in Orbis.', 'orbis-siteground' ),
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

		\wp_register_ability(
			'orbis-siteground/search-accounts',
			[
				'label'               => \__( 'Search SiteGround accounts', 'orbis-siteground' ),
				'description'         => \__( 'Searches the SiteGround hosting accounts (one account per website/domain) that are imported into Orbis and returns their status, plan, server and expiration and billing dates. Accounts that are no longer present at SiteGround are excluded unless include_removed is true. Dates are in UTC.', 'orbis-siteground' ),
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
									'id'                => [ 'type' => 'integer' ],
									'post_id'           => [ 'type' => [ 'integer', 'null' ] ],
									'url'               => $nullable_string,
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
			'id'                => (int) $row->id,
			'post_id'           => $post_id,
			'url'               => null === $post_id ? null : (string) \get_permalink( $post_id ),
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
}
