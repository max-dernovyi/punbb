<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

/**
 * Which change of a profile the step observers saw asked for: choosing the
 * forums a member moderates and renaming them read and store the forums'
 * moderators at points of their own.
 */
final class ProfileFlow {
	public const MODERATORS = 'pf_forum_moderators';

	public const RENAME = 'pf_change_details';

	private string $prefix = self::MODERATORS;

	public function select(string $prefix): void {
		$this->prefix = $prefix;
	}

	/** The start of the points reading and storing the moderators: 'pf_forum_moderators' or 'pf_change_details'. */
	public function prefix(): string {
		return $this->prefix;
	}
}
