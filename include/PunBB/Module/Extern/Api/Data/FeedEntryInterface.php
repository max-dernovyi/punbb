<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Api\Data;

/**
 * A post a feed carries: a topic's first post in a feed of topics, any post in
 * a feed of posts. The poster's address comes from their account, or from the
 * post when a guest wrote it.
 */
interface FeedEntryInterface {
	/** The topic's id in a feed of topics, the post's in a feed of posts. */
	public function id(): int;

	/** The topic's subject in a feed of topics; '' in a feed of posts. */
	public function subject(): string;

	public function poster(): string;

	public function posterId(): int;

	public function posted(): int;

	public function message(): string;

	public function hidesSmilies(): bool;

	/** The address of the poster's account; '' for a guest. */
	public function accountEmail(): string;

	/** Whether the poster lets their address be shown: their account's email setting is 0. */
	public function showsEmail(): bool;

	/** The address a guest left with the post; '' when none. */
	public function guestEmail(): string;
}
