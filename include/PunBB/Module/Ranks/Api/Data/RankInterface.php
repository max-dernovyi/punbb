<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Api\Data;

/**
 * A rank: the title a member earns at a number of posts; the id is 0 for a rank not stored yet.
 */
interface RankInterface {
	public function id(): int;

	public function title(): string;

	public function minPosts(): int;
}
