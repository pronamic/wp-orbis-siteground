<?php
/**
 * Import result
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Pronamic\Orbis\SiteGround;

/**
 * Import result class
 */
final class ImportResult {
	/**
	 * Number of new accounts.
	 *
	 * @var int
	 */
	public int $created = 0;

	/**
	 * Number of existing accounts with changes.
	 *
	 * @var int
	 */
	public int $updated = 0;

	/**
	 * Number of existing accounts without changes.
	 *
	 * @var int
	 */
	public int $unchanged = 0;

	/**
	 * Number of removed accounts that reappeared.
	 *
	 * @var int
	 */
	public int $restored = 0;

	/**
	 * Number of accounts that disappeared.
	 *
	 * @var int
	 */
	public int $removed = 0;

	/**
	 * Get the total number of accounts in the import.
	 *
	 * @return int
	 */
	public function get_total(): int {
		return $this->created + $this->updated + $this->unchanged + $this->restored;
	}

	/**
	 * To array.
	 *
	 * @return array<string, int>
	 */
	public function to_array(): array {
		return [
			'total'     => $this->get_total(),
			'created'   => $this->created,
			'updated'   => $this->updated,
			'unchanged' => $this->unchanged,
			'restored'  => $this->restored,
			'removed'   => $this->removed,
		];
	}
}
