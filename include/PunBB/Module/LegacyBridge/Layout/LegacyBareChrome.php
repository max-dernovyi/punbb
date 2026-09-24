<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\Layout\Chrome\BareChrome;
use PunBB\Module\Layout\Chrome\BareChromeInterface;
use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\View\Html;

/**
 * The redirect and the maintenance message as redirect() and
 * maintenance_message() served them: the chrome's template with every region
 * as its marker, or the theme's redirect.tpl or maintenance.tpl, loaded around
 * the points that see its path and its markup, and filled region by region.
 */
final class LegacyBareChrome implements BareChromeInterface {
	/** chrome => the variable a point holds the template in, the theme's template file, and the marker of its main region */
	private const PROTOCOL = array(
		Layout::REDIRECT	=> array('tpl_redir', 'redirect.tpl', 'redir_main'),
		Layout::MAINTENANCE	=> array('tpl_maint', 'maintenance.tpl', 'maint_main'),
	);

	private readonly LegacyTemplate $held;

	private readonly BareChrome $chrome;

	private readonly string $file;

	private readonly string $mainMarker;

	private string $template;

	public function __construct(private readonly Layout $layout, PointEvaluator $points, private readonly string $name) {
		[$variable, $this->file, $this->mainMarker] = self::PROTOCOL[$name] ?? throw new ChromeException(sprintf('Chrome "%s" is not a page outside the board\'s chrome', $name));

		$this->held = new LegacyTemplate($variable);
		$source = new LegacyChromeSource($points, $this->held);
		$this->chrome = $layout->bare($source, $name);

		$theme = LegacyChromeSource::root().'style/'.$source->viewer()->style.'/'.$this->file;
		$own = Layout::template($name);

		$tpl_path = file_exists($theme) ? $theme : $own;
		$path = array('tpl_path' => &$tpl_path);

		if ($name === Layout::REDIRECT && LegacyScope::attached('fn_redirect_pre_template_loaded'))
			$points->run('fn_redirect_pre_template_loaded', LegacyScope::with($path));
		else if ($name === Layout::MAINTENANCE && LegacyScope::attached('fn_maintenance_message_pre_template_loaded'))
			$points->run('fn_maintenance_message_pre_template_loaded', LegacyScope::with($path));

		$template = $tpl_path === $own ? $layout->render($name, $this->markerRegions()) : Markers::markup(\forum_trim((string) file_get_contents(Markers::markup($tpl_path))));
		$loaded = array($variable => &$template, 'tpl_path' => &$tpl_path);

		if ($name === Layout::REDIRECT && LegacyScope::attached('fn_redirect_template_loaded'))
			$points->run('fn_redirect_template_loaded', LegacyScope::with($loaded));
		else if ($name === Layout::MAINTENANCE && LegacyScope::attached('fn_maintenance_message_template_loaded'))
			$points->run('fn_maintenance_message_template_loaded', LegacyScope::with($loaded));

		$this->template = Markers::markup($template);
	}

	/** The theme's script sees the template it may substitute into, and what it leaves there stays. */
	public function themeHead(): array {
		$this->held->html = $this->template;

		try {
			return $this->chrome->themeHead();
		}
		finally {
			$this->template = (string) $this->held->html;
			$this->held->html = null;
		}
	}

	public function stylesheets(): Html {
		return $this->chrome->stylesheets();
	}

	public function close(array $regions): string {
		$unknown = array_diff(array_keys($regions), Layout::regions($this->name));
		if ($unknown !== array())
			throw new ChromeException(sprintf('Chrome %s places no region %s', $this->name, implode(', ', $unknown)));

		$template = $this->template;
		foreach ($this->chrome->regions($regions) as $region => $markup)
			$template = str_replace(Markers::of($this->marker($region)), $markup->html, $template);

		return TemplateProtocol::userIncludes($template, $this->file);
	}

	/** @return array<string, Html> every region as its legacy marker */
	private function markerRegions(): array {
		$regions = array();
		foreach (Layout::regions($this->name) as $region)
			$regions[$region] = new Html(Markers::of($this->marker($region)));

		return $regions;
	}

	private function marker(string $region): string {
		return $region === 'main' ? $this->mainMarker : $region;
	}
}
