<?php
/**
 * Covers the asset half of `make smoke` (.dev/bin/smoke.php): every script and
 * stylesheet a page renders must resolve to a file in the checkout, and no page
 * may still emit the LABjs loader chain.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SmokeAssetGateTest extends TestCase {
	private const BASE = 'https://punbb.loc';

	public static function setUpBeforeClass(): void {
		require_once dirname(__DIR__, 3).'/bin/smoke.php';
	}

	private function root(): string {
		return rtrim(FORUM_ROOT, '/');
	}

	public function testItCollectsScriptSourcesAndStylesheetHrefs(): void {
		$body = '<link rel="stylesheet" href="/style/Oxygen/Oxygen.css" />'
			.'<script src="/include/js/punbb.common.js"></script>';

		$this->assertSame(
			array('/include/js/punbb.common.js', '/style/Oxygen/Oxygen.css'),
			smoke_asset_urls($body)
		);
	}

	/** The RSS <link rel="alternate"> is not an asset and must not be gated. */
	public function testItIgnoresLinksThatAreNotStylesheets(): void {
		$body = '<link rel="alternate" type="application/rss+xml" href="/extern.php?action=feed" />'
			.'<link rel="canonical" href="/index.php" />';

		$this->assertSame(array(), smoke_asset_urls($body));
	}

	public function testItDecodesEntitiesInAssetUrls(): void {
		$this->assertSame(
			array('/include/js/x.js?a=1&b=2'),
			smoke_asset_urls('<script src="/include/js/x.js?a=1&amp;b=2"></script>')
		);
	}

	public function testAssetsThatExistInTheCheckoutAreNotReported(): void {
		$body = '<script src="'.self::BASE.'/include/js/punbb.common.js"></script>'
			.'<link rel="stylesheet" href="'.self::BASE.'/style/Oxygen/Oxygen.css" />';

		$this->assertSame(array(), smoke_missing_assets($body, self::BASE, $this->root()));
	}

	public function testAnAssetWithNoFileBehindItIsReported(): void {
		$body = '<script src="'.self::BASE.'/include/js/LAB.src.js"></script>';

		$this->assertSame(
			array(self::BASE.'/include/js/LAB.src.js'),
			smoke_missing_assets($body, self::BASE, $this->root())
		);
	}

	public function testTheQueryStringIsNotPartOfThePath(): void {
		$body = '<script src="'.self::BASE.'/include/js/punbb.common.js?v=1#x"></script>';

		$this->assertSame(array(), smoke_missing_assets($body, self::BASE, $this->root()));
	}

	#[DataProvider('offsiteUrls')]
	public function testOffsiteAssetsAreLeftAlone(string $url): void {
		$this->assertSame(array(), smoke_missing_assets('<script src="'.$url.'"></script>', self::BASE, $this->root()));
	}

	public static function offsiteUrls(): array {
		return array(
			array('https://cdn.example.com/jquery.js'),
			array('http://cdn.example.com/jquery.js'),
			array('//cdn.example.com/jquery.js'),
		);
	}

	/** A protocol-relative URL on our own host is ours, not an offsite CDN. */
	public function testProtocolRelativeUrlsOnOurOwnHostAreChecked(): void {
		$host = preg_replace('#^https?:#', '', self::BASE);

		$this->assertSame(
			array($host.'/include/js/nope.js'),
			smoke_missing_assets('<script src="'.$host.'/include/js/nope.js"></script>', self::BASE, $this->root())
		);
		$this->assertSame(
			array(),
			smoke_missing_assets('<script src="'.$host.'/include/js/punbb.common.js"></script>', self::BASE, $this->root())
		);
	}

	public function testRootRelativeUrlsResolveAgainstTheCheckout(): void {
		$this->assertSame(
			array(),
			smoke_missing_assets('<script src="/include/js/punbb.timezone.js"></script>', self::BASE, $this->root())
		);
		$this->assertSame(
			array('/include/js/nope.js'),
			smoke_missing_assets('<script src="/include/js/nope.js"></script>', self::BASE, $this->root())
		);
	}

	public function testEveryBundleTheLoaderCanServeExists(): void {
		foreach (array(
			'include/js/punbb.common.js',
			'include/js/punbb.timezone.js',
			'include/js/punbb.install.js',
			'style/Oxygen/Oxygen.css',
		) as $asset)
			$this->assertFileExists(FORUM_ROOT.$asset);
	}

	/**
	 * The sweep URL and the forum's $base_url need not match — a page rendered
	 * with $base_url must still be gated when swept on http://localhost.
	 */
	public function testEveryBaseGivenIsStrippedFromAnAssetUrl(): void {
		$body = '<script src="https://punbb.loc/include/js/punbb.common.js"></script>'
			.'<script src="https://punbb.loc/include/js/nope.js"></script>';

		$this->assertSame(
			array('https://punbb.loc/include/js/nope.js'),
			smoke_missing_assets($body, array('http://localhost', 'https://punbb.loc'), $this->root())
		);

		// Without the forum's own base the same URLs read as off-site and are skipped.
		$this->assertSame(array(), smoke_missing_assets($body, array('http://localhost'), $this->root()));
	}

	public function testAnEmptyBaseIsNotTreatedAsAPrefix(): void {
		$this->assertSame(
			array('/include/js/nope.js'),
			smoke_missing_assets('<script src="/include/js/nope.js"></script>', array('', null), $this->root())
		);
	}

	public function testTheLabjsGateFiresOnTheLoaderChain(): void {
		$this->assertTrue(smoke_labjs_references('<script>$LAB.script("x.js").wait();</script>'));
		$this->assertFalse(smoke_labjs_references('<script src="/include/js/punbb.common.js"></script>'));
	}

	/** The tags admin/install.php and admin/db_update.php emit outside the Loader. */
	public function testTheWizardScriptTagsResolveToFilesThatExist(): void {
		foreach (array('admin/install.php', 'admin/db_update.php') as $wizard)
		{
			$source = (string) file_get_contents(FORUM_ROOT.$wizard);
			preg_match_all('#<script[^>]+src="[^"]*?(include/js/[\w./]+\.js)"#', $source, $matches);

			$this->assertNotEmpty($matches[1], $wizard.' renders no script tag');

			foreach (array_unique($matches[1]) as $asset)
				$this->assertFileExists(FORUM_ROOT.ltrim($asset, '/'), $wizard.' references '.$asset);
		}
	}
}
