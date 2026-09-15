<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Censoring;

use PunBB\Module\Censoring\Event\CensorChangeStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of changing a censored word, with the word in
 * $id, $search_for and $replace_with, as admin/censoring.php held it.
 */
final class CensorChangeObserver {
	public const POINTS = array(
		CensorChangeStep::ADDING	=> 'acs_add_word_form_submitted',
		CensorChangeStep::ADDED		=> 'acs_add_word_pre_redirect',
		CensorChangeStep::UPDATING	=> 'acs_update_form_submitted',
		CensorChangeStep::UPDATED	=> 'acs_update_pre_redirect',
		CensorChangeStep::REMOVING	=> 'acs_remove_form_submitted',
		CensorChangeStep::REMOVED	=> 'acs_remove_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(CensorChangeStep $event): void {
		$censor = $event->censor();

		if (!in_array($event->step(), array(CensorChangeStep::ADDING, CensorChangeStep::ADDED), true))
			$GLOBALS['id'] = $censor->id();

		if (!in_array($event->step(), array(CensorChangeStep::REMOVING, CensorChangeStep::REMOVED), true))
		{
			$GLOBALS['search_for'] = $censor->searchFor();
			$GLOBALS['replace_with'] = $censor->replaceWith();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
