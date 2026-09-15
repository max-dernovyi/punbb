<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Event\BanAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders aba_view_ban_pre_display with the ban as $cur_ban and its block's
 * lines and creator in $forum_page, both read back.
 */
final class BanAssemblingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(BanAssembling $event): void {
		if (!LegacyScope::attached('aba_view_ban_pre_display'))
			return;

		$GLOBALS['cur_ban'] = BanRows::row($event->ban());

		$lines = array();
		foreach ($event->names() as $name)
			$lines[$name] = (string) $event->entry($name);

		ForumPage::set('ban_info', $lines);
		ForumPage::set('ban_creator', $event->creator());
		ForumPage::set('item_num', $event->number() - 1);

		$event->append($this->scope->renderObserved('aba_view_ban_pre_display', $event));

		$lines = Markers::entries(ForumPage::get('ban_info'));
		foreach ($event->names() as $name)
			if (!isset($lines[$name]))
				$event->remove($name);

		foreach ($lines as $name => $markup)
			$event->set((string) $name, $markup);

		$event->setCreator(Markers::markup(ForumPage::get('ban_creator')));
	}
}
