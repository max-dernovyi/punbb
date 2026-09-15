<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Api\Data\SubmittedDetailsInterface;
use PunBB\Module\Profile\Event\DetailsUpdateStep;

/**
 * Runs the point at each step of saving a section of a profile, with the
 * member in $user, the section in $section, what it saves in $form and the
 * errors in $errors, both read back until it is stored; the sections storing
 * nothing in $skip_db_update_sections, read back once checked; the old name in
 * $old_username once renamed. A section validates at its own point, one an
 * extension added at pf_change_details_new_section_validation.
 */
final class DetailsUpdateStepObserver {
	public const POINTS = array(
		DetailsUpdateStep::SUBMITTED	=> 'pf_change_details_form_submitted',
		DetailsUpdateStep::VALIDATED	=> 'pf_change_details_pre_database_validation',
		DetailsUpdateStep::STORING		=> 'pf_change_details_database_validation',
		DetailsUpdateStep::RENAMED		=> 'pf_change_details_username_changed',
		DetailsUpdateStep::UPDATED		=> 'pf_change_details_pre_redirect',
	);

	/** @var array<string, string> section => the point validating it */
	public const VALIDATION = array(
		'identity'	=> 'pf_change_details_identity_validation',
		'settings'	=> 'pf_change_details_settings_validation',
		'signature'	=> 'pf_change_details_signature_validation',
		'avatar'	=> 'pf_change_details_avatar_validation',
	);

	public function __construct(private readonly PageScope $scope, private readonly ProfileFlow $flow) {}

	public function observe(DetailsUpdateStep $event): void {
		$point = $event->step() === DetailsUpdateStep::VALIDATING ? self::VALIDATION[$event->section()] ?? 'pf_change_details_new_section_validation' : self::POINTS[$event->step()];

		if ($event->step() === DetailsUpdateStep::RENAMED)
		{
			$this->flow->select(ProfileFlow::RENAME);
			$GLOBALS['username_updated'] = true;
			$GLOBALS['old_username'] = $event->oldName();
		}

		ProfileState::publish($event->user());
		$GLOBALS['section'] = $event->section();

		$details = $event->details();
		$form = array();
		foreach ($details->names() as $name)
			$form[$name] = $details->value($name);

		$GLOBALS['form'] = $form;
		$GLOBALS['errors'] = $event->errors();

		if ($event->step() === DetailsUpdateStep::VALIDATED)
			$GLOBALS['skip_db_update_sections'] = $event->skippedSections();

		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if (in_array($event->step(), array(DetailsUpdateStep::RENAMED, DetailsUpdateStep::UPDATED), true))
			return;

		self::readBack($details, $GLOBALS['form'] ?? null);
		$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));

		if ($event->step() === DetailsUpdateStep::VALIDATED)
			$event->setSkippedSections(array_values(Markers::entries($GLOBALS['skip_db_update_sections'] ?? null)));
	}

	private static function readBack(SubmittedDetailsInterface $details, mixed $form): void {
		$form = is_array($form) ? $form : array();

		foreach ($details->names() as $name)
			if (!array_key_exists($name, $form))
				$details->remove($name);

		foreach ($form as $name => $value)
			$details->set((string) $name, is_int($value) || is_float($value) ? $value : Markers::markup($value));
	}
}
