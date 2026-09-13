<?php
/**
 * The class tree under include/PunBB/ is never served: a class file requested
 * directly fatals on its unloaded dependencies and can print the server path.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class ClassTreeGuardTest extends TestCase
{
	public function testTheRootRulesDenyTheClassTree(): void
	{
		$htaccess = (string) file_get_contents(FORUM_ROOT.'.htaccess.dist');

		$this->assertMatchesRegularExpression('#RewriteRule \^\([^)]*\|include/PunBB\)/ - \[F,L\]#', $htaccess);
		$this->assertMatchesRegularExpression('#RedirectMatch 404 \(\^\|/\)\([^)]*\|include/PunBB\)/#', $htaccess);
	}
}
