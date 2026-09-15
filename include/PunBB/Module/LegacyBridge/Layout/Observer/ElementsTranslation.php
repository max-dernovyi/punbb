<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Layout\TemplateProtocol;
use PunBB\Module\Layout\Event\BoardElementsAssembling;
use PunBB\Module\Layout\Event\MainElementsAssembling;

/**
 * An element array of header.php — $gen_elements, $main_elements — built from
 * the event's regions and keyed by marker, handed to the point, and read back:
 * a region's marker left out is a region left out, a marker the event does
 * not carry is kept for a template that has it.
 */
final class ElementsTranslation {
	public function __construct(private readonly PointEvaluator $points, private readonly TemplateProtocol $template) {}

	public function run(string $point, string $variable, BoardElementsAssembling|MainElementsAssembling $event): void {
		if (!LegacyScope::attached($point))
			return;

		$names = $event::REGIONS;

		$elements = array();
		foreach ($names as $name)
			if ($event->entry($name) !== null)
				$elements[Markers::of($name)] = (string) $event->entry($name);

		$this->points->run($point, LegacyScope::with(array($variable => &$elements)), $event);

		$returned = Markers::entries($elements);

		foreach ($names as $name)
		{
			if (array_key_exists(Markers::of($name), $returned))
				$event->set($name, $returned[Markers::of($name)]);
			else
				$event->remove($name);

			unset($returned[Markers::of($name)]);
		}

		foreach ($returned as $marker => $markup)
			$this->template->keepMarker((string) $marker, $markup);
	}
}
