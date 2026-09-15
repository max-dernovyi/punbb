<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Event\BanFormRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the ban form's markup points at their positions, with $mode set and
 * the form's counts in $forum_page, read back for the fields that follow.
 */
final class BanFormRenderingObserver {
	public const POINTS = array(
		BanFormRendering::OUTPUT_START				=> 'aba_add_edit_ban_output_start',
		BanFormRendering::PRE_CRITERIA_FIELDSET		=> 'aba_add_edit_ban_pre_criteria_fieldset',
		BanFormRendering::PRE_USERNAME				=> 'aba_add_edit_ban_pre_username',
		BanFormRendering::PRE_EMAIL					=> 'aba_add_edit_ban_pre_email',
		BanFormRendering::PRE_IP					=> 'aba_add_edit_ban_pre_ip',
		BanFormRendering::PRE_MESSAGE				=> 'aba_add_edit_ban_pre_message',
		BanFormRendering::PRE_EXPIRE				=> 'aba_add_edit_ban_pre_expire',
		BanFormRendering::CRITERIA_PRE_FIELDSET_END	=> 'aba_add_edit_ban_criteria_pre_fieldset_end',
		BanFormRendering::CRITERIA_FIELDSET_END		=> 'aba_add_edit_ban_criteria_fieldset_end',
		BanFormRendering::END						=> 'aba_add_edit_ban_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(BanFormRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['mode'] = $event->adding() ? 'add' : 'edit';
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
