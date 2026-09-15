<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Help;

use PunBB\Module\Help\Event\SmiliesListing;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs he_pre_smile_display over the parser's $smilies, which the help page lists as the hook leaves it.
 */
final class SmiliesListingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(SmiliesListing $event): void {
		if (!LegacyScope::attached('he_pre_smile_display'))
			return;

		$smilies = array();
		foreach ($event->texts() as $text)
			$smilies[$text] = (string) $event->image($text);

		$GLOBALS['smilies'] = $smilies;
		$GLOBALS['smiley_groups'] = array();

		$this->scope->observe('he_pre_smile_display', $event);

		$listed = Markers::entries($GLOBALS['smilies'] ?? null);

		foreach ($event->texts() as $text)
			if (!isset($listed[$text]))
				$event->remove($text);

		foreach ($listed as $text => $image)
			$event->set((string) $text, $image);
	}
}
