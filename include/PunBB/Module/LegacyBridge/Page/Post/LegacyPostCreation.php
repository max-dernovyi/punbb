<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Post\Api\Data\NewPostInterface;
use PunBB\Module\Post\Creation\CreatedTopic;
use PunBB\Module\Post\Creation\PostCreationInterface;

/**
 * add_post() and add_topic() of include/functions.php, with the extension code
 * attached to them; the post is left in $post_info, with any key a point added
 * to it, and its ids in $new_pid and $new_tid.
 */
final class LegacyPostCreation implements PostCreationInterface {
	public function reply(NewPostInterface $post): int {
		$post_info = NewPostRows::row($post, $GLOBALS['post_info'] ?? null);
		$new_pid = 0;

		\add_post($post_info, $new_pid);

		$GLOBALS['post_info'] = $post_info;
		$GLOBALS['new_pid'] = $new_pid;

		return (int) Markers::markup($new_pid);
	}

	public function topic(NewPostInterface $post): CreatedTopic {
		$post_info = NewPostRows::row($post, $GLOBALS['post_info'] ?? null);
		$new_tid = $new_pid = 0;

		\add_topic($post_info, $new_tid, $new_pid);

		$GLOBALS['post_info'] = $post_info;
		$GLOBALS['new_tid'] = $new_tid;
		$GLOBALS['new_pid'] = $new_pid;

		return new CreatedTopic((int) Markers::markup($new_tid), (int) Markers::markup($new_pid));
	}
}
