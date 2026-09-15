<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ProfileMenuAssembling;

/**
 * Runs pf_change_details_modify_main_menu with the menu in
 * $forum_page['main_menu'], read back, the section in $section and the member
 * in $user.
 */
final class ProfileMenuObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ProfileMenuAssembling $event): void {
		ProfileState::publish($event->user());
		$GLOBALS['section'] = $event->section();

		$menu = array();
		foreach ($event->names() as $name)
			$menu[$name] = (string) $event->entry($name);

		ForumPage::set('main_menu', $menu);

		if (!LegacyScope::attached('pf_change_details_modify_main_menu'))
			return;

		$this->scope->observe('pf_change_details_modify_main_menu', $event);

		$returned = Markers::entries(ForumPage::get('main_menu'));

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach ($returned as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
