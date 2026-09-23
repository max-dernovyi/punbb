<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Model\Setting;

/**
 * Room for more than one moderator group: a 1.2 board's moderators become
 * group 4, and the options 1.2 kept for every moderator become permissions of
 * each moderating group.
 */
final class ModeratorGroups implements DataPatchInterface {
	private const OPTIONS = array('p_mod_edit_users' => 'g_mod_edit_users', 'p_mod_rename_users' => 'g_mod_rename_users', 'p_mod_change_passwords' => 'g_mod_change_passwords', 'p_mod_ban_users' => 'g_mod_ban_users');

	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly BoardDataInterface $data
	) {}

	public function apply(int $startAt): PatchStep {
		$options = BoardOptions::of($this->settings);

		// Moving the groups twice would move them back, so a rerun resumes at the step the last run did not record
		$reorder = $options[BoardOptions::GROUP_REORDER] ?? null;
		if (BoardOptions::from12($this->settings) && ($reorder !== null || !$this->data->hasModeratorGroup()))
		{
			if ($reorder === null)
			{
				$reorder = $this->data->spareGroupId().':0';
				BoardOptions::store($this->settings, BoardOptions::GROUP_REORDER, $reorder);
			}

			[$spare, $step] = array_map(intval(...), explode(':', $reorder, 2) + array(1 => '0'));
			while ($this->data->reorderGroups($spare, $step))
				BoardOptions::store($this->settings, BoardOptions::GROUP_REORDER, $spare.':'.++$step);

			// The default group, where it is the members' old id
			$this->settings->replace(new Setting('o_default_user_group', '3'), '4');
		}

		foreach (self::OPTIONS as $option => $permission)
		{
			if (!array_key_exists($option, $options))
				continue;

			$this->data->grantModerators($permission, (int) $options[$option]);
			$this->settings->remove($option);
		}

		return new PatchStep();
	}
}
