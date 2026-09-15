<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\AvatarDeletionStep;

/**
 * Runs the point at each step of deleting a member's avatar, with the member in $user.
 */
final class AvatarDeletionStepObserver {
	public const POINTS = array(
		AvatarDeletionStep::SELECTED	=> 'pf_delete_avatar_selected',
		AvatarDeletionStep::DELETED		=> 'pf_delete_avatar_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(AvatarDeletionStep $event): void {
		ProfileState::publish($event->user());

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
