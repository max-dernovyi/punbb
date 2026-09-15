<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of opening, closing, sticking or unsticking topics: once asked for,
 * and once changed, before the browser is sent on. Topics selected in the
 * forum's list are opened or closed at once; a topic's own link opens,
 * closes, sticks or unsticks it once its token is checked.
 */
final class TopicStateStep implements EventInterface {
	/** Opening or closing asked for, before the topics are read. */
	public const OPEN_CLOSE_SELECTED = 'open_close_selected';

	/** The topics selected in the list opened or closed. */
	public const LIST_CHANGED = 'list_changed';

	/** The topic of a link opened or closed, with its subject. */
	public const LINK_CHANGED = 'link_changed';

	/** Sticking asked for, the token checked, before the topic is read. */
	public const STICK_SELECTED = 'stick_selected';

	public const STUCK = 'stuck';

	public const UNSTICK_SELECTED = 'unstick_selected';

	public const UNSTUCK = 'unstuck';

	private const STEPS = array(self::OPEN_CLOSE_SELECTED, self::LIST_CHANGED, self::LINK_CHANGED, self::STICK_SELECTED, self::STUCK, self::UNSTICK_SELECTED, self::UNSTUCK);

	/**
	 * @param bool $closing whether topics are closed, not opened
	 * @param list<int> $topicIds
	 * @param bool $listed whether the topics are selected in the forum's list, not a topic's link
	 */
	public function __construct(private readonly string $step, private readonly bool $closing = false, private readonly array $topicIds = array(), private readonly string $subject = '', private readonly bool $listed = false) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing topics has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function closing(): bool {
		return $this->closing;
	}

	/** @return list<int> the topics changed; a link's one, or none before they are read */
	public function topicIds(): array {
		return $this->topicIds;
	}

	/** The subject of a link's topic, once it is read. */
	public function subject(): string {
		return $this->subject;
	}

	public function listed(): bool {
		return $this->listed;
	}
}
