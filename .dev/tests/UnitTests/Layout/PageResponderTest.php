<?php
/**
 * A module's page as a response: its chrome is opened before its content is
 * rendered, and the two are sent with the headers every page carries.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Page/PageFakes.php';

class PageResponderTest extends TestCase {
	public function testTheChromeOpensBeforeTheContentRendersAndClosesAroundIt(): void {
		$log = array();
		$chromes = new FakeChromeFactory();
		$chromes->log = &$log;

		$response = (new PageResponder($chromes))->respond(new PageHead('probe', array(new Crumb('Board'))), function () use (&$log): array {
			$log[] = 'content';

			return array('main' => new Html('<p>main</p>'));
		}, 403);

		$this->assertSame(array('open', 'content'), $log);
		$this->assertSame('[probe]<p>main</p>', $response->body);
		$this->assertSame(403, $response->status);
		$this->assertSame(array('Expires', 'Last-Modified', 'Cache-Control', 'Pragma', 'Content-type'), array_keys($response->headers));
		$this->assertSame('probe', $chromes->opened[0]->id);
	}
}
