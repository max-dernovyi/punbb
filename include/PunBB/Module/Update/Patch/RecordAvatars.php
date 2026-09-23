<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;

/**
 * The avatars 1.2 kept as files alone, recorded on their accounts; an avatar
 * that is no image, or larger than the board allows, is deleted.
 */
final class RecordAvatars implements DataPatchInterface {
	private const TYPES = array('gif' => 1, 'jpg' => 2, 'png' => 3);

	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly BoardDataInterface $data,
		private readonly BoardFilesInterface $files
	) {}

	public function apply(int $startAt): PatchStep {
		$options = BoardOptions::of($this->settings);

		foreach ($this->files->avatars() as $avatar)
		{
			if (preg_match('/^(\d+)\.(png|gif|jpg)/', $avatar, $matches) !== 1)
				continue;

			$userId = intval($matches[1], 10);
			if ($userId < 2)
				continue;

			$size = $this->files->avatarSize($avatar);
			if ($size === null || $size[0] > (int) ($options['o_avatars_width'] ?? 0) || $size[1] > (int) ($options['o_avatars_height'] ?? 0))
				$this->files->removeAvatar($avatar);
			else
				$this->data->storeAvatar($userId, self::TYPES[$matches[2]], $size[0], $size[1]);
		}

		return new PatchStep();
	}
}
