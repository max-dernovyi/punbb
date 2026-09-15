<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Page;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MaintenanceShowing;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;

/**
 * The message a board in maintenance mode shows everyone but its administrators,
 * sent as 503 so search engines keep what they indexed.
 */
final class MaintenancePage {
	private const TEMPLATE = __DIR__.'/../templates/maintenance.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly ChromeFactoryInterface $chromes,
		private readonly TemplateRenderer $templates,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings
	) {}

	/** The maintenance message; null when an observer lets the visitor in. */
	public function respond(): ?Response {
		$showing = new MaintenanceShowing();
		$this->events->dispatch($showing);

		if ($showing->isLetIn())
			return null;

		// The message is the administrator's markup; runs of tabs and spaces keep their width
		$message = str_replace(array("\t\t", '  ', '  '), array('&#160; &#160; ', '&#160; ', ' &#160;'), $this->settings->value('o_maintenance_message'));

		$chrome = $this->chromes->bare(Layout::MAINTENANCE);

		$lines = array();
		foreach ($chrome->themeHead() as $line)
			$lines[] = $line->html;

		$main = $this->templates->render(self::TEMPLATE, array(
			'heading'	=> $this->language->text('common', 'Maintenance mode'),
			'message'	=> new Html($message),
		));

		return new Response($chrome->close(array(
			'head'	=> (new Html(implode("\n", $lines).$chrome->stylesheets()->html))->trim(),
			'main'	=> new Html("\t".(new Html($main))->trim()->html),
		)), 503, array('Content-type' => 'text/html; charset=utf-8'));
	}
}
