<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Settings;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Settings\Event\SettingsSectionRequested;

/**
 * Runs aop_new_section with the section as $section. Code there that answers a
 * section of its own includes header.php and buffers its markup, and the page
 * ended it: aop_end runs, the markup fills the template's main region and
 * footer.php sends the page.
 */
final class SettingsSectionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(SettingsSectionRequested $event): void {
		$GLOBALS['section'] = $event->section();

		$opened = isset($GLOBALS['tpl_main']);

		$this->scope->observe('aop_new_section', $event);

		if ($opened || !isset($GLOBALS['tpl_main']))
			return;

		$this->scope->observe('aop_end', $event);

		$GLOBALS['tpl_main'] = str_replace(Markers::of('main'), Markers::markup(\forum_trim((string) ob_get_contents())), Markers::markup($GLOBALS['tpl_main']));
		ob_end_clean();

		LegacyScope::requireGlobally(LegacyChromeSource::root().'footer.php');
	}
}
