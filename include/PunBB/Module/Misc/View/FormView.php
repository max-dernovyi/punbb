<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Misc\Event\EmailRendering;
use PunBB\Module\Misc\Event\ReportRendering;

/**
 * The form mailing a member or the form reporting a post as the template shows
 * it, built in the order the page is read: the markup placed at each position,
 * and the numbers the form gives its field group, items and fields.
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
	 * @param array<string, mixed> $values what the page shows
	 */
	public function __construct(array $positions, private array $values) {
		$this->positions = array_fill_keys($positions, new Html(''));
	}

	/** @return array{int, int, int} the group, item and field counts so far */
	public function counts(): array {
		return array($this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(EmailRendering|ReportRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();
	}

	public function numberGroup(string $name): void {
		$this->numbers[$name] = ++$this->groupCount;
	}

	public function numberItem(string $name): void {
		$this->numbers[$name] = ++$this->itemCount;
	}

	public function numberField(string $name): void {
		$this->numbers[$name] = ++$this->fieldCount;
	}

	/** Adds what the page shows once it is read. */
	public function show(string $name, mixed $value): void {
		$this->values[$name] = $value;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}
}
