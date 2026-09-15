<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Avatar\AvatarRemovalInterface;
use PunBB\Module\Profile\Avatar\UploadedFilesInterface;
use PunBB\Module\Profile\Event\AvatarUploadStep;
use PunBB\Module\Profile\Model\Avatar;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;

/**
 * An avatar uploaded from a profile: checked by what the file is, not by what
 * the client says it is, and stored under the member's id with the extension
 * of the image type found.
 */
final class AvatarUpload {
	/** @var list<string> the MIME types a browser sends an image the board takes as */
	private const MIME_TYPES = array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png');

	/** @var list<int> */
	private const IMAGE_TYPES = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF);

	/** @var array<int, array{string, int}> IMAGETYPE_* => the extension it is stored with and the type the account records */
	private const TYPES = array(
		IMAGETYPE_GIF	=> array('.gif', 1),
		IMAGETYPE_JPEG	=> array('.jpg', 2),
		IMAGETYPE_PNG	=> array('.png', 3),
	);

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly MessagePage $messages,
		private readonly ProfilesInterface $profiles,
		private readonly AvatarRemovalInterface $removal,
		private readonly UploadedFilesInterface $files,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly FormatterInterface $formatter
	) {}

	/**
	 * The member with the avatar stored, and what stopped the upload.
	 *
	 * @param array<string, Html> $strings
	 * @param list<string> $errors what already stops it
	 * @return Response|array{ProfileUserInterface, list<string>}
	 */
	public function upload(Request $request, ProfileUserInterface $user, array $strings, array $errors): Response|array {
		if (!isset($request->files['req_file']))
		{
			$errors[] = ProfilePage::string($strings, 'No file')->html;

			return array($user, $errors);
		}

		// A multi-file req_file[] gives array-valued members: a malformed request, not an upload
		$uploaded = $request->files['req_file'];
		if (!is_array($uploaded) || !isset($uploaded['tmp_name']) || !is_string($uploaded['tmp_name']))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$size = is_numeric($uploaded['size'] ?? null) ? (int) $uploaded['size'] : 0;
		$sentType = is_string($uploaded['type'] ?? null) ? $uploaded['type'] : '';

		if (isset($uploaded['error']) && $errors === array())
		{
			$problem = match (is_numeric($uploaded['error']) ? (int) $uploaded['error'] : UPLOAD_ERR_OK) {
				UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE	=> 'Too large ini',
				UPLOAD_ERR_PARTIAL							=> 'Partial upload',
				UPLOAD_ERR_NO_FILE							=> 'No file',
				UPLOAD_ERR_NO_TMP_DIR						=> 'No tmp directory',
				default										=> $size === 0 ? 'No file' : null,
			};

			if ($problem !== null)
				$errors[] = ProfilePage::string($strings, $problem)->html;
		}

		if (!$this->files->isUploaded($uploaded['tmp_name']) || $errors !== array())
		{
			if ($errors === array())
				$errors[] = ProfilePage::string($strings, 'Unknown failure')->html;

			return array($user, $errors);
		}

		$accepting = new AvatarUploadStep(AvatarUploadStep::UPLOADED, $user, self::MIME_TYPES, self::IMAGE_TYPES, errors: $errors);
		$this->events->dispatch($accepting);
		$errors = $accepting->errors();

		$maximumSize = (int) $this->settings->value('o_avatars_size');
		if (!in_array($sentType, $accepting->mimeTypes(), true))
			$errors[] = ProfilePage::string($strings, 'Bad type')->html;
		else if ($size > $maximumSize)
			$errors[] = Html::format(ProfilePage::string($strings, 'Too large'), $this->formatter->number($maximumSize))->html;

		if ($errors !== array())
			return array($user, $errors);

		$directory = $this->settings->value('o_avatars_dir');

		// The upload is moved beside the avatars before it is measured, as open_basedir may keep it from being read where it is
		$temporary = $directory.'/'.$user->id().'.tmp';
		if (!$this->files->move($uploaded['tmp_name'], $temporary))
		{
			$admin = $this->settings->value('o_admin_email');
			$errors[] = Html::format(ProfilePage::string($strings, 'Move failed'), Html::format('<a href="mailto:%s">%s</a>', $admin, $admin))->html;

			return array($user, $errors);
		}

		$moved = new AvatarUploadStep(AvatarUploadStep::MOVED, $user, $accepting->mimeTypes(), $accepting->imageTypes(), $temporary, errors: $errors);
		$this->events->dispatch($moved);
		$errors = $moved->errors();

		$image = $this->files->imageSize($temporary);
		[$width, $height, $type] = $image ?? array(0, 0, 0);

		if ($image === null)
		{
			$this->files->delete($temporary);
			$errors[] = ProfilePage::string($strings, 'Bad type')->html;
		}

		if ($errors === array() && ($width > (int) $this->settings->value('o_avatars_width') || $height > (int) $this->settings->value('o_avatars_height')))
		{
			$this->files->delete($temporary);
			$errors[] = Html::format(ProfilePage::string($strings, 'Too wide or high'), $this->settings->value('o_avatars_width'), $this->settings->value('o_avatars_height'))->html;
		}
		else if ($type === IMAGETYPE_GIF && $sentType !== 'image/gif')
		{
			// A GIF sent as another type is a dodgy upload
			$this->files->delete($temporary);
			$errors[] = ProfilePage::string($strings, 'Bad type')->html;
		}

		[$extension, $avatarType] = self::TYPES[$type] ?? array('', 0);

		$typed = new AvatarUploadStep(AvatarUploadStep::TYPED, $user, $accepting->mimeTypes(), $accepting->imageTypes(), $temporary, $extension, $avatarType, $errors);
		$this->events->dispatch($typed);
		$errors = $typed->errors();
		$extension = $typed->extension();

		// The image type decides, not the name or the type the client sent
		if ($errors === array() && (!in_array($typed->avatarType(), $accepting->imageTypes(), true) || $extension === ''))
		{
			$this->files->delete($temporary);
			$errors[] = ProfilePage::string($strings, 'Bad type')->html;
		}

		$checked = new AvatarUploadStep(AvatarUploadStep::CHECKED, $user, $accepting->mimeTypes(), $accepting->imageTypes(), $temporary, $extension, $typed->avatarType(), $errors);
		$this->events->dispatch($checked);
		$errors = $checked->errors();

		if ($errors !== array())
			return array($user, $errors);

		$this->removal->remove($user->id());

		$this->files->place($temporary, $directory.'/'.$user->id().$extension);

		$this->profiles->storeAvatar(new Avatar($user->id(), $typed->avatarType(), max($width, 0), max($height, 0)));

		return array($user->withAvatar($typed->avatarType(), $width, $height), $errors);
	}
}
