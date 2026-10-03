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
	 * Render the status badges of an account.
	 *
	 * @param object $account Account.
	 * @param string $context Context, `admin` or `theme`.
	 * @return void
	 */
	public static function render_status_badges( object $account, string $context = 'admin' ): void {
		foreach ( self::get_status_badges( $account ) as $badge ) {
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
