<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Creation;

use PunBB\Module\Post\Api\Data\NewPostInterface;

/**
 * Stores a post with everything it changes: the topic's and the forum's
 * counts and last posts, the poster's post count, the search index and the
 * subscriptions mailed. Behind it are points of their own that extension code
 * also reaches by calling add_post() and add_topic(): the module declares it,
 * the bootstrap's side wires it.
 */
interface PostCreationInterface {
	/** Stores $post as a reply to its topic, and returns the post's id. */
	public function reply(NewPostInterface $post): int;

	/** Stores $post as the first post of a new topic in its forum. */
	public function topic(NewPostInterface $post): CreatedTopic;
}
