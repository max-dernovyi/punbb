<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api\Data;

/**
 * A title a member earns by posting.
 */
interface RankInterface {
	public function title(): string;

	/** The posts a member needs for it. */
	public function minPosts(): int;
}
