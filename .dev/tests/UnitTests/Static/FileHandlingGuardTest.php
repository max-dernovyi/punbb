<?php
/**
 * What decides a filename, and what decides an include path.
 *
 * Two surfaces meet here. The avatar upload is the only way a visitor puts a
 * file on disk, and the stored name has to come from the account id and the
 * detected image type, never from the request. The language and style names
 * come out of a profile and are concatenated into require() paths in 56 places,
 * so the four sites that write them are the only barrier.
 *
 * Both are audit pins: they hold on the current code, and the point is that
 * they keep holding.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileHandlingGuardTest extends TestCase
{
	/**
	 * The sanitiser every site that writes a language, style or URL scheme
	 * name has to run: strip the three characters that could build a path,
	 * then require the resulting directory to exist.
	 */
	private const SANITISER = "preg_replace('#[\\.\\\\\\/]#', '', ";

	private const UPLOAD = 'include/PunBB/Module/Profile/Controller/AvatarUpload.php';

	private function read(string $file): string
	{
		return (string) file_get_contents(FORUM_ROOT.$file);
	}

	/** The stored avatar is named from the account id and nothing else. */
	public function testTheAvatarNameComesFromTheAccountId(): void
	{
		$this->assertStringContainsString('$id = ProfilePage::integer($request->query[\'id\'] ?? 0);', $this->read('include/PunBB/Module/Profile/Controller/ProfileController.php'),
			'the id the avatar is named after is no longer an integer');
		$this->assertStringContainsString('$this->files->place($temporary, $directory.\'/\'.$user->id().$extension);', $this->read(self::UPLOAD));
	}

	/** And the extension from what getimagesize() found, not from the upload. */
	public function testTheAvatarExtensionComesFromTheDetectedType(): void
	{
		$source = $this->read(self::UPLOAD);

		foreach (array('.gif' => 'IMAGETYPE_GIF', '.jpg' => 'IMAGETYPE_JPEG', '.png' => 'IMAGETYPE_PNG') as $extension => $type)
			$this->assertMatchesRegularExpression('#'.$type.'\t=> array\(\''.preg_quote($extension, '#').'\', [123]\),#', $source,
				'the '.$extension.' extension is no longer tied to '.$type);

		$this->assertStringContainsString('[$width, $height, $type] = $image ?? array(0, 0, 0);', $source);
		$this->assertStringContainsString('[$extension, $avatarType] = self::TYPES[$type] ?? array(\'\', 0);', $source);
		$this->assertStringContainsString('if ($errors === array() && (!in_array($typed->avatarType(), $accepting->imageTypes(), true) || $extension === \'\'))', $source);
		$this->assertStringContainsString('$info = @getimagesize($file);', $this->read('include/PunBB/Module/Profile/Model/UploadedFiles.php'));
	}

	/**
	 * The one attacker-controlled string in $_FILES is the client's filename.
	 * It must reach nothing: not the stored name, not the extension, not a log.
	 */
	public function testTheUploadedFilenameIsNeverUsed(): void
	{
		$source = $this->read(self::UPLOAD);

		$this->assertStringNotContainsString('[\'name\']', $source,
			'the avatar upload reads the client-supplied filename');
		$this->assertStringNotContainsString('pathinfo(', $source);
	}

	/** The temporary file is named from the id too, and never survives a rejection. */
	public function testTheTemporaryUploadIsNamedFromTheAccountId(): void
	{
		$source = $this->read(self::UPLOAD);

		$this->assertStringContainsString('$temporary = $directory.\'/\'.$user->id().\'.tmp\';', $source);

		$start = (int) strpos($source, '$temporary = ');
		$block = substr($source, $start, (int) strpos($source, '$this->removal->remove($user->id());') - $start);

		// Five things can go wrong after the move is attempted, and four of
		// them leave a file behind unless they delete it. The fifth is the
		// move itself failing, which leaves nothing.
		$this->assertSame(5, substr_count($block, '$errors[] = '), 'the avatar rejections have changed shape');
		$this->assertSame(4, substr_count($block, '$this->files->delete($temporary);'),
			'a rejected upload can be left behind in the avatar directory');
		$this->assertStringContainsString('\'Move failed\'', $block);
	}

	/**
	 * Every site that writes a name later concatenated into a require() path.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function nameWriterProvider(): array
	{
		return array(
			'install language'  => array('include/PunBB/Module/LegacyBridge/Setup/LegacyInstallerLanguage.php', 'LegacyChromeSource::root().\'lang/\'.$language.\'/install.php\''),
		);
	}

	/** A name that names no directory the forum ships is refused on the way in. */
	#[DataProvider('nameWriterProvider')]
	public function testAWrittenNameHasToNameAnExistingDirectory(string $file, string $check): void
	{
		$this->assertStringContainsString('!file_exists('.$check.')', $this->read($file),
			$file.': "'.$check.'" is no longer required to exist');
	}

	/** ...after the three path characters have been stripped from it. */
	#[DataProvider('nameWriterProvider')]
	public function testAWrittenNameIsStrippedOfPathCharacters(string $file, string $check): void
	{
		$this->assertGreaterThan(0, substr_count($this->read($file), self::SANITISER),
			$file.': the path characters are no longer stripped from the name');
	}

	/**
	 * The settings take a default style, language and URL scheme through the
	 * module: stripped of the path characters, then one of the packs the board
	 * lists, which are the directories holding the pack's own file.
	 */
	public function testTheSettingsTakeOnlyAPackTheBoardHas(): void
	{
		$controller = $this->read('include/PunBB/Module/Settings/Controller/SettingsController.php');
		$functions = $this->read('include/functions.php');

		$this->assertStringContainsString("\$form->set(\$name, (string) preg_replace('#[\\.\\\\\\/]#', '', (string) (\$form->value(\$name) ?? '')));", $controller);
		$this->assertStringContainsString("if (!in_array(\$form->value('default_style'), \$this->packs->styles(), true)\n\t\t\t|| !in_array(\$form->value('default_lang'), \$this->packs->languages(), true)\n\t\t\t|| !in_array(\$form->value('sef'), \$this->packs->urlSchemes(), true))", $controller);
		$this->assertStringContainsString("if (is_dir(\$dirname) && file_exists(\$dirname.'/'.\$tempname.'.php'))", $functions);
		$this->assertStringContainsString("if (is_dir(\$dirname) && file_exists(\$dirname.'/common.php'))", $functions);
		$this->assertStringContainsString("if (is_dir(\$dirname) && file_exists(\$dirname.'/forum_urls.php'))", $functions);
	}

	/**
	 * A profile's settings take a language and a style through the module:
	 * stripped of the path characters, then one of the packs the board lists.
	 */
	public function testTheProfileTakesOnlyAPackTheBoardHas(): void
	{
		$update = $this->read('include/PunBB/Module/Profile/Controller/DetailsUpdate.php');

		$this->assertStringContainsString("private const PATH_CHARACTERS = '#[\\.\\\\\\/]#';", $update);
		foreach (array('language' => 'languages', 'style' => 'styles') as $field => $packs)
			$this->assertStringContainsString("\$details->set('".$field."', (string) preg_replace(self::PATH_CHARACTERS, '', (string) \$details->value('".$field."')));\n\t\t\tif (!in_array(\$details->value('".$field."'), \$this->packs->".$packs."(), true))", $update);
	}

	/**
	 * The registration takes a language through the module: stripped of the
	 * path characters, then one of the packs the board has, which are the
	 * directories of lang/ holding a common.php.
	 */
	public function testTheRegistrationTakesOnlyALanguageTheBoardHas(): void
	{
		$controller = $this->read('include/PunBB/Module/Register/Controller/RegisterController.php');

		$this->assertStringContainsString("private const PATH_CHARACTERS = '#[\\.\\\\\\/]#';", $controller);
		$this->assertStringContainsString("\$language = (string) preg_replace(self::PATH_CHARACTERS, '', \$request->post['language']);\n\t\t\tif (!in_array(\$language, \$this->language->available(), true))", $controller);
		$this->assertStringContainsString("file_exists(\$root.\$entry.'/common.php')", $this->read('include/PunBB/Module/LegacyBridge/Site/LegacyLanguage.php'));
	}

	/**
	 * The sanitiser itself: whatever survives it cannot build a path.
	 *
	 * @return array<string, array{string}>
	 */
	public static function hostileNameProvider(): array
	{
		return array(
			'traversal'     => array('../../include'),
			'absolute'      => array('/etc'),
			'backslash'     => array('..\\..\\include'),
			'dot segment'   => array('./English'),
			'wrapper'       => array('php://filter'),
			'trailing dot'  => array('English.'),
		);
	}

	#[DataProvider('hostileNameProvider')]
	public function testTheSanitiserLeavesNothingThatBuildsAPath(string $name): void
	{
		$clean = (string) preg_replace('#[\.\\\/]#', '', $name);

		$this->assertStringNotContainsString('/', $clean);
		$this->assertStringNotContainsString('\\', $clean);
		$this->assertStringNotContainsString('.', $clean);

		// Whatever is left names something inside lang/, or names nothing.
		$resolved = realpath(FORUM_ROOT.'lang/'.$clean);
		if ($resolved !== false)
			$this->assertSame(realpath(FORUM_ROOT.'lang'), dirname($resolved),
				'"'.$name.'" survives the sanitiser as a path out of lang/');
	}

	/**
	 * What validate_manifest() actually checks before an extension's <hook>
	 * content is stored and eval()ed. It is a shape check and a "does this
	 * parse without leaving inline HTML" check — not a safety check, because
	 * an extension is code by design. Pinned so the claim stays accurate.
	 */
	public function testValidateManifestChecksShapeAndNothingAboutTheCode(): void
	{
		$source = $this->read('include/xml.php');
		$body = substr($source, (int) strpos($source, 'function validate_manifest('));

		foreach (array('$ext[\'id\'] != $folder_name', '$ext[\'attributes\'][\'engine\'] != \'1.0\'',
			'preg_match(\'/[^a-z0-9\- \.]+/i\', $ext[\'version\'])', 'token_get_all(\'<?php \'.$hook[\'content\'])',
			'$last_element[0] == T_INLINE_HTML') as $check)
			$this->assertStringContainsString($check, $body, 'validate_manifest() no longer performs: '.$check);

		// The hook code is read in three places and no more: two emptiness
		// checks and the trailing-inline-HTML test. Nothing inspects what it
		// calls, and nothing here is a safety check.
		$this->assertSame(1, substr_count($body, 'token_get_all('));
		$this->assertSame(3, substr_count($body, '$hook[\'content\']'),
			'validate_manifest() now claims to filter hook code — say so in the audit');
	}

	/** The extension id reaching the filesystem is stripped to one path segment. */
	public function testTheExtensionIdCannotLeaveTheExtensionsDirectory(): void
	{
		$source = $this->read('include/PunBB/Module/Extensions/Controller/ExtensionsController.php');

		$this->assertStringContainsString('private const ID = \'/[^0-9a-z_]/\';', $source);
		$this->assertSame(3, substr_count($source, '$id = (string) preg_replace(self::ID, \'\', '), 'an install, an uninstall and a switch each strip the id');

		foreach (array('../../config.php', '..\\config', "cache\0.php", 'a/b') as $hostile)
		{
			$clean = (string) preg_replace('/[^0-9a-z_]/', '', $hostile);

			$this->assertMatchesRegularExpression('/\A[0-9a-z_]*\z/', $clean,
				'"'.$hostile.'" survives as more than one path segment');
		}
	}
}
