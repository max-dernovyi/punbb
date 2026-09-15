<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * An action asked of the search page is checked against the actions it knows:
 * an observer may add an action of its own, or decide the answer outright.
 */
final class SearchActionValidating implements EventInterface {
	private ?bool $decision = null;

	/** @param list<string> $actions */
	public function __construct(private readonly string $action, private array $actions) {}

	public function action(): string {
		return $this->action;
	}

	/** @return list<string> */
	public function actions(): array {
		return $this->actions;
	}

	/** @param list<string> $actions */
	public function setActions(array $actions): void {
		$this->actions = $actions;
	}

	public function decide(bool $valid): void {
		$this->decision = $valid;
	}

	/** Whether the action is valid, decided outright; null when the list decides. */
	public function decision(): ?bool {
		return $this->decision;
	}
}
