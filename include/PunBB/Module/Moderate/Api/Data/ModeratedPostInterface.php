<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A post of a topic being moderated, with what the page shows of its poster.
 */
interface ModeratedPostInterface {
	public function id(): int;

	/** The name the post carries. */
	public function poster(): string;

	/** The poster's account; 1 for a guest. */
	public function posterId(): int;

	public function message(): string;

	public function hidesSmilies(): bool;

	public function posted(): int;

	/** When it was last edited; null when it never was. */
	public function edited(): ?int;

	public function editedBy(): string;

	/** The poster's own title; '' for none. */
	public function posterTitle(): string;

	public function posterPostCount(): int;

	public function posterGroupId(): int;

	/** The title the poster's group gives its members; null for none. */
	public function posterGroupTitle(): ?string;
}
