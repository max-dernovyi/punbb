<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * A page outside the board's chrome, built from what the source says: the
 * theme's head, the stylesheets, and the language attributes of the page.
 */
final class BareChrome implements BareChromeInterface {
	public function __construct(
		private readonly Layout $layout,
		private readonly ChromeSourceInterface $source,
		private readonly string $chrome
	) {
		if (!isset(Layout::BARE_REGIONS[$chrome]))
			throw new ChromeException(sprintf('Chrome "%s" is not a page outside the board\'s chrome', $chrome));
	}

	public function themeHead(): array {
		return $this->source->themeHead();
	}

	public function stylesheets(): Html {
		return $this->source->stylesheets();
	}

	/**
	 * The regions the page gave, with what the chrome adds: the language
	 * attributes, and the table of queries where the chrome shows it.
	 *
	 * @param array<string, Html> $regions
	 * @return array<string, Html>
	 */
	public function regions(array $regions): array {
		if (isset($regions['local']) || isset($regions['debug']))
			throw new ChromeException('A page outside the board\'s chrome places neither its language attributes nor its debug region');

		$source = $this->source;
		$regions = array('local' => Html::format('xml:lang="%s" lang="%s" dir="%s"', $source->languageIdentifier(), $source->languageIdentifier(), $source->languageDirection())) + $regions;

		if (in_array('debug', Layout::BARE_REGIONS[$this->chrome], true))
			$regions['debug'] = $source->savedQueries() ?? new Html('');

		return $regions;
	}

	public function close(array $regions): string {
		return $this->layout->render($this->source, $this->chrome, $this->regions($regions));
	}
}
