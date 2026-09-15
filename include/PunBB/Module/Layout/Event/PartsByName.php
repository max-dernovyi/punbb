<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use InvalidArgumentException;
use PunBB\Module\Layout\View\Parts;

/**
 * An event carrying several groups of named markup, each reached by the name
 * of its group: setting a name that is there keeps its place, setting a new
 * one adds it at the end, removing one drops it.
 */
trait PartsByName {
	/** @var array<string, Parts> group => its parts */
	private array $parts = array();

	/** @return list<string> the names in $group, in order */
	public function names(string $group): array {
		return $this->group($group)->names();
	}

	/** The markup under $name in $group, null when nothing is. */
	public function entry(string $group, string $name): ?string {
		return $this->group($group)->entry($name);
	}

	public function set(string $group, string $name, string $markup): void {
		$this->group($group)->set($name, $markup);
	}

	public function remove(string $group, string $name): void {
		$this->group($group)->remove($name);
	}

	private function group(string $group): Parts {
		return $this->parts[$group] ?? throw new InvalidArgumentException(sprintf('%s has no parts "%s"', static::class, $group));
	}
}
