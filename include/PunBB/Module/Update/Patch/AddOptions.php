<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Model\Setting;

/**
 * The options each release added, for a board that has not got them yet, and
 * the defaults a later release changed, where the board kept the old one.
 */
final class AddOptions implements DataPatchInterface {
	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly EnvironmentInterface $environment
	) {}

	public function apply(int $startAt): PatchStep {
		$options = BoardOptions::of($this->settings);
		$fetches = $this->environment->fetchesRemoteFiles() ? '1' : '0';

		$added = array(
			'o_quote_depth'				=> '3',
			'o_database_revision'		=> '0',
			'o_default_email_setting'	=> '1',
			'o_additional_navlinks'		=> '',
			'o_sef'						=> 'Default',
			'o_topic_views'				=> '1',
			'o_signatures'				=> '1',
			'o_smtp_ssl'				=> '0',
			'o_check_for_updates'		=> $fetches,
			'o_check_for_versions'		=> $options['o_check_for_updates'] ?? $fetches,
			'o_announcement_heading'	=> '',
			'o_default_dst'				=> '0',
			'o_show_moderators'			=> '0',
			'o_mask_passwords'			=> '1',
		);

		$missing = array();
		foreach ($added as $name => $value)
			if (!array_key_exists($name, $options))
				$missing[] = new Setting($name, $value);

		$this->settings->add(...$missing);

		// Server timezone is now simply the default timezone
		if (!array_key_exists('o_default_timezone', $options))
			$this->settings->rename('o_server_timezone', 'o_default_timezone');

		// A visit lasts 30 minutes, where the board kept the old default
		if (($options['o_timeout_visit'] ?? null) === '600')
			$this->settings->update(new Setting('o_timeout_visit', '1800'));

		if (version_compare($this->settings->version() ?? '', '1.4', '<') && ($options['o_redirect_delay'] ?? null) === '1')
			$this->settings->update(new Setting('o_redirect_delay', '0'));

		return new PatchStep();
	}
}
