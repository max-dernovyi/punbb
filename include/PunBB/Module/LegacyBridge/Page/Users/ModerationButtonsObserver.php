<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\ModerationButtonsAssembling;
use PunBB\Module\Users\Event\SearchSelected;

/**
 * Runs the point before the buttons below a search's users, with them as $forum_page['mod_options'], read back.
 */
final class ModerationButtonsObserver {
	public const POINTS = array(
		SearchSelected::SHOW_USERS	=> 'aus_show_users_pre_moderation_buttons',
		SearchSelected::FIND_USER	=> 'aus_find_user_pre_moderation_buttons',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ModerationButtonsAssembling $event): void {
		$point = self::POINTS[$event->search()];
		if (!LegacyScope::attached($point))
			return;

		$buttons = array();
		foreach ($event->names() as $name)
			$buttons[$name] = (string) $event->entry($name);

		ForumPage::set('mod_options', $buttons);

		$event->append($this->scope->renderObserved($point, $event));

		$returned = Markers::entries(ForumPage::get('mod_options'));

		foreach ($event->names() as $name)
			if (!array_key_exists($name, $returned))
				$event->remove($name);

		foreach ($returned as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
