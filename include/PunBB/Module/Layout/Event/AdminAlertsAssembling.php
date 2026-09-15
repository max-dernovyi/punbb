<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The administrator's alerts and the moderation links beside the navigation,
 * before they are placed. Both are named entries of markup: the links through
 * the entry methods, the alerts through their own.
 */
final class AdminAlertsAssembling implements EventInterface {
	use MarkupEntries;

	/**
	 * @param array<string, string> $links
	 * @param array<string, string> $alerts
	 */
	public function __construct(array $links, private array $alerts) {
		$this->entries = $links;
	}

	/** @return list<string> the alerts' names, in order */
	public function alertNames(): array {
		return array_map(strval(...), array_keys($this->alerts));
	}

	public function alert(string $name): ?string {
		return $this->alerts[$name] ?? null;
	}

	public function setAlert(string $name, string $markup): void {
		$this->alerts[$name] = $markup;
	}

	public function removeAlert(string $name): void {
		unset($this->alerts[$name]);
	}

	private function accept(string $name): void {}
}
