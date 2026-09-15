<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Manifest\LocalManifest;
use PunBB\Module\Extensions\Manifest\ManifestReading;
use PunBB\Module\Extensions\Manifest\ManifestsInterface;
use PunBB\Module\Extensions\Model\Manifest;
use PunBB\Module\Extensions\Model\ManifestDependency;
use PunBB\Module\Extensions\Model\ManifestHook;
use PunBB\Module\Extensions\Model\ManifestNote;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * The manifests, through xml_to_array() and validate_manifest() of include/xml.php,
 * with the extension code attached to them, and the version gates of
 * include/functions.php. What was read is left in $manifest, $ext_data and $errors.
 */
final class LegacyManifests implements ManifestsInterface {
	private const HOTFIX_SERVICE = 'https://punbb.informer.com/update/manifest/';

	public function __construct(private readonly ExtensionRows $rows) {}

	public function local(string $id): ManifestReading {
		$file = LegacyChromeSource::root().'extensions/'.$id.'/manifest.xml';

		return $this->read(is_readable($file) ? (string) file_get_contents($file) : false, $id);
	}

	public function hotfix(string $id): ManifestReading {
		$remote = \get_remote_file(self::HOTFIX_SERVICE.$id.'.xml', 16);

		return $this->read(is_array($remote) && !empty($remote['content']) && is_string($remote['content']) ? $remote['content'] : false, $id);
	}

	public function directory(): array {
		self::loadXml();

		$root = LegacyChromeSource::root().'extensions';
		$directory = dir($root);
		if ($directory === false)
			return array();

		$listed = array();
		while (($entry = $directory->read()) !== false)
		{
			if ($entry[0] === '.' || !is_dir($root.'/'.$entry))
				continue;

			$GLOBALS['entry'] = $entry;

			if (preg_match('/[^0-9a-z_]/', $entry))
				$listed[] = new LocalManifest($entry, null, LocalManifest::ILLEGAL_ID);
			else if (!file_exists($root.'/'.$entry.'/manifest.xml'))
				$listed[] = new LocalManifest($entry, null, LocalManifest::MISSING);
			else
			{
				$data = is_readable($root.'/'.$entry.'/manifest.xml') ? \xml_to_array((string) file_get_contents($root.'/'.$entry.'/manifest.xml')) : '';
				$GLOBALS['ext_data'] = $data;

				if (empty($data) || !is_array($data))
				{
					$listed[] = new LocalManifest($entry, null, LocalManifest::UNPARSED);
					continue;
				}

				$errors = self::errors(\validate_manifest($data, $entry));
				$GLOBALS['errors'] = $errors;

				$listed[] = $errors !== array() ? new LocalManifest($entry, null, LocalManifest::INVALID, $errors) : new LocalManifest($entry, $this->manifest($data));
			}
		}

		$directory->close();

		return $listed;
	}

	public function supports(ManifestInterface $manifest): bool {
		return defined('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK') || \forum_extension_version_supported(self::forumVersion(), $manifest->maxTestedOn());
	}

	public function outgrows(ManifestInterface $manifest): bool {
		return version_compare(Markers::markup(\clean_version(self::forumVersion())), Markers::markup(\clean_version($manifest->maxTestedOn())), '>');
	}

	private function read(string|false $xml, string $id): ManifestReading {
		self::loadXml();

		$data = \xml_to_array($xml);
		$errors = self::errors(\validate_manifest($data, $id));

		$GLOBALS['manifest'] = $xml;
		$GLOBALS['ext_data'] = $data;
		$GLOBALS['errors'] = $errors;

		if ($errors !== array() || !is_array($data))
			return new ManifestReading(null, $errors);

		return new ManifestReading($this->manifest($data));
	}

	/**
	 * The manifest a parse describes, with its dependencies made a list the way
	 * the page script left them in $ext_data.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private function manifest(array $data): Manifest {
		$extension = is_array($data['extension'] ?? null) ? $data['extension'] : array();

		$dependencies = array();
		$listed = self::element($extension, 'dependencies', 'dependency');
		if ($listed !== null)
		{
			// A lone dependency parses to a scalar, or to an element array when it carries attributes
			if (!is_array($listed) || array_key_exists('content', $listed))
				$listed = array($listed);

			$dependencies = array_values($listed);
		}

		$extension['dependencies'] = $dependencies;
		$data['extension'] = $extension;

		$notes = array();
		foreach (is_array($extension['note'] ?? null) ? $extension['note'] : array() as $note)
			if (is_array($note))
				$notes[] = new ManifestNote(Markers::markup(self::element($note, 'attributes', 'type')), Markers::markup($note['content'] ?? ''));

		$hooks = array();
		$listedHooks = self::element($extension, 'hooks', 'hook');
		foreach (is_array($listedHooks) ? $listedHooks : array() as $hook)
		{
			if (!is_array($hook))
				continue;

			$points = array_map(static fn (string $point): string => self::trim($point), explode(',', Markers::markup(self::element($hook, 'attributes', 'id'))));
			$hooks[] = new ManifestHook($points, self::trim($hook['content'] ?? ''), (int) Markers::markup(self::element($hook, 'attributes', 'priority') ?? 5));
		}

		$manifest = new Manifest(
			Markers::markup($extension['id'] ?? ''),
			Markers::markup($extension['title'] ?? ''),
			Markers::markup($extension['version'] ?? ''),
			Markers::markup($extension['description'] ?? ''),
			Markers::markup($extension['author'] ?? ''),
			Markers::markup($extension['maxtestedon'] ?? ''),
			array_map(static fn (mixed $dependency): ManifestDependency => new ManifestDependency(
				is_array($dependency) ? Markers::markup($dependency['content'] ?? '') : Markers::markup($dependency),
				is_array($dependency) ? Markers::markup(self::element($dependency, 'attributes', 'minversion')) : ''
			), $dependencies),
			$notes,
			self::trim($extension['install'] ?? ''),
			self::trim($extension['uninstall'] ?? ''),
			$hooks
		);

		$this->rows->keep($manifest, $data);
		$GLOBALS['ext_data'] = $data;

		return $manifest;
	}

	/** What a parse holds at $path below $node; null when it holds nothing there. */
	private static function element(mixed $node, string ...$path): mixed {
		foreach ($path as $key)
		{
			if (!is_array($node) || !isset($node[$key]))
				return null;

			$node = $node[$key];
		}

		return $node;
	}

	private static function trim(mixed $value): string {
		return Markers::markup(\forum_trim(Markers::markup($value)));
	}

	/** @return list<string> */
	private static function errors(mixed $errors): array {
		return array_values(Markers::entries($errors));
	}

	private static function forumVersion(): string {
		$config = $GLOBALS['forum_config'] ?? null;

		return is_array($config) ? Markers::markup($config['o_cur_version'] ?? '') : '';
	}

	public static function loadXml(): void {
		if (!defined('FORUM_XML_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/xml.php');
	}
}
