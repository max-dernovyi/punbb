<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ProfileDetailsSelected;
use PunBB\Module\Profile\Event\ProfileRendering;

/**
 * Runs pf_view_details_selected or pf_change_details_about_selected with the
 * member in $user and $forum_page['user_ident'], read back.
 */
final class ProfileDetailsSelectedObserver {
	public const POINTS = array(
		ProfileRendering::DETAILS	=> 'pf_view_details_selected',
		ProfileRendering::ABOUT		=> 'pf_change_details_about_selected',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ProfileDetailsSelected $event): void {
		$point = self::POINTS[$event->page()];
		if (!LegacyScope::attached($point))
			return;

		ProfileState::publish($event->user());
		ForumPage::set('user_ident', array());

		$this->scope->observe($point, $event);

		foreach (Markers::entries(ForumPage::get('user_ident')) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
