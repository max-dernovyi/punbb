<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Model;

use PunBB\Module\Viewtopic\Api\Data\PostLocationInterface;

final readonly class PostLocation implements PostLocationInterface {
	public function __construct(private int $topicId, private int $posted) {}

	public function topicId(): int {
		return $this->topicId;
	}

	public function posted(): int {
		return $this->posted;
	}
}
