<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Layout\TemplateProtocol;
use PunBB\Module\Layout\Event\VisitElementsAssembling;

/**
 * Runs hd_visit_elements with the welcome in $visit_elements and the links as $visit_links.
 */
final class VisitElementsObserver {
	private const WELCOME = '<!-- forum_welcome -->';

	public function __construct(private readonly PointEvaluator $points, private readonly TemplateProtocol $template) {}

	public function observe(VisitElementsAssembling $event): void {
		if (!LegacyScope::attached('hd_visit_elements'))
			return;

		$visit_elements = $event->welcome() !== null ? array(self::WELCOME => (string) $event->welcome()) : array();

		$visit_links = array();
		foreach ($event->names() as $name)
			$visit_links[$name] = (string) $event->entry($name);

		$this->points->run('hd_visit_elements', LegacyScope::with(array('visit_elements' => &$visit_elements, 'visit_links' => &$visit_links)), $event);

		$elements = Markers::entries($visit_elements);
		$event->replaceWelcome($elements[self::WELCOME] ?? null);
		unset($elements[self::WELCOME]);

		foreach ($elements as $marker => $markup)
			$this->template->keepMarker((string) $marker, $markup);

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries($visit_links) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
