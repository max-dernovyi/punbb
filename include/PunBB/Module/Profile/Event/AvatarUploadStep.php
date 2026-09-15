<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A stage of checking an avatar uploaded: the file uploaded, where an observer
 * may change the MIME types and image types accepted; the file moved beside
 * the avatars, before its size is read; its image type found, where an
 * observer may change the extension it is stored with and the type recorded;
 * and the file checked, before it replaces the avatar. From the upload on, an
 * observer may change the errors that stop it, each markup.
 */
final class AvatarUploadStep implements EventInterface {
	public const UPLOADED = 'uploaded';

	public const MOVED = 'moved';

	public const TYPED = 'typed';

	public const CHECKED = 'checked';

	private const STAGES = array(self::UPLOADED, self::MOVED, self::TYPED, self::CHECKED);

	/**
	 * @param list<string> $mimeTypes the MIME types a file may be sent as
	 * @param list<int> $imageTypes the IMAGETYPE_* an avatar may be
	 * @param string $file the file the upload was moved to, from moved on
	 * @param string $extension the extension the avatar is stored with, from typed on; '' for none
	 * @param int $avatarType the type the member's account records, from typed on
	 * @param list<string> $errors
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ProfileUserInterface $user,
		private array $mimeTypes,
		private array $imageTypes,
		private readonly string $file = '',
		private string $extension = '',
		private int $avatarType = 0,
		private array $errors = array()
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('Checking an avatar has no stage "%s"', $stage));
	}

	public function stage(): string {
		return $this->stage;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	/** @return list<string> */
	public function mimeTypes(): array {
		return $this->mimeTypes;
	}

	/** @return list<int> */
	public function imageTypes(): array {
		return $this->imageTypes;
	}

	public function file(): string {
		return $this->file;
	}

	public function extension(): string {
		return $this->extension;
	}

	public function avatarType(): int {
		return $this->avatarType;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * @param list<string> $mimeTypes
	 * @param list<int> $imageTypes
	 */
	public function accept(array $mimeTypes, array $imageTypes): void {
		if ($this->stage !== self::UPLOADED)
			throw new InvalidArgumentException('The types an avatar may be change once it is uploaded only');

		$this->mimeTypes = $mimeTypes;
		$this->imageTypes = $imageTypes;
	}

	public function type(string $extension, int $avatarType): void {
		if ($this->stage !== self::TYPED)
			throw new InvalidArgumentException('An avatar\'s extension and type change once its image type is found only');

		$this->extension = $extension;
		$this->avatarType = $avatarType;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		$this->errors = $errors;
	}
}
