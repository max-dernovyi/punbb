<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Api\Data;

/**
 * Where a post is: its topic, and when it was posted there.
 */
interface PostLocationInterface {
	public function topicId(): int;

	public function posted(): int;
}
