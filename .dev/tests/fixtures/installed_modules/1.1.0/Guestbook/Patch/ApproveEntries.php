<?php

declare(strict_types=1);

namespace PunBBModule\Guestbook\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Database\Sql\Connection;

/**
 * The entries a guestbook took before it approved any.
 */
final class ApproveEntries implements DataPatchInterface {
	public function __construct(private readonly Connection $db) {}

	public function apply(int $startAt): PatchStep {
		$approved = $this->db->execute('UPDATE '.$this->db->table('guestbook').' SET approved = 1 WHERE approved = 0');

		return new PatchStep(array(sprintf('Approved %d entries', $approved)));
	}
}
