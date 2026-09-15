<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Api\Data\ExtensionRecordInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionStatusInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionVersionInterface;
use PunBB\Module\Extensions\Api\Data\HookRecordInterface;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\ExtensionsInterface;
use PunBB\Module\Extensions\Model\ExtensionVersion;
use PunBB\Module\Extensions\Model\InstalledExtension;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\FoundName;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The extensions page's query points, with the query arrays admin/extensions.php
 * built. A query a point changed answers instead; a statement a point changed
 * runs instead, and the repository is handed nothing to store. What was read
 * is left where the page script kept it: the extensions in $inst_exts, the
 * enabled ones in $installed_ext, the installed version in $ext_version, an
 * extension in $ext_data, a dependent one in $dependency, the status in
 * $ext_status and $disable, the dependencies in $dependencies.
 */
final class ExtensionsPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly ExtensionRows $rows) {}

	/**
	 * @param list<InstalledExtensionInterface> $result
	 * @return list<InstalledExtensionInterface>
	 */
	public function afterAll(ExtensionsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'e.*',
			'FROM'		=> 'extensions AS e',
			'ORDER BY'	=> 'e.title'
		);

		if ($this->queries->changed('aex_qr_get_all_extensions', ExtensionsInterface::class.'::all', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$extension = self::extensionOf($row);
				$this->rows->keep($extension, $row);
				$result[] = $extension;
			}
		}

		$inst_exts = array();
		foreach ($result as $extension)
			$inst_exts[$extension->id()] = $this->rows->extension($extension);

		$GLOBALS['inst_exts'] = $inst_exts;

		return $result;
	}

	/**
	 * @param list<ExtensionVersionInterface> $result
	 * @return list<ExtensionVersionInterface>
	 */
	public function afterEnabledVersions(ExtensionsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'e.id, e.version',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0'
		);

		if ($this->queries->changed('aex_install_check_dependencies', ExtensionsInterface::class.'::enabledVersions', $query))
			$result = array_map(static fn (array $row): ExtensionVersion => new ExtensionVersion(Markers::markup($row['id'] ?? ''), Markers::markup($row['version'] ?? '')), PluggedQuery::rows($query));

		$installed_ext = array();
		foreach ($result as $extension)
			$installed_ext[$extension->id()] = array('id' => $extension->id(), 'version' => $extension->version());

		$GLOBALS['installed_ext'] = $installed_ext;

		return $result;
	}

	public function afterInstalledVersion(ExtensionsInterface $subject, ?string $result, string $id): ?string {
		$query = array(
			'SELECT'	=> 'e.version',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.self::escape($id).'\''
		);

		if ($this->queries->changed('aex_install_comply_qr_get_current_ext_version', ExtensionsInterface::class.'::installedVersion', $query))
			$result = FoundName::of($query);

		$GLOBALS['ext_version'] = $result;

		return $result;
	}

	/** @return list<ExtensionRecordInterface>|null */
	public function beforeUpdate(ExtensionsInterface $subject, ExtensionRecordInterface ...$extensions): ?array {
		$kept = array();
		foreach ($extensions as $extension)
		{
			[$uninstall_code, $uninstall_note, $dependencies] = self::stored($extension);

			$query = array(
				'UPDATE'	=> 'extensions',
				'SET'		=> 'title=\''.self::escape($extension->title()).'\', version=\''.self::escape($extension->version()).'\', description=\''.self::escape($extension->description()).'\', author=\''.self::escape($extension->author()).'\', uninstall='.$uninstall_code.', uninstall_note='.$uninstall_note.', dependencies=\'|'.implode('|', $dependencies).'|\'',
				'WHERE'		=> 'id=\''.self::escape($extension->id()).'\''
			);

			if ($this->queries->changed('aex_install_comply_qr_update_ext', ExtensionsInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $extension;
		}

		return count($kept) !== count($extensions) ? $kept : null;
	}

	/** @return list<string>|null */
	public function beforeClearHooks(ExtensionsInterface $subject, string ...$extensionIds): ?array {
		return $this->deletions($extensionIds, 'aex_install_comply_qr_update_ext_delete_hooks', 'clearHooks', 'extension_hooks', 'extension_id');
	}

	/** @return list<ExtensionRecordInterface>|null */
	public function beforeAdd(ExtensionsInterface $subject, ExtensionRecordInterface ...$extensions): ?array {
		$kept = array();
		foreach ($extensions as $extension)
		{
			[$uninstall_code, $uninstall_note, $dependencies] = self::stored($extension);

			$query = array(
				'INSERT'	=> 'id, title, version, description, author, uninstall, uninstall_note, dependencies',
				'INTO'		=> 'extensions',
				'VALUES'	=> '\''.self::escape($extension->id()).'\', \''.self::escape($extension->title()).'\', \''.self::escape($extension->version()).'\', \''.self::escape($extension->description()).'\', \''.self::escape($extension->author()).'\', '.$uninstall_code.', '.$uninstall_note.', \'|'.implode('|', $dependencies).'|\'',
			);

			if ($this->queries->changed('aex_install_comply_qr_add_ext', ExtensionsInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $extension;
		}

		return count($kept) !== count($extensions) ? $kept : null;
	}

	/** @return list<HookRecordInterface>|null */
	public function beforeAddHooks(ExtensionsInterface $subject, HookRecordInterface ...$hooks): ?array {
		$kept = array();
		foreach ($hooks as $hook)
		{
			$GLOBALS['cur_hook'] = $hook->id();

			$query = array(
				'INSERT'	=> 'id, extension_id, code, installed, priority',
				'INTO'		=> 'extension_hooks',
				'VALUES'	=> '\''.self::escape($hook->id()).'\', \''.self::escape($hook->extensionId()).'\', \''.self::escape($hook->code()).'\', '.$hook->installedAt().', '.$hook->priority()
			);

			if ($this->queries->changed('aex_install_comply_qr_add_hook', ExtensionsInterface::class.'::addHooks', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $hook;
		}

		return count($kept) !== count($hooks) ? $kept : null;
	}

	public function afterFind(ExtensionsInterface $subject, ?InstalledExtensionInterface $result, string $id): ?InstalledExtensionInterface {
		$query = array(
			'SELECT'	=> 'e.title, e.version, e.description, e.author, e.uninstall, e.uninstall_note',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.self::escape($id).'\''
		);

		$row = null;
		if ($this->queries->changed('aex_uninstall_qr_get_extension', ExtensionsInterface::class.'::find', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? self::extensionOf(array('id' => $id) + $row) : null;
		}

		$GLOBALS['ext_data'] = $row ?? ($result !== null ? $this->rows->extension($result) : null);

		return $result;
	}

	public function afterDependent(ExtensionsInterface $subject, ?string $result, string $id): ?string {
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.dependencies LIKE \'%|'.self::escape($id).'|%\''
		);

		if ($this->queries->changed('aex_uninstall_qr_check_dependencies', ExtensionsInterface::class.'::dependent', $query))
			$result = FoundName::of($query);

		$GLOBALS['dependency'] = $result !== null ? array('id' => $result) : null;

		return $result;
	}

	/** @return list<string>|null */
	public function beforeRemoveHooks(ExtensionsInterface $subject, string ...$extensionIds): ?array {
		return $this->deletions($extensionIds, 'aex_uninstall_comply_qr_uninstall_delete_hooks', 'removeHooks', 'extension_hooks', 'extension_id');
	}

	/** @return list<string>|null */
	public function beforeRemove(ExtensionsInterface $subject, string ...$ids): ?array {
		return $this->deletions($ids, 'aex_uninstall_comply_qr_delete_extension', 'remove', 'extensions', 'id');
	}

	public function afterIsDisabled(ExtensionsInterface $subject, ?bool $result, string $id): ?bool {
		$query = array(
			'SELECT'	=> 'e.disabled',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.self::escape($id).'\''
		);

		if ($this->queries->changed('aex_flip_qr_get_disabled_status', ExtensionsInterface::class.'::isDisabled', $query))
		{
			$status = FoundName::of($query);
			$result = $status !== null ? $status !== '0' : null;
		}

		$GLOBALS['ext_status'] = $result !== null ? ($result ? '1' : '0') : null;
		$GLOBALS['disable'] = $result === false;

		return $result;
	}

	public function afterEnabledDependent(ExtensionsInterface $subject, ?string $result, string $id): ?string {
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0 AND e.dependencies LIKE \'%|'.self::escape($id).'|%\''
		);

		if ($this->queries->changed('aex_flip_qr_get_disable_dependencies', ExtensionsInterface::class.'::enabledDependent', $query))
			$result = FoundName::of($query);

		$GLOBALS['dependency'] = $result !== null ? array('id' => $result) : null;

		return $result;
	}

	/**
	 * @param list<string> $result
	 * @return list<string>
	 */
	public function afterDependencies(ExtensionsInterface $subject, array $result, string $id): array {
		$query = array(
			'SELECT'	=> 'e.dependencies',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.self::escape($id).'\''
		);

		if ($this->queries->changed('aex_flip_qr_get_enable_dependencies', ExtensionsInterface::class.'::dependencies', $query))
			$result = InstalledExtension::dependencyList(FoundName::of($query));

		$GLOBALS['dependencies'] = $result;

		return $result;
	}

	/**
	 * @param list<string> $result
	 * @return list<string>
	 */
	public function afterEnabledIds(ExtensionsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0'
		);

		if ($this->queries->changed('aex_flip_qr_check_dependencies', ExtensionsInterface::class.'::enabledIds', $query))
			$result = array_map(static fn (array $row): string => Markers::markup($row['id'] ?? ''), PluggedQuery::rows($query));

		$GLOBALS['installed_ext'] = $result;

		return $result;
	}

	/** @return list<ExtensionStatusInterface>|null */
	public function beforeSetDisabled(ExtensionsInterface $subject, ExtensionStatusInterface ...$statuses): ?array {
		$kept = array();
		foreach ($statuses as $status)
		{
			$query = array(
				'UPDATE'	=> 'extensions',
				'SET'		=> 'disabled='.($status->isDisabled() ? '1' : '0'),
				'WHERE'		=> 'id=\''.self::escape($status->id()).'\''
			);

			if ($this->queries->changed('aex_flip_qr_update_disabled_status', ExtensionsInterface::class.'::setDisabled', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $status;
		}

		return count($kept) !== count($statuses) ? $kept : null;
	}

	/**
	 * Runs the deletion $point changed for each id, and hands the repository the rest.
	 *
	 * @param array<string> $ids
	 * @return list<string>|null
	 */
	private function deletions(array $ids, string $point, string $method, string $table, string $column): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = array(
				'DELETE'	=> $table,
				'WHERE'		=> $column.'=\''.self::escape($id).'\''
			);

			if ($this->queries->changed($point, ExtensionsInterface::class.'::'.$method, $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}

	/**
	 * The uninstall code, the uninstall note and the dependency ids as the page
	 * script quoted them into its statements, each escaped where it is built.
	 *
	 * @return array{string, string, list<string>}
	 */
	private static function stored(ExtensionRecordInterface $extension): array {
		$dependencies = array();
		foreach ($extension->dependencies() as $dependency)
			$dependencies[] = self::escape($dependency);

		return array(
			$extension->uninstallCode() !== '' ? '\''.self::escape($extension->uninstallCode()).'\'' : 'NULL',
			$extension->uninstallNote() !== '' ? '\''.self::escape($extension->uninstallNote()).'\'' : 'NULL',
			$dependencies,
		);
	}

	/** @param array<array-key, mixed> $row */
	private static function extensionOf(array $row): InstalledExtension {
		return new InstalledExtension(
			Markers::markup($row['id'] ?? ''),
			Markers::markup($row['title'] ?? ''),
			Markers::markup($row['version'] ?? ''),
			Markers::markup($row['description'] ?? ''),
			Markers::markup($row['author'] ?? ''),
			Markers::markup($row['uninstall'] ?? ''),
			Markers::markup($row['uninstall_note'] ?? ''),
			InstalledExtension::dependencyList(isset($row['dependencies']) ? Markers::markup($row['dependencies']) : null),
			Markers::markup($row['disabled'] ?? '0') === '1'
		);
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
