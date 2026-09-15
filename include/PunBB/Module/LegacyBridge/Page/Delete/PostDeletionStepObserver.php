<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Event\PostDeletionStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of a deletion.
 */
final class PostDeletionStepObserver {
	public const POINTS = array(
		PostDeletionStep::SELECTED		=> 'dl_post_selected',
		PostDeletionStep::SUBMITTED		=> 'dl_form_submitted',
		PostDeletionStep::TOPIC_DELETED	=> 'dl_topic_deleted_pre_redirect',
		PostDeletionStep::POST_DELETED	=> 'dl_post_deleted_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostDeletionStep $event): void {
		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
