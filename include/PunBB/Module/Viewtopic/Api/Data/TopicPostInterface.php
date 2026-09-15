<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Api\Data;

/**
 * A post as its topic's page shows it, with what the page shows of its poster.
 */
interface TopicPostInterface {
	public function id(): int;

	/** The poster's user id; the guest account's for a guest. */
	public function posterId(): int;

	/** The name it was posted under. */
	public function poster(): string;

	public function posterIp(): string;

	/** The email a guest left with it; null for a member's post. */
	public function posterEmail(): ?string;

	public function message(): string;

	public function hidesSmilies(): bool;

	public function posted(): int;

	/** When it was last edited; null when it never was. */
	public function edited(): ?int;

	public function editedBy(): ?string;

	/** The poster's account's email. */
	public function email(): string;

	public function title(): ?string;

	public function url(): ?string;

	public function location(): ?string;

	public function signature(): ?string;

	/** Who may email the poster: 0 anyone, 1 through the form, 2 nobody. */
	public function emailSetting(): int;

	public function postCount(): int;

	public function registered(): int;

	public function adminNote(): ?string;

	/** The avatar's image type; 0 for none. */
	public function avatar(): int;

	public function avatarWidth(): int;

	public function avatarHeight(): int;

	public function groupId(): int;

	public function groupTitle(): ?string;

	/** Whether the poster is signed in and active now. */
	public function isOnline(): bool;
}
