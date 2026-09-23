<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;

/** Each topic's first post, recorded on a 1.2 board: 1.3 added the column. */
final class RecordFirstPosts implements DataPatchInterface {
	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly BoardDataInterface $data
	) {}

	public function apply(int $startAt): PatchStep {
		if (BoardOptions::from12($this->settings))
			$this->data->recordFirstPosts();

		return new PatchStep();
	}
}
