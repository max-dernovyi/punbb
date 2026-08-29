<?php
/**
 * xml_to_array() over the manifest shapes admin/extensions.php feeds it.
 *
 * The parser tracked repeated tags in $multi_key and $level without ever
 * initialising them, so the first close tag below the root incremented an
 * undefined variable. The suite runs with failOnWarning, so a regression fails
 * this test by itself.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class XmlToArrayTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once FORUM_ROOT.'include/xml.php';
	}

	private function manifest(string $notes): string {
		return '<?xml version="1.0" encoding="utf-8"?>'.
			'<extension engine="1.0">'.
			'<id>test_ext</id>'.
			'<title>Test</title>'.
			'<version>1.0</version>'.
			$notes.
			'</extension>';
	}

	public function testTheManifestBodyIsParsedIntoAnArray(): void {
		$data = xml_to_array($this->manifest('<note>one</note>'));

		$this->assertSame('test_ext', $data['extension']['id']);
		$this->assertSame('1.0', $data['extension']['version']);
	}

	/**
	 * A lone <note> parses to a scalar; normalising it used to call current()
	 * on that string, which is a TypeError since PHP 8.
	 */
	public function testASingleNoteIsNormalisedToAList(): void {
		$data = xml_to_array($this->manifest('<note>one</note>'));

		$this->assertIsArray($data['extension']['note']);
		$this->assertSame('one', $data['extension']['note'][0]);
	}

	public function testAManifestWithNoNoteGetsAnEmptyNoteList(): void {
		$data = xml_to_array($this->manifest(''));

		$this->assertSame(array(), $data['extension']['note']);
	}

	public function testNestedRepeatedElementsAreCollectedWithoutWarnings(): void {
		$xml = $this->manifest('<hooks><hook id="a">x</hook><hook id="b">y</hook></hooks>');
		$data = xml_to_array($xml);

		$hooks = $data['extension']['hooks']['hook'];

		$this->assertCount(2, $hooks);
		$this->assertSame('a', $hooks[0]['attributes']['id']);
		$this->assertSame('b', $hooks[1]['attributes']['id']);
	}

	public function testUnparsableInputYieldsAnEmptyArray(): void {
		$this->assertSame(array(), xml_to_array('not xml at all'));
	}

	/** admin/extensions.php passes false when it could not read the manifest. */
	public function testAFailedManifestReadYieldsAnEmptyArray(): void {
		$this->assertSame(array(), xml_to_array(false));
	}
}
