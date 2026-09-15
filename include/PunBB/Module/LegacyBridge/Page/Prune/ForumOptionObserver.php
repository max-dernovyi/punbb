<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Prune;

use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Prune\Event\ForumOptionRendering;

/**
 * Renders the points around a forum's option with the forum as $forum and the
 * category of the group the list is in as $cur_category, as admin/prune.php left them.
 */
final class ForumOptionObserver {
	public const POINTS = array(
		ForumOptionRendering::START	=> 'apr_pre_prune_forum_loop_start',
		ForumOptionRendering::END	=> 'apr_pre_prune_forum_loop_end',
	);

	public function __construct(private readonly PageScope $scope, private readonly KeptRows $rows) {}

	public function observe(ForumOptionRendering $event): void {
		$forum = $event->forum();

		$GLOBALS['forum'] = $this->rows->row($forum) ?? array(
			'cid'			=> $forum->categoryId(),
			'cat_name'		=> $forum->categoryName(),
			'fid'			=> $forum->id(),
			'forum_name'	=> $forum->name(),
		);

		if ($event->position() === ForumOptionRendering::END)
			$GLOBALS['cur_category'] = $forum->categoryId();

		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
