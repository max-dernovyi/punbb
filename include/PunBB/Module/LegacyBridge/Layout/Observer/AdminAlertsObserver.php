<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Layout\TemplateProtocol;
use PunBB\Module\Layout\Event\AdminAlertsAssembling;

/**
 * Runs hd_alert with the links as $admod_links and the alerts as $alert_items,
 * and keeps the alerts for the administration's index, which reads them back.
 */
final class AdminAlertsObserver {
	public function __construct(private readonly PointEvaluator $points, private readonly TemplateProtocol $template) {}

	public function observe(AdminAlertsAssembling $event): void {
		$admod_links = array();
		foreach ($event->names() as $name)
			$admod_links[$name] = (string) $event->entry($name);

		$alert_items = array();
		foreach ($event->alertNames() as $name)
			$alert_items[$name] = (string) $event->alert($name);

		if (!LegacyScope::attached('hd_alert'))
		{
			$this->template->keepAlerts($alert_items);
			return;
		}

		$this->points->run('hd_alert', LegacyScope::with(array('admod_links' => &$admod_links, 'alert_items' => &$alert_items)), $event);

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries($admod_links) as $name => $markup)
			$event->set((string) $name, $markup);

		foreach ($event->alertNames() as $name)
			$event->removeAlert($name);

		$alerts = Markers::entries($alert_items);
		foreach ($alerts as $name => $markup)
			$event->setAlert((string) $name, $markup);

		$this->template->keepAlerts($alerts);
	}
}
