<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Manifest;

use PunBB\Module\Extensions\Api\Data\ManifestInterface;

/**
 * A directory under extensions/, and the manifest found in it.
 */
final readonly class LocalManifest {
	/** The directory's name is no extension id. */
	public const ILLEGAL_ID = 'illegal_id';

	/** The directory has no manifest.xml. */
	public const MISSING = 'missing';

	/** Its manifest.xml parsed to nothing. */
	public const UNPARSED = 'unparsed';

	/** Its manifest did not check out: the errors say why. */
	public const INVALID = 'invalid';

	/**
	 * @param ?string $problem one of the constants; null when the manifest checked out
	 * @param list<string> $errors each markup
	 */
	public function __construct(public string $directory, public ?ManifestInterface $manifest, public ?string $problem = null, public array $errors = array()) {}
}
