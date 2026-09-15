<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api\Data;

/**
 * The topic a reported post is in.
 */
interface ReportedTopicInterface {
	public function id(): int;

	public function subject(): string;

	public function forumId(): int;
}
