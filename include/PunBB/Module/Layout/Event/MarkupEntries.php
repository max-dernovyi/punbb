<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

/**
 * Named pieces of markup in order: setting a name that is there keeps its
 * place, setting a new one adds it at the end, removing one drops it.
 */
trait MarkupEntries {
	/** @var array<string, string> name => markup */
	private array $entries = array();

	/** @return list<string> the names, in order */
	public function names(): array {
		return array_map(strval(...), array_keys($this->entries));
	}

	/** The markup under $name, null when nothing is. */
	public function entry(string $name): ?string {
		return $this->entries[$name] ?? null;
	}

	public function set(string $name, string $markup): void {
		$this->accept($name);
		$this->entries[$name] = $markup;
	}

	public function remove(string $name): void {
		unset($this->entries[$name]);
	}

	/** A name the event does not carry is refused before it is set. */
	abstract private function accept(string $name): void;
}
