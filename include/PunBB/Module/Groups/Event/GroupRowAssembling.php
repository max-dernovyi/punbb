<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Groups\Api\Data\ListedGroupInterface;
use PunBB\Module\Layout\Event\FormMarkup;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A group in the list of groups, which an observer may add markup at: before
 * it and after it. Before it the options the group offers, named ('edit',
 * 'remove'), may still change. Markup that adds an item counts it.
 */
final class GroupRowAssembling implements EventInterface {
	use FormMarkup;
	use MarkupEntries;

	public const PRE_OUTPUT = 'pre_output';

	public const POST_OUTPUT = 'post_output';

	/** @param array<string, string> $options the options, before the group only */
	public function __construct(
		private readonly string $position,
		private readonly ListedGroupInterface $group,
		private readonly bool $default,
		array $options,
		int $groupCount,
		int $itemCount,
		int $fieldCount
	) {
		if (!in_array($position, array(self::PRE_OUTPUT, self::POST_OUTPUT), true))
			throw new InvalidArgumentException(sprintf('A listed group has no position "%s"', $position));

		$this->entries = $options;
		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}

	public function group(): ListedGroupInterface {
		return $this->group;
	}

	/** Whether new users join the group. */
	public function isDefault(): bool {
		return $this->default;
	}

	private function accept(string $name): void {
		if ($this->position !== self::PRE_OUTPUT)
			throw new InvalidArgumentException('A listed group\'s options are shown by now');
	}
}
