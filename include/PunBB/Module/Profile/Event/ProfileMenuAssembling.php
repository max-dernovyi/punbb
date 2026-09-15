<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * The menu of a profile's sections is built, each entry named after its
 * section, before the section asked for is shown: an observer may add, change
 * or drop an entry.
 */
final class ProfileMenuAssembling implements EventInterface {
	use MarkupEntries;

	/**
	 * @param string $section the section asked for
	 * @param bool $ownProfile whether the visitor is the member
	 * @param array<string, string> $entries
	 */
	public function __construct(private readonly ProfileUserInterface $user, private readonly string $section, private readonly bool $ownProfile, array $entries) {
		$this->entries = $entries;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function section(): string {
		return $this->section;
	}

	public function ownProfile(): bool {
		return $this->ownProfile;
	}

	private function accept(string $name): void {}
}
