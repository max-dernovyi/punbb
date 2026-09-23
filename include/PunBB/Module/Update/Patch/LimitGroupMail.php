<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Update\Api\BoardDataInterface;

/** The guests kept from mailing, and the administrators, guests and moderators from waiting between mails. */
final class LimitGroupMail implements DataPatchInterface {
	public function __construct(private readonly BoardDataInterface $data) {}

	public function apply(int $startAt): PatchStep {
		$this->data->limitGroupMail();

		return new PatchStep();
	}
}
