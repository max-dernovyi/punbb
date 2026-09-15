<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\PostingPermissionChecking;

/**
 * Runs po_pre_permission_check with the moderators as $mods_array, whether the
 * visitor is subscribed to the topic as $is_subscribed and whether they
 * moderate as $forum_page['is_admmod'], read back.
 */
final class PostingPermissionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostingPermissionChecking $event): void {
		$mods_array = array();
		foreach ($event->location()->moderators() as $moderator)
			$mods_array[$moderator->username()] = $moderator->userId();

		$GLOBALS['mods_array'] = $mods_array;
		$GLOBALS['is_subscribed'] = $event->location()->topicId() > 0 && $event->location()->subscribed();
		ForumPage::set('is_admmod', $event->moderating());

		if (!LegacyScope::attached('po_pre_permission_check'))
			return;

		$this->scope->observe('po_pre_permission_check', $event);

		$event->treatAsModerating(!empty(ForumPage::get('is_admmod')));
	}
}
