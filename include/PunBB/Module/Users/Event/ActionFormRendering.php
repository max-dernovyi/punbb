<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * The start or the end of a form asked for the users selected, which an
 * observer may add markup at: the deletion's confirmation, the ban's message
 * and expiry, or the group they move to. The form numbers its field groups,
 * items and fields on from the start.
 */
final class ActionFormRendering implements EventInterface {
	use FormMarkup;

	public const DELETE = 'delete';

	public const BAN = 'ban';

	public const CHANGE_GROUP = 'change_group';

	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	/** @param list<int> $ids the users selected */
	public function __construct(private readonly string $form, private readonly string $position, private readonly array $ids, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($form, array(self::DELETE, self::BAN, self::CHANGE_GROUP), true))
			throw new InvalidArgumentException(sprintf('The users page has no form "%s"', $form));

		if (!in_array($position, array(self::OUTPUT_START, self::END), true))
			throw new InvalidArgumentException(sprintf('A form of the users page has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function form(): string {
		return $this->form;
	}

	public function position(): string {
		return $this->position;
	}

	/** @return list<int> */
	public function ids(): array {
		return $this->ids;
	}
}
