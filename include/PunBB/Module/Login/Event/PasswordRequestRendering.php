<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in the form asking for a new password, which an observer may add
 * markup at. The form numbers its field groups, items and fields in order;
 * markup that adds any of them counts them, and the form numbers on from
 * there. Before the errors the errors, each named, may still change; they are
 * there only when something stopped the request.
 */
final class PasswordRequestRendering implements EventInterface {
	use MarkupEntries;

	public const OUTPUT_START = 'output_start';

	public const PRE_NEW_PASSWORD_ERRORS = 'pre_new_password_errors';

	public const PRE_GROUP = 'pre_group';

	public const PRE_EMAIL = 'pre_email';

	public const PRE_GROUP_END = 'pre_group_end';

	public const GROUP_END = 'group_end';

	public const END = 'end';

	public const POSITIONS = array(self::OUTPUT_START, self::PRE_NEW_PASSWORD_ERRORS, self::PRE_GROUP, self::PRE_EMAIL, self::PRE_GROUP_END, self::GROUP_END, self::END);

	private string $markup = '';

	/**
	 * @param string $action the URL the form posts to
	 * @param array<string, string> $errors
	 */
	public function __construct(
		private readonly string $position,
		private readonly string $action,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount,
		array $errors = array()
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The new password form has no position "%s"', $position));

		$this->entries = $errors;
	}

	public function position(): string {
		return $this->position;
	}

	public function action(): string {
		return $this->action;
	}

	public function groupCount(): int {
		return $this->groupCount;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function fieldCount(): int {
		return $this->fieldCount;
	}

	/** The form's counts once the markup added here is counted in. */
	public function count(int $groups, int $items, int $fields): void {
		$this->groupCount = $groups;
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {
		if ($this->position !== self::PRE_NEW_PASSWORD_ERRORS)
			throw new InvalidArgumentException('The new password form\'s errors change only before they are placed');
	}
}
