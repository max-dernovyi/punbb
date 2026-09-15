<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The extensions page was asked for no install, uninstall or switch, before
 * it lists the extensions or the hotfixes: an observer may answer a section of its own.
 */
final class ExtensionsActionRequested implements EventInterface {
	/** @param string $section the section asked for; '' when none was */
	public function __construct(private readonly string $section) {}

	public function section(): string {
		return $this->section;
	}
}
