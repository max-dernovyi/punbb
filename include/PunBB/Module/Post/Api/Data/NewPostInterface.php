<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Api\Data;

/**
 * A post about to be stored: a reply to a topic, or the first post of a new one.
 */
interface NewPostInterface {
	/** The subscription a reply changes: none, subscribes the poster, or ends it. */
	public const SUBSCRIPTION_KEPT = 0;

	public const SUBSCRIPTION_STARTED = 1;

	public const SUBSCRIPTION_ENDED = 2;

	public function isGuest(): bool;

	public function poster(): string;

	/** The poster's account; the guest account for a guest. */
	public function posterId(): int;

	/** The address a guest left; null for a member. */
	public function posterEmail(): ?string;

	/** The topic's subject: the one replied to, or the new topic's. */
	public function subject(): string;

	public function message(): string;

	public function hidesSmilies(): bool;

	public function postedAt(): int;

	/** The topic replied to; 0 for a new topic. */
	public function topicId(): int;

	public function forumId(): int;

	public function forumName(): string;

	/** One of the SUBSCRIPTION_* constants; a new topic either starts one or keeps none. */
	public function subscription(): int;
}
