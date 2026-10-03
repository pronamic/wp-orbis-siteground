<?php
/**
 * Importer
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
use Exception;
use InvalidArgumentException;

/**
 * Importer class
 *
 * Upserts the items from a SiteGround API response into a shadow table, keeps
 * a post per item and marks items that are no longer in the response as removed.
 */
abstract readonly class Importer {
	/**
	 * Construct.
	 *
	 * @param Repository $repository Repository.
	 */
	public function __construct(
		/**
		 * Repository.
		 */
		protected Repository $repository
	) {
	}

	/**
	 * Get post type.
	 *
	 * @return string
	 */
	abstract protected function get_post_type(): string;

	/**
	 * Check if a SiteGround item is valid.
	 *
	 * @param array<mixed> $item SiteGround item.
	 * @return bool
	 */
	abstract protected function is_valid_item( array $item ): bool;

	/**
	 * Get the message for an import without items.
	 *
	 * @return string
	 */
	abstract protected function get_empty_message(): string;

	/**
	 * Get the message for an import with invalid items.
	 *
	 * @return string
	 */
	abstract protected function get_invalid_message(): string;

	/**
	 * Map SiteGround item to table columns.
	 *
	 * Must include `siteground_id` and `siteground_created_at`.
	 *
	 * @param array<string, mixed> $item SiteGround item.
	 * @return array<string, mixed>
	 */
	abstract protected function map( array $item ): array;

	/**
	 * Get post title.
	 *
	 * @param array<string, mixed> $values Mapped values.
	 * @return string
	 */
	abstract protected function get_post_title( array $values ): string;

	/**
	 * Import items.
	 *
	 * @param array<int, mixed> $items SiteGround items.
	 * @return ImportResult
	 * @throws InvalidArgumentException When the items are empty or invalid.
	 */
	public function import( array $items ): ImportResult {
		// An empty or invalid list would mark every item as removed, so refuse it.
		if ( [] === $items ) {
			throw new InvalidArgumentException( $this->get_empty_message() );
		}

		foreach ( $items as $item ) {
			if ( ! \is_array( $item ) || ! $this->is_valid_item( $item ) ) {
				throw new InvalidArgumentException( $this->get_invalid_message() );
			}
		}

		$now = \current_time( 'mysql', true );

		$result = new ImportResult();

		foreach ( $items as $item ) {
			$this->upsert( $item, $now, $result );
		}

		$result->removed = $this->repository->mark_removed( $now );

		return $result;
	}

	/**
	 * Upsert item.
	 *
	 * @param array<string, mixed> $item   SiteGround item.
	 * @param string               $now    Import time (UTC, MySQL format).
	 * @param ImportResult         $result Import result.
	 * @return void
	 */
	private function upsert( array $item, string $now, ImportResult $result ): void {
		$values = $this->map( $item );

		$stored = $this->repository->get_by_siteground_id( (string) $values['siteground_id'] );

		if ( null === $stored ) {
			$values['post_id']       = $this->insert_post( $values );
			$values['created_at']    = $now;
			$values['updated_at']    = $now;
			$values['first_seen_at'] = $now;
			$values['last_seen_at']  = $now;

			$this->repository->insert( $values );

			++$result->created;

			return;
		}

		$values['post_id'] = $this->sync_post( $stored, $values );

		$changed = $this->has_changes( $stored, $values );

		$values['last_seen_at'] = $now;
		$values['removed_at']   = null;

		if ( $changed ) {
			$values['updated_at'] = $now;
		}

		$this->repository->update( (int) $stored->id, $values );

		if ( null !== $stored->removed_at ) {
			++$result->restored;
		} elseif ( $changed ) {
			++$result->updated;
		} else {
			++$result->unchanged;
		}
	}

	/**
	 * Check if the mapped values differ from the stored item.
	 *
	 * @param object               $stored Stored item.
	 * @param array<string, mixed> $values Mapped values.
	 * @return bool
	 */
	private function has_changes( object $stored, array $values ): bool {
		foreach ( $values as $key => $value ) {
			$stored_value = $stored->$key ?? null;

			if ( ( null === $value ) !== ( null === $stored_value ) ) {
				return true;
			}

			if ( null !== $value && (string) $value !== (string) $stored_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Insert post for item.
	 *
	 * @param array<string, mixed> $values Mapped values.
	 * @return int|null Post ID.
	 */
	private function insert_post( array $values ): ?int {
		$postarr = [
			'post_type'   => $this->get_post_type(),
			'post_status' => 'publish',
			'post_title'  => $this->get_post_title( $values ),
		];

		if ( \is_string( $values['siteground_created_at'] ?? null ) ) {
			$postarr['post_date_gmt'] = $values['siteground_created_at'];
			$postarr['post_date']     = \get_date_from_gmt( $values['siteground_created_at'] );
		}

		$post_id = \wp_insert_post( $postarr, true );

		return \is_wp_error( $post_id ) ? null : $post_id;
	}

	/**
	 * Sync the post of an existing item.
	 *
	 * Recreates a deleted post, restores a trashed post and updates the title
	 * when it changed.
	 *
	 * @param object               $stored Stored item.
	 * @param array<string, mixed> $values Mapped values.
	 * @return int|null Post ID.
	 */
	private function sync_post( object $stored, array $values ): ?int {
		$post = null === $stored->post_id ? null : \get_post( (int) $stored->post_id );

		if ( null === $post ) {
			return $this->insert_post( $values );
		}

		if ( 'trash' === $post->post_status ) {
			\wp_untrash_post( $post->ID );
		}

		$title = $this->get_post_title( $values );

		if ( 'publish' !== $post->post_status || $title !== $post->post_title ) {
			\wp_update_post(
				[
					'ID'          => $post->ID,
					'post_status' => 'publish',
					'post_title'  => $title,
				]
			);
		}

		return $post->ID;
	}

	/**
	 * String or null.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	protected function string_or_null( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return \is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Convert an ISO 8601 date to a UTC MySQL datetime.
	 *
	 * @param mixed $value Value, for example `2026-09-05T08:20:25-05:00`.
	 * @return string|null
	 */
	protected function date_or_null( $value ): ?string {
		if ( ! \is_string( $value ) || '' === $value ) {
			return null;
		}

		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception ) {
			return null;
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
