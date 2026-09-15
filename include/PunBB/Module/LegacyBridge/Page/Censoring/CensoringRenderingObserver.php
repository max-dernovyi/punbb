<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Censoring;

use PunBB\Module\Censoring\Event\CensoringRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the censoring page's markup points outside the list of words at
 * their positions, with the forms' counts in $forum_page, read back for the
 * fields that follow.
 */
final class CensoringRenderingObserver {
	public const POINTS = array(
		CensoringRendering::MAIN_OUTPUT_START			=> 'acs_main_output_start',
		CensoringRendering::PRE_ADD_WORD_FIELDSET		=> 'acs_pre_add_word_fieldset',
		CensoringRendering::PRE_ADD_SEARCH_FOR			=> 'acs_pre_add_search_for',
		CensoringRendering::PRE_ADD_REPLACE_WITH		=> 'acs_pre_add_replace_with',
		CensoringRendering::PRE_ADD_SUBMIT				=> 'acs_pre_add_submit',
		CensoringRendering::PRE_ADD_WORD_FIELDSET_END	=> 'acs_pre_add_word_fieldset_end',
		CensoringRendering::ADD_WORD_FIELDSET_END		=> 'acs_add_word_fieldset_end',
		CensoringRendering::END							=> 'acs_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(CensoringRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
