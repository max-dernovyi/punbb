<?php
/**
 * Covers the parts of the fresh-install matrix (.dev/tests/Integration/install_matrix.php)
 * that can be judged without a database: the driver list it walks, the table
 * list it asserts on, the form it posts and how it reads the installer's answer.
 *
 * The matrix run itself is `make test-install`; this pins the contract it uses,
 * so a driver added to the forum or a table added to the installer fails here
 * instead of silently going unverified.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class InstallMatrixTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'.dev/tests/Integration/install_matrix.php';
	}

	public function testItCoversEverySupportedDriver(): void {
		$this->assertSame(forum_supported_db_types(), array_keys(install_matrix_drivers()));
	}

	/** A table the installer creates but the matrix never looks for is unverified. */
	public function testItExpectsExactlyTheTablesTheInstallerCreates(): void {
		$this->assertSame(
			install_matrix_expected_tables(),
			install_matrix_installer_tables(FORUM_ROOT.'admin/install.php')
		);
	}

	/** Sharing a prefix would make one driver's teardown drop another's tables. */
	public function testEachDriverGetsItsOwnStorage(): void {
		$storage = array();

		foreach (install_matrix_drivers() as $spec)
			$storage[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];

		$this->assertSame($storage, array_values(array_unique($storage)));
	}

	public function testItPostsEveryFieldTheInstallerReads(): void {
		$drivers = install_matrix_drivers();
		$fields = install_matrix_form_fields('pgsql', $drivers['pgsql'], 'http://localhost');

		$this->assertSame('1', $fields['form_sent']);
		$this->assertSame('pgsql', $fields['req_db_type']);
		$this->assertSame($drivers['pgsql']['prefix'], $fields['db_prefix']);
		$this->assertSame('http://localhost', $fields['req_base_url']);
		$this->assertSame('English', $fields['req_language']);

		// Everything admin/install.php reads out of $_POST behind form_sent.
		foreach (array('req_db_host', 'req_db_name', 'db_username', 'db_password',
			'req_username', 'req_email', 'req_password1') as $field)
			$this->assertArrayHasKey($field, $fields);
	}

	/** The account the matrix creates has to survive the installer's own validation. */
	public function testTheAdminAccountPassesTheInstallerValidation(): void {
		$this->assertGreaterThanOrEqual(2, utf8_strlen(INSTALL_MATRIX_USERNAME));
		$this->assertLessThanOrEqual(25, utf8_strlen(INSTALL_MATRIX_USERNAME));
		$this->assertGreaterThanOrEqual(4, utf8_strlen(INSTALL_MATRIX_PASSWORD));
		$this->assertNotSame('guest', strtolower(INSTALL_MATRIX_USERNAME));

		require_once FORUM_ROOT.'include/email.php';
		$this->assertTrue((bool) is_valid_email(INSTALL_MATRIX_EMAIL));
	}

	public function testItRecognisesTheInstallerSuccessPage(): void {
		$this->assertTrue(install_matrix_install_succeeded(
			'<h1>Final instructions</h1><p>PunBB has been fully installed! You may now go to the index.</p>'
		));
	}

	/** An error page is HTTP 200 too, so the markers are the only signal. */
	public function testItRejectsAnInstallerErrorPage(): void {
		$body = '<p class="warn">Unable to connect to database. MySQL reported: access denied</p>';

		$this->assertFalse(install_matrix_install_succeeded($body));
		$this->assertSame('Unable to connect to database. MySQL reported: access denied', install_matrix_failure_reason($body));
	}

	public function testItReadsTheDriverBackOutOfAWrittenConfig(): void {
		$this->assertSame('sqlite3', install_matrix_config_db_type("<?php\n\n\$db_type = 'sqlite3';\n\$db_host = '';\n"));
		$this->assertSame('', install_matrix_config_db_type("<?php\n\n\$db_host = 'localhost';\n"));
	}
}
