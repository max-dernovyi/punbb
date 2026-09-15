<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\SubscriptionStep;

/**
 * Runs the point at each step of subscribing or unsubscribing, with the topic
 * in $topic_id and its subject in $subject, or the forum in $forum_id and its
 * name in $forum_name, once it is changed.
 */
final class SubscriptionStepObserver {
	/** target => subscribing or not => step => point */
	public const POINTS = array(
		SubscriptionStep::TOPIC	=> array(
			1	=> array(SubscriptionStep::SELECTED => 'mi_subscribe_selected', SubscriptionStep::CHANGED => 'mi_subscribe_pre_redirect'),
			0	=> array(SubscriptionStep::SELECTED => 'mi_unsubscribe_selected', SubscriptionStep::CHANGED => 'mi_unsubscribe_pre_redirect'),
		),
		SubscriptionStep::FORUM	=> array(
			1	=> array(SubscriptionStep::SELECTED => 'mi_forum_subscribe_selected', SubscriptionStep::CHANGED => 'mi_forum_subscribe_pre_redirect'),
			0	=> array(SubscriptionStep::SELECTED => 'mi_forum_unsubscribe_selected', SubscriptionStep::CHANGED => 'mi_forum_unsubscribe_pre_redirect'),
		),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(SubscriptionStep $event): void {
		$topic = $event->target() === SubscriptionStep::TOPIC;

		$GLOBALS[$topic ? 'topic_id' : 'forum_id'] = $event->id();

		if ($event->step() === SubscriptionStep::CHANGED)
			$GLOBALS[$topic ? 'subject' : 'forum_name'] = $event->name();

		$this->scope->observe(self::POINTS[$event->target()][$event->subscribing() ? 1 : 0][$event->step()], $event);
	}
}
