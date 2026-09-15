<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Api\Data;

/**
 * What an edit stores: the post's message, and the subject of the topic it opens.
 */
interface PostEditInterface {
	public function postId(): int;

	public function topicId(): int;

	/** The topic's new subject; null when the post does not open it. */
	public function subject(): ?string;

	public function message(): string;

	public function hidesSmilies(): bool;

	/** When it was edited; null for a moderator's silent edit, which leaves the post's last edit as it was. */
	public function editedAt(): ?int;

	/** Who edited it; null for a silent edit. */
	public function editedBy(): ?string;
}
