<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Settings;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Settings\Api\Data\SubmittedSettingsInterface;
use PunBB\Module\Settings\Event\SettingsFormStep;

/**
 * Runs the point at each step of saving a section, with the section as
 * $section and the settings posted as $form, read back: a value that is not
 * an integer comes back as text, and one the code dropped is dropped. A
 * section validates at its own point, one an extension added at
 * aop_new_section_validation.
 */
final class SettingsFormStepObserver {
	public const POINTS = array(
		SettingsFormStep::SUBMITTED	=> 'aop_form_submitted',
		SettingsFormStep::UPDATING	=> 'aop_pre_update_configuration',
		SettingsFormStep::UPDATED	=> 'aop_pre_redirect',
	);

	/** @var array<string, string> section => the point validating it */
	public const VALIDATION = array(
		'setup'			=> 'aop_setup_validation',
		'features'		=> 'aop_features_validation',
		'email'			=> 'aop_email_validation',
		'announcements'	=> 'aop_announcements_validation',
		'registration'	=> 'aop_registration_validation',
		'maintenance'	=> 'aop_maintenance_validation',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(SettingsFormStep $event): void {
		$point = $event->step() === SettingsFormStep::VALIDATING ? self::VALIDATION[$event->section()] ?? 'aop_new_section_validation' : self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$settings = $event->settings();

		$form = array();
		foreach ($settings->names() as $name)
			$form[$name] = $settings->value($name);

		$GLOBALS['section'] = $event->section();
		$GLOBALS['form'] = $form;

		$this->scope->observe($point, $event);

		self::readBack($settings, $GLOBALS['form'] ?? null);
	}

	private static function readBack(SubmittedSettingsInterface $settings, mixed $form): void {
		$form = is_array($form) ? $form : array();

		foreach ($settings->names() as $name)
			if (!array_key_exists($name, $form))
				$settings->remove($name);

		foreach ($form as $name => $value)
			$settings->set((string) $name, is_int($value) ? $value : (is_scalar($value) ? (string) $value : ''));
	}
}
