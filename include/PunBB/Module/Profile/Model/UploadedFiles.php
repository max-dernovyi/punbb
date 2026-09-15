<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Avatar\UploadedFilesInterface;

/**
 * The uploads PHP received, handled on the filesystem the forum runs from.
 */
final class UploadedFiles implements UploadedFilesInterface {
	public function isUploaded(string $file): bool {
		return is_uploaded_file($file);
	}

	public function move(string $file, string $destination): bool {
		return @move_uploaded_file($file, $destination);
	}

	public function imageSize(string $file): ?array {
		if (!is_file($file) || !is_readable($file))
			return null;

		// getimagesize() warns on a truncated or non-image file; what it returns decides
		$info = @getimagesize($file);

		if (!is_array($info) || $info[0] <= 0 || $info[1] <= 0)
			return null;

		return array($info[0], $info[1], $info[2]);
	}

	public function delete(string $file): void {
		@unlink($file);
	}

	public function place(string $file, string $destination): void {
		@rename($file, $destination);
		@chmod($destination, 0644);
	}
}
