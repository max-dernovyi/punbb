<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

/**
 * Which change of the users selected admin/users.php was asked for, as the
 * step observers saw it selected: the deletion and the ban check for
 * administrators among them at points of their own.
 */
final class SelectedAction {
	private string $checkPoint = '';

	public function select(string $checkPoint): void {
		$this->checkPoint = $checkPoint;
	}

	/** The point of the check for administrators among the users selected; '' before a change is selected. */
	public function checkPoint(): string {
		return $this->checkPoint;
	}
}
