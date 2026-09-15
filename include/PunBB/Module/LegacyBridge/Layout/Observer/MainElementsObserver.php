<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\Layout\Event\MainElementsAssembling;

/**
 * Runs hd_main_elements with the regions as $main_elements.
 */
final class MainElementsObserver {
	public function __construct(private readonly ElementsTranslation $elements) {}

	public function observe(MainElementsAssembling $event): void {
		$this->elements->run('hd_main_elements', 'main_elements', $event);
	}
}
