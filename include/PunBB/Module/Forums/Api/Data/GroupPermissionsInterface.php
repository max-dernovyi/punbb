<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * A group's defaults on the board, and the permissions a forum stores for it:
 * null where the forum stores none.
 */
interface GroupPermissionsInterface extends GroupDefaultsInterface {
	public function groupTitle(): string;

	public function readForum(): ?bool;

	public function postReplies(): ?bool;

	public function postTopics(): ?bool;
}
