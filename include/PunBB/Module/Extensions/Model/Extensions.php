<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Extensions\Api\Data\ExtensionRecordInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionStatusInterface;
use PunBB\Module\Extensions\Api\Data\HookRecordInterface;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\ExtensionsInterface;

/**
 * The extensions and extension hooks tables.
 */
final class Extensions implements ExtensionsInterface {
	private const COLUMNS = 'e.id, e.title, e.version, e.description, e.author, e.uninstall, e.uninstall_note, e.disabled, e.dependencies';

	public function __construct(private readonly Connection $db) {}

	public function all(): array {
		return array_map(self::extension(...), $this->db->select('SELECT '.self::COLUMNS.' FROM '.$this->db->table('extensions').' AS e ORDER BY e.title'));
	}

	public function enabledVersions(): array {
		return array_map(static fn (Row $row): ExtensionVersion => new ExtensionVersion($row->string('id'), $row->string('version')),
			$this->db->select('SELECT e.id, e.version FROM '.$this->db->table('extensions').' AS e WHERE e.disabled=0'));
	}

	public function installedVersion(string $id): ?string {
		$version = $this->db->selectValue('SELECT e.version FROM '.$this->db->table('extensions').' AS e WHERE e.id=?', $id);

		return $version !== null ? (string) $version : null;
	}

	public function update(ExtensionRecordInterface ...$extensions): void {
		foreach ($extensions as $extension)
			$this->db->execute('UPDATE '.$this->db->table('extensions').' SET title=?, version=?, description=?, author=?, uninstall=?, uninstall_note=?, dependencies=? WHERE id=?',
				$extension->title(), $extension->version(), $extension->description(), $extension->author(), self::nullable($extension->uninstallCode()), self::nullable($extension->uninstallNote()),
				InstalledExtension::dependencyColumn($extension->dependencies()), $extension->id());
	}

	public function clearHooks(string ...$extensionIds): void {
		$this->removeHooks(...$extensionIds);
	}

	public function add(ExtensionRecordInterface ...$extensions): void {
		foreach ($extensions as $extension)
			$this->db->execute('INSERT INTO '.$this->db->table('extensions').' (id, title, version, description, author, uninstall, uninstall_note, dependencies) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
				$extension->id(), $extension->title(), $extension->version(), $extension->description(), $extension->author(), self::nullable($extension->uninstallCode()), self::nullable($extension->uninstallNote()),
				InstalledExtension::dependencyColumn($extension->dependencies()));
	}

	public function addHooks(HookRecordInterface ...$hooks): void {
		foreach ($hooks as $hook)
			$this->db->execute('INSERT INTO '.$this->db->table('extension_hooks').' (id, extension_id, code, installed, priority) VALUES (?, ?, ?, ?, ?)',
				$hook->id(), $hook->extensionId(), $hook->code(), $hook->installedAt(), $hook->priority());
	}

	public function find(string $id): ?InstalledExtensionInterface {
		$row = $this->db->selectRow('SELECT '.self::COLUMNS.' FROM '.$this->db->table('extensions').' AS e WHERE e.id=?', $id);

		return $row !== null ? self::extension($row) : null;
	}

	public function dependent(string $id): ?string {
		return self::text($this->db->selectValue('SELECT e.id FROM '.$this->db->table('extensions').' AS e WHERE e.dependencies LIKE ?', '%|'.$id.'|%'));
	}

	public function removeHooks(string ...$extensionIds): void {
		foreach ($extensionIds as $extensionId)
			$this->db->execute('DELETE FROM '.$this->db->table('extension_hooks').' WHERE extension_id=?', $extensionId);
	}

	public function remove(string ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('extensions').' WHERE id=?', $id);
	}

	public function isDisabled(string $id): ?bool {
		$disabled = $this->db->selectValue('SELECT e.disabled FROM '.$this->db->table('extensions').' AS e WHERE e.id=?', $id);

		return $disabled !== null ? (int) $disabled !== 0 : null;
	}

	public function enabledDependent(string $id): ?string {
		return self::text($this->db->selectValue('SELECT e.id FROM '.$this->db->table('extensions').' AS e WHERE e.disabled=0 AND e.dependencies LIKE ?', '%|'.$id.'|%'));
	}

	public function dependencies(string $id): array {
		return InstalledExtension::dependencyList(self::text($this->db->selectValue('SELECT e.dependencies FROM '.$this->db->table('extensions').' AS e WHERE e.id=?', $id)));
	}

	public function enabledIds(): array {
		return array_map(static fn (Row $row): string => $row->string('id'), $this->db->select('SELECT e.id FROM '.$this->db->table('extensions').' AS e WHERE e.disabled=0'));
	}

	public function setDisabled(ExtensionStatusInterface ...$statuses): void {
		foreach ($statuses as $status)
			$this->db->execute('UPDATE '.$this->db->table('extensions').' SET disabled=? WHERE id=?', $status->isDisabled() ? 1 : 0, $status->id());
	}

	private static function extension(Row $row): InstalledExtension {
		return new InstalledExtension(
			$row->string('id'),
			$row->string('title'),
			$row->string('version'),
			$row->string('description'),
			$row->string('author'),
			$row->nullableString('uninstall') ?? '',
			$row->nullableString('uninstall_note') ?? '',
			InstalledExtension::dependencyList($row->nullableString('dependencies')),
			$row->int('disabled') !== 0
		);
	}

	private static function nullable(string $text): ?string {
		return $text !== '' ? $text : null;
	}

	private static function text(int|float|string|null $value): ?string {
		return $value !== null ? (string) $value : null;
	}
}
