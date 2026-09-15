<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\View;

use PunBB\Module\AdminIndex\Event\InformationRendering;
use PunBB\Module\Layout\View\Html;

/**
 * The administration's index as the template shows it, built in the order the
 * page is read: the markup placed at each position and the number each box gets.
 */
final class InformationView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> box => its number */
	private array $numbers = array();

	private int $itemCount = 0;

	/** @param array<string, mixed> $values what the boxes show */
	public function __construct(private readonly array $values) {
		$this->positions = array_fill_keys(InformationRendering::POSITIONS, new Html(''));
	}

	/** The event for $position, carrying the boxes numbered so far. */
	public function rendering(string $position): InformationRendering {
		return new InformationRendering($position, $this->itemCount);
	}

	/** Places what the observers of $event added, and numbers on from where they left the count. */
	public function place(InformationRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->itemCount = $event->itemCount();
	}

	public function markup(string $position): Html {
		return $this->positions[$position];
	}

	/** Gives box $name the next number. */
	public function numberItem(string $name): void {
		$this->numbers[$name] = ++$this->itemCount;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}
}
