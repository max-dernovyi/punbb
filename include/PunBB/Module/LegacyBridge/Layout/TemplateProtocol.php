<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\Chrome\PageChrome;
use PunBB\Module\Layout\View\Html;

/**
 * A page built on $tpl_main, as header.php and footer.php served it: the header
 * hands back the chrome as a template whose page markers are still there to
 * fill, the footer fills the rest and hands back the page.
 *
 * The template is the chrome's own template with every region rendered as its
 * marker, or a legacy theme's .tpl. Either way the page, the theme's stylesheet
 * script and extension code see the string they always saw.
 */
final class TemplateProtocol {
	/** A legacy theme's template may pull in files from here with <!-- forum_include "file" -->. */
	private const USER_INCLUDES = 'include/user/';

	private readonly LegacyTemplate $template;

	private ?LegacyChromeSource $source = null;

	private ?PageChrome $chrome = null;

	/** @var array<string, string> marker => markup, for the markers extension code added to the legacy element arrays */
	private array $markers = array();

	/** @var array<array-key, string> the administrator's alerts, as the header left them */
	private array $alerts = array();

	public function __construct(private readonly Layout $layout, private readonly PointEvaluator $points) {
		$this->template = new LegacyTemplate();
	}

	/** The template with the header's regions in it, and the markers of the page's own regions and the footer's. */
	public function header(): string {
		foreach (Layout::headers(time()) as $name => $value)
			header($name.': '.$value, $name !== 'Cache-Control');

		// An undefined FORUM_PAGE fails here, where header.php failed on it
		$chrome = Layout::chrome(Markers::markup(constant('FORUM_PAGE')));

		$source = $this->source();
		$style = $source->viewer()->style;
		$theme = LegacyChromeSource::root().'style/'.$style.'/'.$chrome.'.tpl';

		$tpl_path = $style !== 'Oxygen' && file_exists($theme) ? $theme : Layout::template($chrome);
		if (LegacyScope::attached('hd_pre_template_loaded'))
			$this->points->run('hd_pre_template_loaded', LegacyScope::with(array('tpl_path' => &$tpl_path)));

		$tpl_main = $tpl_path === Layout::template($chrome) ? $this->layout->render($chrome, self::markerRegions()) : (string) file_get_contents($tpl_path);
		if (LegacyScope::attached('hd_template_loaded'))
			$this->points->run('hd_template_loaded', LegacyScope::with(array('tpl_main' => &$tpl_main, 'tpl_path' => &$tpl_path)));

		$this->template->html = self::userIncludes($tpl_main, 'main.tpl');
		$this->chrome = $this->layout->open($source);

		try {
			$regions = $this->chrome->header();
			$tpl_main = (string) $this->template->html;
		}
		finally {
			$this->template->html = null;
		}

		foreach ($regions as $name => $markup)
			$tpl_main = str_replace(Markers::of($name), $markup->html, $tpl_main);

		foreach ($this->markers as $marker => $markup)
			$tpl_main = str_replace($marker, $markup, $tpl_main);

		if (!defined('FORUM_HEADER'))
			define('FORUM_HEADER', 1);

		return $tpl_main;
	}

	/** The page: the footer's regions filled into $tpl_main, as the last extension code there leaves it. */
	public function footer(string $tpl_main): string {
		$this->chrome ??= $this->layout->open($this->source());

		foreach ($this->chrome->footer() as $name => $markup)
			$tpl_main = str_replace(Markers::of($name), $markup->html, $tpl_main);

		return $this->chrome->finish($tpl_main);
	}

	/** @return array<array-key, string> the alerts header.php left in $alert_items for the administration's index */
	public function alertItems(): array {
		return $this->alerts;
	}

	/** @param array<array-key, string> $alerts */
	public function keepAlerts(array $alerts): void {
		$this->alerts = $alerts;
	}

	/** A marker extension code added to an element array, filled wherever a template carries it. */
	public function keepMarker(string $marker, string $markup): void {
		$this->markers[$marker] = $markup;
	}

	private function source(): LegacyChromeSource {
		return $this->source ??= new LegacyChromeSource($this->points, $this->template);
	}

	/** @return array<string, Html> every region as its marker */
	private static function markerRegions(): array {
		$regions = array();
		foreach (PageChrome::REGIONS as $name)
			$regions[$name] = new Html(Markers::of($name));

		return $regions;
	}

	/** $template with each <!-- forum_include "file" --> replaced by what include/user/file prints. */
	public static function userIncludes(string $template, string $name): string {
		while (preg_match('#<!-- ?forum_include "([^/\\\\]*?)" ?-->#', $template, $include) === 1)
		{
			$file = LegacyChromeSource::root().self::USER_INCLUDES.$include[1];

			if (!file_exists($file))
				\error('Unable to process user include &lt;!-- forum_include "'.Markers::markup(\forum_htmlencode($include[1])).'" --&gt; from template '.$name.'.<br />There is no such file in folder /include/user/', __FILE__, __LINE__);

			ob_start();
			LegacyScope::include($file);
			$template = str_replace($include[0], (string) ob_get_clean(), $template);
		}

		return $template;
	}
}
