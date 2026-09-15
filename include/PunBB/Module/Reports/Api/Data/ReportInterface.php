<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Api\Data;

/**
 * A report, with what is left of what it names: the post, its topic and forum,
 * and the members who reported it and marked it read. A name is null once
 * what it names was deleted.
 */
interface ReportInterface {
	public function id(): int;

	/** The reported post; null once it was deleted. */
	public function postId(): ?int;

	public function topicId(): int;

	/** The topic's subject; null once it was deleted. */
	public function subject(): ?string;

	public function forumId(): int;

	/** The forum's name; null once it was deleted. */
	public function forumName(): ?string;

	public function reporterId(): int;

	/** The reporter's username; null once the account was deleted. */
	public function reporter(): ?string;

	public function created(): int;

	public function message(): string;

	/** When it was marked read; null while it is unread. */
	public function zapped(): ?int;

	public function zappedById(): ?int;

	/** The username of who marked it read; null while it is unread or once the account was deleted. */
	public function zappedBy(): ?string;
}
