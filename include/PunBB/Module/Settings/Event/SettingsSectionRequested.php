<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A section the settings do not have was asked for, before the request is
 * refused: an observer may answer a section of its own.
 */
final class SettingsSectionRequested implements EventInterface {
	public function __construct(private readonly string $section) {}

	public function section(): string {
		return $this->section;
	}
}
