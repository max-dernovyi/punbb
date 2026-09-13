<?php
/**
 * A throwaway forum on SQLite3, installed by admin/install.php and driven one
 * request per process through its real entry points, logged in as the admin.
 *
 * The root is a temporary directory of symlinks into the checkout with its own
 * config.php, cache/, extensions/ and database, so nothing touches the forum
 * the checkout may have installed.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

final class ScratchForum {
	public const BASE_URL = 'http://forum.test';

	private const LINKED_DIRS = array('include', 'lang', 'style', 'img', 'vendor');

	private string $root;
	private SQLite3 $db;
	private int $adminId;

	/** @var array<string, string> */
	private array $cookie = array();

	public function __construct() {
		$this->root = sys_get_temp_dir().'/punbb_scratch_'.bin2hex(random_bytes(6));

		foreach (array('admin', 'cache', 'extensions') as $dir)
			mkdir($this->root.'/'.$dir, 0777, true);

		foreach (glob(FORUM_ROOT.'*.php') as $file)
			if (basename($file) !== 'config.php')
				symlink($file, $this->root.'/'.basename($file));

		foreach (glob(FORUM_ROOT.'admin/*.php') as $file)
			symlink($file, $this->root.'/admin/'.basename($file));

		foreach (self::LINKED_DIRS as $dir)
			symlink(FORUM_ROOT.$dir, $this->root.'/'.$dir);

		$output = $this->request('admin/install.php', array(), array(
			'form_sent'		=> '1',
			'req_db_type'	=> 'sqlite3',
			'req_db_host'	=> '',
			'req_db_name'	=> 'forum.sqlite',
			'db_username'	=> '',
			'db_password'	=> '',
			'db_prefix'		=> '',
			'req_username'	=> 'admin',
			'req_email'		=> 'admin@example.com',
			'req_password1'	=> 'scratch-password',
			'req_language'	=> 'English',
			'req_base_url'	=> self::BASE_URL
		));

		if (!file_exists($this->root.'/config.php'))
		{
			$this->remove();
			throw new RuntimeException('admin/install.php did not install the scratch forum: '.$output);
		}

		$this->db = new SQLite3($this->root.'/forum.sqlite');
		$this->db->busyTimeout(5000);

		// The unit suite stays off the network: no update or version checks on admin pages.
		$this->db->exec('UPDATE config SET conf_value=\'0\' WHERE conf_name IN (\'o_check_for_updates\', \'o_check_for_versions\')');
		array_map('unlink', glob($this->root.'/cache/*.php'));

		file_put_contents($this->root.'/config.php', "\n\ndefine('FORUM_DEBUG', 1);\n", FILE_APPEND);

		$this->logInAsAdmin();
	}

	/** Makes a fixture extension under .dev/tests/fixtures/extensions/ available to install. */
	public function addExtension(string $id): void {
		symlink(FORUM_ROOT.'.dev/tests/fixtures/extensions/'.$id, $this->root.'/extensions/'.$id);
	}

	/** Makes an extension consisting of this manifest alone available to install. */
	public function writeExtension(string $id, string $manifest): void {
		mkdir($this->root.'/extensions/'.$id);
		file_put_contents($this->root.'/extensions/'.$id.'/manifest.xml', $manifest);
	}

	/** @return string what the script printed, PHP diagnostics included */
	public function request(string $script, array $get = array(), array $post = array()): string {
		$request = json_encode(array('get' => $get, 'post' => $post, 'cookie' => $this->cookie));

		return (string) shell_exec(escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.
			escapeshellarg(__DIR__.'/forum_request_harness.php').' '.escapeshellarg($this->root).' '.
			escapeshellarg($script).' '.escapeshellarg($request).' 2>&1');
	}

	/** Loads the form, then posts it with the CSRF token that page issued. */
	public function submit(string $script, array $get, array $post): string {
		$this->request($script, $get);

		$post['csrf_token'] = sha1(self::BASE_URL.'/'.$script.'?'.http_build_query($get).$this->csrfSecret());

		return $this->request($script, $get, $post);
	}

	/** Follows the enable/disable link on the extensions list. */
	public function flip(string $id): string {
		$this->request('admin/extensions.php', array('section' => 'manage'));

		return $this->request('admin/extensions.php', array(
			'section'		=> 'manage',
			'flip'			=> $id,
			'csrf_token'	=> sha1('flip'.$id.$this->adminId.$this->csrfSecret())
		));
	}

	/** @return list<array<string, mixed>> */
	public function rows(string $sql): array {
		$result = $this->db->query($sql);

		$rows = array();
		while ($row = $result->fetchArray(SQLITE3_ASSOC))
			$rows[] = $row;

		$result->finalize();

		return $rows;
	}

	public function remove(): void {
		if (isset($this->db))
			$this->db->close();

		self::removeTree($this->root);
	}

	private function logInAsAdmin(): void {
		preg_match('/^\$cookie_name = \'([^\']+)\';$/m', (string) file_get_contents($this->root.'/config.php'), $match);

		$admin = $this->rows('SELECT id, password, salt FROM users WHERE group_id='.FORUM_ADMIN)[0];
		$this->adminId = (int) $admin['id'];
		$expire = time() + 3600;

		$this->cookie = array($match[1] => base64_encode($this->adminId.'|'.$admin['password'].'|'.$expire.'|'.
			forum_cookie_hash($this->adminId, $admin['password'], $expire, $admin['salt'])));
	}

	private function csrfSecret(): string {
		return (string) $this->db->querySingle('SELECT csrf_token FROM online WHERE user_id='.$this->adminId);
	}

	// A link is unlinked, never followed: the root links into the checkout.
	private static function removeTree(string $path): void {
		if (is_link($path) || is_file($path))
		{
			unlink($path);
			return;
		}

		if (!is_dir($path))
			return;

		foreach (scandir($path) as $entry)
			if ($entry !== '.' && $entry !== '..')
				self::removeTree($path.'/'.$entry);

		rmdir($path);
	}
}
