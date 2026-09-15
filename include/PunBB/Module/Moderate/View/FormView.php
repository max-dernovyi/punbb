<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Moderate\Event\ModerationFormRendering;

/**
 * A form confirming a change of posts or topics as the template shows it,
 * built in the order the page is read: the markup placed at each position,
 * and the numbers the form gives its field groups, items and fields.
 */
final class FormView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the form numbers => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/**
	 * @param list<string> $positions every position of the form
	 * @param array<string, mixed> $values what the form shows
	 */
	public function __construct(array $positions, private array $values) {
		$this->positions = array_fill_keys($positions, new Html(''));
	}

	/** @return array{int, int, int} the group, item and field counts so far */
	public function counts(): array {
		return array($this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(ModerationFormRendering $event): Html {
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();

		return $this->positions[$event->position()] = new Html($event->markup());
	}

	public function numberGroup(string $name): int {
		return $this->numbers[$name] = ++$this->groupCount;
	}

	public function numberItem(string $name): int {
		return $this->numbers[$name] = ++$this->itemCount;
	}

	public function numberField(string $name): int {
		return $this->numbers[$name] = ++$this->fieldCount;
	}

	/** Adds what the form shows once it is read, such as the forums listed. */
	public function show(string $name, mixed $value): void {
		$this->values[$name] = $value;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}
}
