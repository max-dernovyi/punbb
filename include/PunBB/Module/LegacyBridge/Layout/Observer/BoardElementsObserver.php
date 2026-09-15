<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\Layout\Event\BoardElementsAssembling;

/**
 * Runs hd_gen_elements with the regions as $gen_elements.
 */
final class BoardElementsObserver {
	public function __construct(private readonly ElementsTranslation $elements) {}

	public function observe(BoardElementsAssembling $event): void {
		$this->elements->run('hd_gen_elements', 'gen_elements', $event);
	}
}
