<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Register\Event\RulesRendering;

/**
 * Renders the markup points of the forum rules at their positions, with the
 * form's counts in $forum_page, read back for the fields that follow.
 */
final class RulesRenderingObserver {
	public const POINTS = array(
		RulesRendering::OUTPUT_START		=> 'rg_rules_output_start',
		RulesRendering::PRE_GROUP			=> 'rg_rules_pre_group',
		RulesRendering::PRE_AGREE_CHECKBOX	=> 'rg_rules_pre_agree_checkbox',
		RulesRendering::PRE_GROUP_END		=> 'rg_rules_pre_group_end',
		RulesRendering::GROUP_END			=> 'rg_rules_group_end',
		RulesRendering::END					=> 'rg_rules_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RulesRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
