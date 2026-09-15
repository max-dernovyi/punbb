<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\Data\SettingInterface;

/**
 * The options, read from and written to the config table.
 */
final class BoardSettings implements BoardSettingsInterface {
	public function __construct(private readonly Connection $db) {}

	public function version(): ?string {
		$version = $this->db->selectValue('SELECT c.conf_value FROM '.$this->db->table('config').' AS c WHERE c.conf_name=\'o_cur_version\'');

		return $version !== null ? (string) $version : null;
	}

	public function all(): array {
		return array_map(static fn (Row $row): Setting => new Setting($row->string('conf_name'), $row->nullableString('conf_value')),
			$this->db->select('SELECT c.conf_name, c.conf_value FROM '.$this->db->table('config').' AS c'));
	}

	public function add(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
			$this->db->execute('INSERT INTO '.$this->db->table('config').' (conf_name, conf_value) VALUES (?, ?)', $setting->name(), $setting->value());
	}

	public function update(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
			$this->db->execute('UPDATE '.$this->db->table('config').' SET conf_value=? WHERE conf_name=?', $setting->value(), $setting->name());
	}

	public function replace(SettingInterface $setting, string $expected): void {
		$this->db->execute('UPDATE '.$this->db->table('config').' SET conf_value=? WHERE conf_name=? AND conf_value=?', $setting->value(), $setting->name(), $expected);
	}

	public function rename(string $from, string $to): void {
		$this->db->execute('UPDATE '.$this->db->table('config').' SET conf_name=? WHERE conf_name=?', $to, $from);
	}

	public function remove(string ...$names): void {
		foreach ($names as $name)
			$this->db->execute('DELETE FROM '.$this->db->table('config').' WHERE conf_name=?', $name);
	}
}
