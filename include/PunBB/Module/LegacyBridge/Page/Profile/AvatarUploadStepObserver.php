<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\AvatarUploadStep;

/**
 * Runs the point at each stage of checking an avatar uploaded, with the member
 * in $user, the types accepted in $allowed_mime_types and $allowed_types, read
 * back once uploaded; the file moved in $avatar_tmp_file; the extension and
 * type in $extension and $avatar_type, read back once typed; and the errors in
 * $errors, read back.
 */
final class AvatarUploadStepObserver {
	public const POINTS = array(
		AvatarUploadStep::UPLOADED	=> 'pf_change_details_avatar_allowed_types',
		AvatarUploadStep::MOVED		=> 'pf_change_details_avatar_modify_size',
		AvatarUploadStep::TYPED		=> 'pf_change_details_avatar_determine_extension',
		AvatarUploadStep::CHECKED	=> 'pf_change_details_avatar_validate_file',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(AvatarUploadStep $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		ProfileState::publish($event->user());
		$GLOBALS['allowed_mime_types'] = $event->mimeTypes();
		$GLOBALS['allowed_types'] = $event->imageTypes();
		$GLOBALS['avatar_tmp_file'] = $event->file();
		$GLOBALS['extension'] = $event->extension() !== '' ? $event->extension() : null;
		$GLOBALS['avatar_type'] = $event->avatarType();
		$GLOBALS['errors'] = $event->errors();

		$this->scope->observe($point, $event);

		if ($event->stage() === AvatarUploadStep::UPLOADED)
			$event->accept(array_values(Markers::entries($GLOBALS['allowed_mime_types'] ?? null)), array_values(array_map(intval(...), Markers::entries($GLOBALS['allowed_types'] ?? null))));

		if ($event->stage() === AvatarUploadStep::TYPED)
			$event->type(Markers::markup($GLOBALS['extension'] ?? ''), (int) Markers::markup($GLOBALS['avatar_type'] ?? 0));

		$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));
	}
}
