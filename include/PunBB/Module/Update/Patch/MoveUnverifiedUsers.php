<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Update\Api\BoardDataInterface;

/** The accounts 1.2 marked unverified by group 32000, in group 0. */
final class MoveUnverifiedUsers implements DataPatchInterface {
	public function __construct(private readonly BoardDataInterface $data) {}

	public function apply(int $startAt): PatchStep {
		$this->data->moveUnverifiedUsers();

		return new PatchStep();
	}
}
