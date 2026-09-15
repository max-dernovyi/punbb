<?php

declare(strict_types=1);

namespace PunBB\Module\Search\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Search\Api\Data\SearchableForumInterface;
use PunBB\Module\Search\Event\ForumChecklistRendering;
use PunBB\Module\Search\Event\SearchFormRendering;

/**
 * The search form as the template shows it, built in the order the page is
 * read: the markup placed at each position, the numbers the form gives its
 * field groups, items and fields, and the links, lines and sort options as
 * they stood when the form showed them.
 */
final class SearchFormView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, array<string, int>> 'groups', 'items' or 'fields' => what is numbered => its number */
	private array $numbers = array('groups' => array(), 'items' => array(), 'fields' => array());

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/** @var array<string, mixed> */
	private array $values = array();

	public function __construct(
		public readonly bool $advanced,
		private readonly Parts $headOptions,
		private readonly Parts $info,
		private readonly Parts $sort
	) {
		$this->positions = array_fill_keys(SearchFormRendering::POSITIONS, new Html(''));
	}

	/** The event for $position, carrying the form's counts so far and what may still change. */
	public function rendering(string $position): SearchFormRendering {
		return new SearchFormRendering($position, $this->advanced, $this->groupCount, $this->itemCount, $this->fieldCount, $this->headOptions, $this->info, $this->sort);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(SearchFormRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->placeCounts($event);
	}

	public function checklistRendering(string $position, SearchableForumInterface $forum): ForumChecklistRendering {
		return new ForumChecklistRendering($position, $forum, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	public function placeCounts(SearchFormRendering|ForumChecklistRendering $event): void {
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();
	}

	/** The links above the form and the lines explaining it, as they stand now. */
	public function showHead(): void {
		$this->values['headOptions'] = $this->headOptions->isEmpty() ? null : new Html($this->headOptions->join(' '));
		$this->values['info'] = new Html($this->info->join("\n\t\t\t\t"));
	}

	/** The sort options, as they stand now. */
	public function showSort(): void {
		$this->values['sort'] = new Html($this->sort->join("\n\t\t\t\t\t\t"));
	}

	public function numberGroup(string $name): void {
		$this->numbers['groups'][$name] = ++$this->groupCount;
	}

	public function numberItem(string $name): void {
		$this->numbers['items'][$name] = ++$this->itemCount;
	}

	public function numberField(string $name): void {
		$this->numbers['fields'][$name] = ++$this->fieldCount;
	}

	/** The next field's number, which it takes. */
	public function nextField(): int {
		return ++$this->fieldCount;
	}

	/** The results fieldset numbers its items from 1 again. */
	public function restartItems(): void {
		$this->itemCount = 0;
	}

	public function show(string $name, mixed $value): void {
		$this->values[$name] = $value;
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return $this->values + $this->numbers + array('advanced' => $this->advanced, 'positions' => $this->positions, 'sort' => new Html(''), 'checklist' => array());
	}
}
