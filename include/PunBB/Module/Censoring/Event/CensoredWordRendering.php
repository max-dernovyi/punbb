<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Event;

use InvalidArgumentException;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in a stored word's fieldset of the form editing the censored
 * words, which an observer may add markup at. The form numbers its items and
 * fields in order; markup that adds either counts it, and the form numbers on
 * from there.
 */
final class CensoredWordRendering implements EventInterface {
	public const PRE_EDIT_WORD_FIELDSET = 'pre_edit_word_fieldset';

	public const PRE_EDIT_SEARCH_FOR = 'pre_edit_search_for';

	public const PRE_EDIT_REPLACE_WITH = 'pre_edit_replace_with';

	public const PRE_EDIT_SUBMIT = 'pre_edit_submit';

	public const PRE_EDIT_WORD_FIELDSET_END = 'pre_edit_word_fieldset_end';

	/** After the fieldset. */
	public const EDIT_WORD_FIELDSET_END = 'edit_word_fieldset_end';

	public const POSITIONS = array(
		self::PRE_EDIT_WORD_FIELDSET, self::PRE_EDIT_SEARCH_FOR, self::PRE_EDIT_REPLACE_WITH, self::PRE_EDIT_SUBMIT,
		self::PRE_EDIT_WORD_FIELDSET_END, self::EDIT_WORD_FIELDSET_END,
	);

	private string $markup = '';

	/** @param int $number the word's place in the list, from 1 */
	public function __construct(
		private readonly string $position,
		private readonly CensorInterface $censor,
		private readonly int $number,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('A censored word\'s fieldset has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function censor(): CensorInterface {
		return $this->censor;
	}

	public function number(): int {
		return $this->number;
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
