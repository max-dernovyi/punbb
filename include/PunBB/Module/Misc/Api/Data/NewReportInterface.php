<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api\Data;

/**
 * A report a member files about a post, for the moderators.
 */
interface NewReportInterface {
	public function postId(): int;

	public function topicId(): int;

	public function forumId(): int;

	public function reporterId(): int;

	public function createdAt(): int;

	public function message(): string;
}
