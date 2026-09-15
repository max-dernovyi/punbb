<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Userlist\Api\Data\GroupInterface;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;
use PunBB\Module\Userlist\Event\UserListRendering;

/**
 * The member list as the template shows it, built in the order the page is
 * read: the markup placed at each position, the numbers the search form gives
 * its field groups, items and fields, the groups, and the members' rows.
 */
final class UserListView {
	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the form numbers => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/** @var list<array{id: int, title: string}> */
	private array $groups = array();

	private Html $table;

	private Html $headers;

	/** @var list<array{before: Html, class: string, cells: Html}> */
	private array $rows = array();

	/**
	 * @param array<string, Html> $strings the userlist language pack
	 * @param list<Html> $options shown beside the heading
	 */
	public function __construct(
		private readonly MemberSearchInterface $search,
		private readonly bool $searchesUsernames,
		private readonly bool $showsPostCount,
		private readonly Html $itemsInfo,
		private readonly array $options,
		private readonly string $formAction,
		private readonly array $strings
	) {
		$this->positions = array_fill_keys(UserListRendering::POSITIONS, new Html(''));
		$this->table = new Html('');
		$this->headers = new Html('');
	}

	/** The event for $position, carrying the form's counts so far. */
	public function rendering(string $position): UserListRendering {
		return new UserListRendering($position, $this->search, $this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/** Places what the observers of $event added, and counts on from where they left the form. */
	public function place(UserListRendering $event): void {
		$this->positions[$event->position()] = new Html($event->markup());
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();
	}

	public function markup(string $position): Html {
		return $this->positions[$position];
	}

	/** Gives $name the next field group's number. */
	public function numberGroup(string $name): void {
		$this->numbers[$name] = ++$this->groupCount;
	}

	/** Gives $name the next item's number. */
	public function numberItem(string $name): void {
		$this->numbers[$name] = ++$this->itemCount;
	}

	/** Gives $name the next field's number. */
	public function numberField(string $name): void {
		$this->numbers[$name] = ++$this->fieldCount;
	}

	/** @param list<GroupInterface> $groups */
	public function listGroups(array $groups): void {
		foreach ($groups as $group)
			$this->groups[] = array('id' => $group->id(), 'title' => $group->title());
	}

	/** The table's header cells, and the markup before the table. */
	public function head(Html $before, Html $headers): void {
		$this->table = $before;
		$this->headers = $headers;
	}

	/** A member's row: the markup before it, its number in the table from 1, and its cells. */
	public function addRow(Html $before, int $number, Html $cells): void {
		$this->rows[] = array('before' => $before, 'class' => ($number % 2 !== 0 ? 'odd' : 'even').($number === 1 ? ' row1' : ''), 'cells' => $cells);
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array(
			'ul'				=> $this->strings,
			'options'			=> $this->options,
			'itemsInfo'			=> $this->itemsInfo,
			'formAction'		=> $this->formAction,
			'positions'			=> $this->positions,
			'numbers'			=> $this->numbers,
			'searchesUsernames'	=> $this->searchesUsernames,
			'username'			=> $this->search->username(),
			'groupId'			=> $this->search->groupId(),
			'groups'			=> $this->groups,
			'sortBy'			=> $this->search->sortBy(),
			'showsPostCount'	=> $this->showsPostCount,
			'descending'		=> $this->search->descending(),
			'table'				=> $this->table,
			'headers'			=> $this->headers,
			'rows'				=> $this->rows,
		);
	}
}
