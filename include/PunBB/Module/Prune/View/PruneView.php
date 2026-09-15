<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Prune\Event\PruneRendering;

/**
 * The pruning form or the confirmation as the template shows it, built in the
 * order the page is read: the markup placed at each position, and the numbers
 * the form gives its field groups, items and fields.
 */
final class PruneView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the form numbers => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/** @param array<string, mixed> $values what the page shows */
	public function __construct(private array $values) {
		$this->positions = array_fill_keys(PruneRendering::POSITIONS, new Html(''));
	}

	/** The event for $position, carrying the form's counts so far. */
	public function rendering(string $position): PruneRendering {
		return new PruneRendering($position, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(PruneRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();
	}

	public function markup(string $position): Html {
		return $this->positions[$position];
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

	/** Adds what the page shows once it is read, such as the forums listed. */
	public function show(string $name, mixed $value): void {
		$this->values[$name] = $value;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}
}
