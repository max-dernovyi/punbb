<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Help;

use PunBB\Module\Help\Event\HelpRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the help page's markup points at their positions, with the section as $section.
 */
final class HelpRenderingObserver {
	public const POINTS = array(
		HelpRendering::START		=> 'he_main_output_start',
		HelpRendering::TEXT_STYLES	=> 'he_new_bbcode_text_style',
		HelpRendering::LINKS		=> 'he_new_bbcode_link',
		HelpRendering::BBCODE		=> 'he_new_bbcode_section',
		HelpRendering::IMAGES		=> 'he_new_img_section',
		HelpRendering::SECTION		=> 'he_new_section',
		HelpRendering::END			=> 'he_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(HelpRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['section'] = $event->section();

		$event->append($this->scope->renderObserved($point, $event));
	}
}
