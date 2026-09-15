<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Event\RankRendering;
use PunBB\Module\Ranks\Event\RanksRendering;

/**
 * The ranks page as the template shows it, built in the order the page is
 * read: the markup placed at each position, and the numbers the forms give
 * their field groups, items and fields.
 */
final class RanksView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the form numbers => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/** @param array<string, mixed> $values what the page shows */
	public function __construct(private array $values) {
		$this->positions = array_fill_keys(RanksRendering::POSITIONS, new Html(''));
	}

	/** The event for $position, carrying the forms' counts so far. */
	public function rendering(string $position): RanksRendering {
		return new RanksRendering($position, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(RanksRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();
	}

	/** The event for $position in the fieldset of $rank, the $number-th rank listed. */
	public function rankRendering(string $position, RankInterface $rank, int $number): RankRendering {
		return new RankRendering($position, $rank, $number, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** What the observers of $event added, counting on from where they left the form. */
	public function placeRank(RankRendering $event): Html {
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();

		return new Html($event->markup());
	}

	public function markup(string $position): Html {
		return $this->positions[$position];
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

	/** The second form numbers its groups and items from one again, and its fields on from the first form's. */
	public function restartGroupsAndItems(): void {
		$this->groupCount = $this->itemCount = 0;
	}

	/** Adds what the page shows once it is read, such as the ranks listed. */
	public function show(string $name, mixed $value): void {
		$this->values[$name] = $value;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}
}
