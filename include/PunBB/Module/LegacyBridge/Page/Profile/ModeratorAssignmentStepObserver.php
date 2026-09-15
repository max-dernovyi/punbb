<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ModeratorAssignmentStep;

/**
 * Runs the point at each step of choosing the forums a member moderates, with
 * the member in $user and, once stored, the forums in $moderator_in.
 */
final class ModeratorAssignmentStepObserver {
	public const POINTS = array(
		ModeratorAssignmentStep::SUBMITTED	=> 'pf_forum_moderators_form_submitted',
		ModeratorAssignmentStep::UPDATED	=> 'pf_forum_moderators_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly ProfileFlow $flow) {}

	public function observe(ModeratorAssignmentStep $event): void {
		$this->flow->select(ProfileFlow::MODERATORS);

		ProfileState::publish($event->user());

		if ($event->step() === ModeratorAssignmentStep::UPDATED)
			$GLOBALS['moderator_in'] = $event->forumIds();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
