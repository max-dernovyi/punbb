<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * What a profile shows about the member is about to be read: to a visitor who
 * may not edit it, or in its introduction. An observer may add named markup
 * identifying the member, which goes before their name, avatar and title.
 */
final class ProfileDetailsSelected implements EventInterface {
	use MarkupEntries;

	/** @param string $page ProfileRendering::DETAILS or ::ABOUT */
	public function __construct(private readonly string $page, private readonly ProfileUserInterface $user) {
		if (!in_array($page, array(ProfileRendering::DETAILS, ProfileRendering::ABOUT), true))
			throw new InvalidArgumentException(sprintf('The profile shows no details on its page "%s"', $page));
	}

	public function page(): string {
		return $this->page;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	private function accept(string $name): void {}
}
