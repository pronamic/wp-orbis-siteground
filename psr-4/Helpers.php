<?php
/**
 * Helpers
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Helpers class
 */
final class Helpers {
	/**
	 * Format a UTC MySQL datetime as a local date.
	 *
	 * @param string|null $value  UTC MySQL datetime.
	 * @param string      $format Date format.
	 * @return string
	 */
	public static function format_date( ?string $value, string $format = 'D j M Y' ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		$date = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );

		return (string) \wp_date( $format, $date->getTimestamp() );
	}

	/**
	 * Format a UTC MySQL datetime as a local date and time.
	 *
	 * @param string|null $value UTC MySQL datetime.
	 * @return string
	 */
	public static function format_datetime( ?string $value ): string {
		return self::format_date( $value, 'D j M Y H:i' );
	}

	/**
	 * Get the status badges of an account.
	 *
	 * @param object $account Account.
	 * @return array<int, array{variation: string, content: string}>
	 */
	public static function get_status_badges( object $account ): array {
		$badges = [];

		$variations = [
			'active'        => 'success',
			'expiring soon' => 'warning',
			'expired'       => 'danger',
		];

		$labels = [
			'active'        => \__( 'Active', 'orbis-siteground' ),
			'expiring soon' => \__( 'Expiring soon', 'orbis-siteground' ),
			'expired'       => \__( 'Expired', 'orbis-siteground' ),
		];

		$badges[] = [
			'variation' => $variations[ $account->status ] ?? 'secondary',
			'content'   => $labels[ $account->status ] ?? (string) $account->status,
		];

		if ( null !== $account->suspended_at ) {
			$badges[] = [
				'variation' => 'danger',
				'content'   => \__( 'Suspended', 'orbis-siteground' ),
			];
		}

		if ( null !== $account->removed_at ) {
			$badges[] = [
				'variation' => 'dark',
				'content'   => \__( 'Removed', 'orbis-siteground' ),
			];
		}

		return $badges;
	}

	/**
	 * Get the status badges of a website.
	 *
	 * @param object $website Website.
	 * @return array<int, array{variation: string, content: string}>
	 */
	public static function get_website_status_badges( object $website ): array {
		$badges = [];

		$variations = [
			'active'          => 'success',
			'offline_mode'    => 'warning',
			'account_expired' => 'danger',
		];

		$labels = [
			'active'          => \__( 'Active', 'orbis-siteground' ),
			'offline_mode'    => \__( 'Offline mode', 'orbis-siteground' ),
			'account_expired' => \__( 'Account expired', 'orbis-siteground' ),
		];

		$badges[] = [
			'variation' => $variations[ $website->status ] ?? 'secondary',
			'content'   => $labels[ $website->status ] ?? (string) $website->status,
		];

		if ( (int) $website->suspended > 0 ) {
			$badges[] = [
				'variation' => 'danger',
				'content'   => \__( 'Suspended', 'orbis-siteground' ),
			];
		}

		if ( null !== $website->removed_at ) {
			$badges[] = [
				'variation' => 'dark',
				'content'   => \__( 'Removed', 'orbis-siteground' ),
			];
		}

		return $badges;
	}

	/**
	 * Get the label of a website CMS.
	 *
	 * @param string|null $cms SiteGround CMS, for example `wordpress`.
	 * @return string
	 */
	public static function get_cms_label( ?string $cms ): string {
		$labels = [
			'wordpress'   => 'WordPress',
			'woocommerce' => 'WooCommerce',
			'general'     => \__( 'General', 'orbis-siteground' ),
		];

		return $labels[ $cms ] ?? (string) $cms;
	}

	/**
	 * Render a link to the SiteGround account of a website.
	 *
	 * Falls back to the account name when the account is not imported.
	 *
	 * @param object $website Website.
	 * @return void
	 */
	public static function render_website_account_link( object $website ): void {
		$account = null === $website->account_siteground_id ? null : Plugin::instance()->accounts->get_by_siteground_id( $website->account_siteground_id );

		if ( null === $account || null === $account->post_id ) {
			echo \esc_html( (string) $website->account_name );

			return;
		}

		\printf(
			'<a href="%s">%s</a>',
			\esc_url( (string) \get_permalink( (int) $account->post_id ) ),
			\esc_html( (string) $account->name )
		);
	}

	/**
	 * Render the status badges of an account.
	 *
	 * @param object $account Account.
	 * @param string $context Context, `admin` or `theme`.
	 * @return void
	 */
	public static function render_status_badges( object $account, string $context = 'admin' ): void {
		self::render_badges( self::get_status_badges( $account ), $context );
	}

	/**
	 * Render the status badges of a website.
	 *
	 * @param object $website Website.
	 * @param string $context Context, `admin` or `theme`.
	 * @return void
	 */
	public static function render_website_status_badges( object $website, string $context = 'admin' ): void {
		self::render_badges( self::get_website_status_badges( $website ), $context );
	}

	/**
	 * Render badges.
	 *
	 * @param array<int, array{variation: string, content: string}> $badges  Badges.
	 * @param string                                                $context Context, `admin` or `theme`.
	 * @return void
	 */
	private static function render_badges( array $badges, string $context ): void {
		foreach ( $badges as $badge ) {
			if ( 'theme' === $context ) {
				\printf(
					'<span class="badge text-bg-%s">%s</span> ',
					\esc_attr( $badge['variation'] ),
					\esc_html( $badge['content'] )
				);

				continue;
			}

			\printf(
				'<code>%s</code> ',
				\esc_html( $badge['content'] )
			);
		}
	}
}
