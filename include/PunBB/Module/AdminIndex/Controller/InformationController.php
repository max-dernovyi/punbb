<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Controller;

use PunBB\Module\AdminIndex\Api\BoardInformationInterface;
use PunBB\Module\AdminIndex\Api\Data\DatabaseInterface;
use PunBB\Module\AdminIndex\Event\InformationRendering;
use PunBB\Module\AdminIndex\Event\InformationRequested;
use PunBB\Module\AdminIndex\Event\PhpInfoShowing;
use PunBB\Module\AdminIndex\Model\ServerEnvironment;
use PunBB\Module\AdminIndex\View\InformationView;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/index.php: what the board and the server it runs on report, for
 * administrators and moderators; phpinfo() for an administrator who asks.
 */
final class InformationController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/information.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly BoardInformationInterface $information,
		private readonly ServerEnvironment $environment,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new InformationRequested());

		if (!$this->visitor->isModerating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_index');
		$administrator = $this->visitor->isAdministrator();

		if (($request->query['action'] ?? null) === 'phpinfo' && $administrator)
		{
			$this->events->dispatch(new PhpInfoShowing());

			if (!$this->environment->allowsPhpInfo())
				return $this->messages->respond(self::string($strings, 'phpinfo disabled'), json: $request->xhr);

			return new Response($this->environment->phpInfo());
		}

		$updates = $administrator ? $this->updates($strings) : null;
		$load = $this->load($strings);
		$online = $this->information->onlineCount();
		$database = $administrator ? $this->information->database() : null;

		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
		);
		if ($administrator)
			$crumbs[] = new Crumb(self::string($common, 'Start')->html, $this->urls->link('admin_index'));
		$crumbs[] = new Crumb(self::string($common, 'Information')->html, $this->urls->link('admin_index'));

		return $this->pages->respond(new PageHead('admin-information', $crumbs, section: 'start'), fn (ChromeInterface $chrome): array => array(
			'main' => $this->main(new InformationView(array(
				'ain'			=> $strings,
				'alerts'		=> array_values($chrome->alerts()),
				'version'		=> $this->settings->value('o_cur_version'),
				'updates'		=> $updates,
				'load'			=> $load,
				'online'		=> $online,
				'administrator'	=> $administrator,
				'os'			=> $this->environment->operatingSystem(),
				'php'			=> $this->environment->phpVersion(),
				'phpinfo'		=> $this->urls->link('admin_index'),
				'accelerator'	=> $this->accelerator($strings),
			) + $this->database($database)), $administrator),
		));
	}

	private function main(InformationView $view, bool $administrator): Html {
		$this->at($view, InformationRendering::MAIN_OUTPUT_START);

		$this->at($view, InformationRendering::PRE_VERSION);
		$view->numberItem('version');

		$this->at($view, InformationRendering::PRE_COMMUNITY);
		$view->numberItem('community');

		$this->at($view, InformationRendering::PRE_SERVER_LOAD);
		$view->numberItem('server_load');

		$this->at($view, InformationRendering::PRE_ENVIRONMENT);
		if ($administrator)
		{
			$view->numberItem('environment');

			$this->at($view, InformationRendering::PRE_DATABASE);
			$view->numberItem('database');
		}

		$this->at($view, InformationRendering::ITEMS_END);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(InformationRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(InformationRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	private function at(InformationView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** @param array<string, Html> $strings */
	private function updates(array $strings): Html {
		if ($this->settings->enabled('o_check_for_updates'))
			return self::string($strings, 'Check for updates enabled');

		return Html::format('<a href="https://punbb.informer.com/update/?version=%s&amp;hotfixes=%s">%s</a>',
			urlencode($this->settings->value('o_cur_version')), implode(',', array_map(urlencode(...), $this->information->hotfixes())), self::string($strings, 'Check for updates manual'));
	}

	/** @param array<string, Html> $strings */
	private function load(array $strings): Html {
		$averages = $this->environment->loadAverages();
		if ($averages === null)
			return self::string($strings, 'Not available');

		return Html::join(' ', array_map(fn (float $average): Html => $this->formatter->number(round($average, 2), 2), $averages));
	}

	/** @param array<string, Html> $strings */
	private function accelerator(array $strings): Html {
		$accelerator = $this->environment->accelerator();

		return $accelerator !== null ? Html::format('<a href="%s">%s</a>', $accelerator[1], $accelerator[0]) : self::string($strings, 'Not applicable');
	}

	/** @return array{database: string, rows: ?Html, size: ?Html} */
	private function database(?DatabaseInterface $database): array {
		if ($database === null)
			return array('database' => '', 'rows' => null, 'size' => null);

		$rows = $database->rows();
		$size = $database->size();

		if ($size !== null)
		{
			$kilobytes = $size / 1024;
			$size = $kilobytes > 1024 ? new Html($this->formatter->number($kilobytes / 1024, 2)->html.' MB') : new Html($this->formatter->number($kilobytes, 2)->html.' KB');
		}

		return array(
			'database'	=> $database->name().' '.$database->version(),
			'rows'		=> $rows !== null ? $this->formatter->number($rows) : null,
			'size'		=> $size,
		);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
