<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the form adding or editing a ban, which an observer may add
 * markup at. The form numbers its field groups, items and fields in order;
 * markup that adds any of them counts them, and the form numbers on from there.
 */
final class BanFormRendering implements EventInterface {
	public const OUTPUT_START = 'output_start';

	public const PRE_CRITERIA_FIELDSET = 'pre_criteria_fieldset';

	public const PRE_USERNAME = 'pre_username';

	public const PRE_EMAIL = 'pre_email';

	public const PRE_IP = 'pre_ip';

	public const PRE_MESSAGE = 'pre_message';

	public const PRE_EXPIRE = 'pre_expire';

	public const CRITERIA_PRE_FIELDSET_END = 'criteria_pre_fieldset_end';

	public const CRITERIA_FIELDSET_END = 'criteria_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::OUTPUT_START, self::PRE_CRITERIA_FIELDSET, self::PRE_USERNAME, self::PRE_EMAIL, self::PRE_IP, self::PRE_MESSAGE, self::PRE_EXPIRE,
		self::CRITERIA_PRE_FIELDSET_END, self::CRITERIA_FIELDSET_END, self::END,
	);

	private string $markup = '';

	/** @param bool $adding whether the form adds a ban, not edits one */
	public function __construct(private readonly string $position, private readonly bool $adding, private int $groupCount, private int $itemCount, private int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The ban form has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function adding(): bool {
		return $this->adding;
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
}
