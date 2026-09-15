<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Censoring;

use PunBB\Module\Censoring\Event\CensoredWordRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of a stored word's fieldset with the word as $cur_word,
 * its place in the list as $censor_key and the form's counts in $forum_page,
 * read back for the fields that follow.
 */
final class CensoredWordObserver {
	public const POINTS = array(
		CensoredWordRendering::PRE_EDIT_WORD_FIELDSET		=> 'acs_pre_edit_word_fieldset',
		CensoredWordRendering::PRE_EDIT_SEARCH_FOR			=> 'acs_pre_edit_search_for',
		CensoredWordRendering::PRE_EDIT_REPLACE_WITH		=> 'acs_pre_edit_replace_with',
		CensoredWordRendering::PRE_EDIT_SUBMIT				=> 'acs_pre_edit_submit',
		CensoredWordRendering::PRE_EDIT_WORD_FIELDSET_END	=> 'acs_pre_edit_word_fieldset_end',
		CensoredWordRendering::EDIT_WORD_FIELDSET_END		=> 'acs_edit_word_fieldset_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(CensoredWordRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$censor = $event->censor();
		$GLOBALS['censor_key'] = $event->number() - 1;
		$GLOBALS['cur_word'] = array('id' => $censor->id(), 'search_for' => $censor->searchFor(), 'replace_with' => $censor->replaceWith());

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
