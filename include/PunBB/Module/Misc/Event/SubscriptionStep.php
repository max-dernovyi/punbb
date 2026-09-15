<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of subscribing to a topic or a forum, or of unsubscribing: the
 * request checked, and the subscription changed, before the browser is sent on.
 */
final class SubscriptionStep implements EventInterface {
	public const SELECTED = 'selected';

	public const CHANGED = 'changed';

	public const TOPIC = 'topic';

	public const FORUM = 'forum';

	private const STEPS = array(self::SELECTED, self::CHANGED);

	/**
	 * @param string $target TOPIC or FORUM
	 * @param bool $subscribing whether the member subscribes; false when they unsubscribe
	 * @param int $id the topic or the forum
	 * @param string $name the topic's subject or the forum's name, once it is changed
	 */
	public function __construct(
		private readonly string $step,
		private readonly string $target,
		private readonly bool $subscribing,
		private readonly int $id,
		private readonly string $name = ''
	) {
		if (!in_array($step, self::STEPS, true) || !in_array($target, array(self::TOPIC, self::FORUM), true))
			throw new InvalidArgumentException(sprintf('Subscribing to a %s has no step "%s"', $target, $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function target(): string {
		return $this->target;
	}

	public function subscribing(): bool {
		return $this->subscribing;
	}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}
}
