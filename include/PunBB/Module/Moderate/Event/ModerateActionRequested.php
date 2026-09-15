<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;

/**
 * The moderation page was asked for none of the changes it knows, before the
 * forum's topics are listed.
 */
final class ModerateActionRequested implements EventInterface {
	public function __construct(private readonly ModeratedForumInterface $forum) {}

	public function forum(): ModeratedForumInterface {
		return $this->forum;
	}
}
