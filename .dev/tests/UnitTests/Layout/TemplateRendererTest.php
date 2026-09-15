<?php
/**
 * A template escapes by default: text a template prints is encoded for HTML
 * unless it was built as Html, and the escaping helpers the layout builds its
 * regions with agree with the legacy ones extension code calls.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateException;
use PunBB\Module\Layout\View\TemplateRenderer;

class TemplateRendererTest extends TestCase {
	private const TOPIC = FORUM_ROOT.'.dev/tests/fixtures/templates/topic.phtml';

	public function testTextIsEscapedAndHtmlIsNot(): void {
		$page = (new TemplateRenderer())->render(self::TOPIC, array(
			'subject'	=> '<script>alert("x")</script> & "quotes"',
			'posts'		=> array(
				array('id' => 7, 'message' => new Html('<strong>bold</strong>')),
				array('id' => '8" onclick="x', 'message' => '<em>not markup</em>'),
			),
			'closed'	=> true,
			'notice'	=> new Html('Closed &mdash; <a href="#">why</a>'),
		));

		$this->assertSame(
			"<div class=\"main-head\">\n".
			"\t<h2 class=\"hn\"><span>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &quot;quotes&quot;</span></h2>\n".
			"</div>\n".
			"<p class=\"post\" data-id=\"7\"><strong>bold</strong></p>\n".
			"<p class=\"post\" data-id=\"8&quot; onclick=&quot;x\">&lt;em&gt;not markup&lt;/em&gt;</p>\n".
			"<p class=\"closed\">Closed &mdash; <a href=\"#\">why</a></p>",
			$page
		);
	}

	public function testAnObjectThatIsNotHtmlDoesNotReachATemplate(): void {
		$this->expectException(TemplateException::class);
		$this->expectExceptionMessage('$posts holds a stdClass');

		(new TemplateRenderer())->render(self::TOPIC, array('subject' => '', 'posts' => array(new stdClass()), 'closed' => false, 'notice' => ''));
	}

	/** @return array<string, array{string}> */
	public static function reservedNameProvider(): array {
		return array(
			'this'					=> array('this'),
			'the renderer\'s own'	=> array('__template'),
			'not a variable name'	=> array('two words'),
		);
	}

	#[DataProvider('reservedNameProvider')]
	public function testANameATemplateCannotHaveIsRefused(string $name): void {
		$this->expectException(TemplateException::class);

		(new TemplateRenderer())->render(self::TOPIC, array($name => 'x'));
	}

	public function testATemplateThatThrowsLeavesNoBufferOpenAndNothingPrinted(): void {
		$level = ob_get_level();
		ob_start();

		try {
			(new TemplateRenderer())->render(FORUM_ROOT.'.dev/tests/fixtures/templates/broken.phtml', array());
			$this->fail('the template\'s exception did not reach the caller');
		}
		catch (RuntimeException $e) {
			$this->assertSame('the template failed', $e->getMessage());
		}

		$this->assertSame('', ob_get_clean());
		$this->assertSame($level, ob_get_level());
	}

	/** @return array<string, array{string}> */
	public static function textProvider(): array {
		return array(
			'markup'		=> array('<b class="x">Tom & Jerry\'s</b>'),
			'entities'		=> array('&amp; &#160; &rarr;'),
			'non-ascii'		=> array('Ёжик 封鎖'),
			'invalid utf-8'	=> array("caf\xE9"),
			'whitespace'	=> array(" \t\xC2\xA0 padded \n\xC2\xA0"),
		);
	}

	#[DataProvider('textProvider')]
	public function testHtmlEscapesAndTrimsAsTheLegacyHelpersDo(string $text): void {
		$this->assertSame(forum_htmlencode($text), Html::escape($text)->html);
		$this->assertSame((string) forum_trim($text), (new Html($text))->trim()->html);
	}

	public function testFormatEscapesTextAndKeepsHtml(): void {
		$this->assertSame('<a href="x?a=1&amp;b=2">Tom &lt;3 &amp; <i>Jerry</i></a> 3',
			Html::format('<a href="%s">%s</a> %s', new Html('x?a=1&amp;b=2'), Html::format('%s &amp; %s', 'Tom <3', new Html('<i>Jerry</i>')), 3)->html);

		$this->assertSame('<span>Logged in as <strong>&lt;admin&gt;</strong>.</span>',
			Html::format('<span>%s</span>', Html::format(new Html('Logged in as %s.'), Html::format('<strong>%s</strong>', '<admin>')))->html);
	}
}
