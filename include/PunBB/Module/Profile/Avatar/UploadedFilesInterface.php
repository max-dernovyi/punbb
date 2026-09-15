<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Avatar;

/**
 * The files a request uploads, and the avatars' directory they end up in.
 */
interface UploadedFilesInterface {
	/** Whether $file is a file this request uploaded. */
	public function isUploaded(string $file): bool;

	/** Moves the upload $file to $destination; false when it could not be moved. */
	public function move(string $file, string $destination): bool;

	/**
	 * The width, height and IMAGETYPE_* of the image in $file; null when it is no image.
	 *
	 * @return array{int, int, int}|null
	 */
	public function imageSize(string $file): ?array;

	public function delete(string $file): void;

	/** Renames $file to $destination, readable by everyone. */
	public function place(string $file, string $destination): void;
}
