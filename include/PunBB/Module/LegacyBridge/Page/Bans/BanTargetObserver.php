<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Event\BanTargetSelecting;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point where the ban form's target is chosen, with it in the
 * variables admin/bans.php held it in: $add_ban and $user_id, $ban_user, or $ban_id.
 */
final class BanTargetObserver {
	public const POINTS = array(
		BanTargetSelecting::USER		=> 'aba_add_ban_selected',
		BanTargetSelecting::USERNAME	=> 'aba_add_ban_form_submitted',
		BanTargetSelecting::BAN			=> 'aba_edit_ban_selected',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(BanTargetSelecting $event): void {
		match ($event->target()) {
			BanTargetSelecting::USER		=> $GLOBALS['add_ban'] = $GLOBALS['user_id'] = $event->userId(),
			BanTargetSelecting::USERNAME	=> $GLOBALS['ban_user'] = $event->username(),
			default							=> $GLOBALS['ban_id'] = $event->banId(),
		};

		$this->scope->observe(self::POINTS[$event->target()], $event);
	}
}
