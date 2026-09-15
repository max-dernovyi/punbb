<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\View\Html;

/**
 * A module's page between its header and its footer, as a page script held
 * it: $tpl_main, which extension code on the page's points may still change.
 */
final class LegacyPageChrome implements ChromeInterface {
	public function __construct(private readonly TemplateProtocol $protocol, string $template) {
		$GLOBALS['tpl_main'] = $template;
	}

	public function close(array $content): string {
		$template = Markers::markup($GLOBALS['tpl_main'] ?? '');

		foreach ($content as $name => $markup)
			$template = str_replace(Markers::of($name), $markup->html, $template);

		return $this->protocol->footer($template);
	}

	public function inlineScript(string $code): void {
		$loader = $GLOBALS['forum_loader'] ?? null;
		if (!$loader instanceof \Loader)
			throw new ChromeException('The legacy bootstrap has no loader in $forum_loader');

		$loader->add_js($code, array('type' => 'inline'));
	}

	public function alerts(): array {
		$alerts = array();
		foreach ($this->protocol->alertItems() as $name => $markup)
			$alerts[(string) $name] = new Html($markup);

		return $alerts;
	}
}
