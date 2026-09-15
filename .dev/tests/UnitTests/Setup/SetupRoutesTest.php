<?php
/**
 * The installer and the updater through the front controller, on a scratch
 * forum the installer installed: what each answers on an installed board, and
 * an update of a board set back to 1.4.4, from its form to its finish.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class SetupRoutesTest extends TestCase {
	private static ?ScratchForum $forum = null;

	public static function setUpBeforeClass(): void {
		self::$forum = new ScratchForum();
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;
	}

	private static function forum(): ScratchForum {
		self::assertNotNull(self::$forum);

		return self::$forum;
	}

	/** @return array<string, ?string> */
	private static function config(): array {
		$config = array();
		foreach (self::forum()->rows('SELECT conf_name, conf_value FROM config') as $row)
			$config[(string) $row['conf_name']] = $row['conf_value'] !== null ? (string) $row['conf_value'] : null;

		return $config;
	}

	public function testTheInstallerWasDrivenThroughTheFrontControllerAndStoredTheBoard(): void {
		$config = self::config();

		$this->assertSame(array('1.5.1', '6', 'English'), array($config['o_cur_version'], $config['o_database_revision'], $config['o_default_lang']));
		$this->assertSame(array(array('id' => 2, 'group_id' => 1, 'username' => 'admin')), self::forum()->rows('SELECT id, group_id, username FROM users WHERE id=2'));
		$this->assertStringStartsWith('$2y$', (string) self::forum()->rows('SELECT password FROM users WHERE id=2')[0]['password'], 'the administrator\'s password is stored as every password is');
		$this->assertNotSame(array(), self::forum()->rows('SELECT word_id FROM search_matches WHERE post_id=1'), 'the welcome post is in the search index');
	}

	public function testAnInstalledBoardAnswersTheInstallerWithItsIndex(): void {
		$output = self::forum()->requestAsGuest('admin/install.php');

		$this->assertSame('The file \'config.php\' already exists which would mean that PunBB is already installed. You should go <a href="../index.php">here</a> instead.', $output);
	}

	public function testAnUpToDateBoardAnswersTheUpdaterWithTheErrorPage(): void {
		$output = self::forum()->requestAsGuest('admin/db_update.php');

		$this->assertStringContainsString('<title>Error - My PunBB forum</title>', $output);
		$this->assertStringContainsString('<p>Your database is already as up-to-date as this script can make it.</p>', $output);
	}

	/** The other tests read the board at 1.5.1: a failure halfway through must not leave it at 1.4.4. */
	protected function tearDown(): void {
		if (self::$forum === null)
			return;

		self::$forum->rows('UPDATE config SET conf_value=\'1.5.1\' WHERE conf_name=\'o_cur_version\'');
		self::$forum->rows('UPDATE config SET conf_value=\'6\' WHERE conf_name=\'o_database_revision\'');
	}

	public function testABoardSetBackTo144IsUpdatedFromItsFormToItsFinish(): void {
		$forum = self::forum();
		$forum->rows('UPDATE config SET conf_value=\'1.4.4\' WHERE conf_name=\'o_cur_version\'');
		$forum->rows('UPDATE config SET conf_value=\'4\' WHERE conf_name=\'o_database_revision\'');
		$forum->rows('DELETE FROM config WHERE conf_name IN (\'o_mask_passwords\', \'o_show_moderators\')');
		$forum->rows('UPDATE forums SET num_topics=7, num_posts=9 WHERE id=1');

		$this->assertStringContainsString('value="Start update"', $forum->requestAsGuest('admin/db_update.php'));

		$start = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'start'));
		$this->assertStringContainsString('window.location="db_update.php?stage=finish"', $start);
		$this->assertSame(array(array('conf_value' => '1'), array('conf_value' => '0')), $forum->rows('SELECT conf_value FROM config WHERE conf_name IN (\'o_mask_passwords\', \'o_show_moderators\') ORDER BY conf_name'));

		$finish = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'finish'));
		$this->assertStringContainsString('PunBB Database Update completed!', $finish);
		$this->assertStringContainsString('You may <a href="http://forum.test/index.php">go to the forum index</a> now.', $finish);
		$this->assertDoesNotMatchRegularExpression('/Warning|Notice|Deprecated|Fatal/', $start.$finish);

		$config = self::config();
		$this->assertSame(array('1.5.1', '6'), array($config['o_cur_version'], $config['o_database_revision']));
		$this->assertSame(array(array('num_topics' => 1, 'num_posts' => 1)), $forum->rows('SELECT num_topics, num_posts FROM forums WHERE id=1'), 'the forums are synchronised');

		$this->assertStringContainsString('Test forum', $forum->request('index.php'), 'the board runs on the updated database');
	}
}
