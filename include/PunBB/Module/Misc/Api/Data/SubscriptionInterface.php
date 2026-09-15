<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api\Data;

/**
 * A member's subscription to a topic or a forum.
 */
interface SubscriptionInterface {
	public function userId(): int;

	/** The topic or the forum subscribed to. */
	public function targetId(): int;
}
