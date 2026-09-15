<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;

/**
 * The layout every page is shown in: which chrome a page gets, the headers it
 * is sent with, and the chrome template its regions are placed into.
 */
final class Layout {
	public const MAIN = 'main';

	public const ADMIN = 'admin';

	public const HELP = 'help';

	/** The page a redirect shows while the browser is forwarded. */
	public const REDIRECT = 'redirect';

	/** The page the board shows while it is in maintenance mode. */
	public const MAINTENANCE = 'maintenance';

	/** The regions a page outside the board's chrome places, by chrome. */
	public const BARE_REGIONS = array(
		self::REDIRECT		=> array('local', 'head', 'main', 'debug'),
		self::MAINTENANCE	=> array('local', 'head', 'main'),
	);

	private const TEMPLATES = __DIR__.'/../templates/chrome/';

	public function __construct(private readonly EventDispatcher $events, private readonly TemplateRenderer $templates) {}

	/** The chrome of the page $pageId: the administration's, the help's, or the main one. */
	public static function chrome(string $pageId): string {
		if (str_starts_with($pageId, 'admin'))
			return self::ADMIN;

		return $pageId === 'help' ? self::HELP : self::MAIN;
	}

	/** The template file of $chrome. */
	public static function template(string $chrome): string {
		self::regions($chrome);

		return self::TEMPLATES.$chrome.'.phtml';
	}

	/** @return list<string> the regions $chrome's template places */
	public static function regions(string $chrome): array {
		if (in_array($chrome, array(self::MAIN, self::ADMIN, self::HELP), true))
			return PageChrome::REGIONS;

		return self::BARE_REGIONS[$chrome] ?? throw new ChromeException(sprintf('There is no chrome "%s"', $chrome));
	}

	/**
	 * What every page is sent with: no cache may keep it, and it is UTF-8 HTML.
	 *
	 * @return array<string, string> header name => value, in the order they are sent
	 */
	public static function headers(int $now): array {
		return array(
			'Expires'		=> 'Thu, 21 Jul 1977 07:30:00 GMT',
			'Last-Modified'	=> gmdate('D, d M Y H:i:s', $now).' GMT',
			'Cache-Control'	=> 'post-check=0, pre-check=0',
			'Pragma'		=> 'no-cache',
			'Content-type'	=> 'text/html; charset=utf-8',
		);
	}

	/** A page's chrome, built from what $source says as the page reaches each region. */
	public function open(ChromeSourceInterface $source): PageChrome {
		return new PageChrome($this, $source, $this->events);
	}

	/** A page outside the board's chrome, Layout::REDIRECT or Layout::MAINTENANCE, built from what $source says. */
	public function bare(ChromeSourceInterface $source, string $chrome): BareChrome {
		return new BareChrome($this, $source, $chrome);
	}

	/**
	 * $chrome's template with $regions placed; a region not given is empty.
	 *
	 * @param array<string, Html> $regions
	 */
	public function render(ChromeSourceInterface $source, string $chrome, array $regions): string {
		$names = self::regions($chrome);

		$values = array();
		foreach ($names as $name)
			$values[$name] = $regions[$name] ?? new Html('');

		$unknown = array_diff(array_keys($regions), $names);
		if ($unknown !== array())
			throw new ChromeException(sprintf('Chrome %s places no region %s', $chrome, implode(', ', $unknown)));

		if (isset(self::BARE_REGIONS[$chrome]))
			return $this->templates->render(self::template($chrome), $values);

		$values['nav_board_title'] = Html::script($source->board()->title);
		$values['nav_menu_admin'] = Html::script($source->text('Menu admin')->html);
		$values['nav_menu_profile'] = Html::script($source->text('Menu profile')->html);

		return $this->templates->render(self::template($chrome), $values);
	}
}
