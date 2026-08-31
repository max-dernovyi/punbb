<?php
/**
 * The BBCode tags that build HTML attributes out of user text.
 *
 * [img], [url], [email] and the autolinker all concatenate a captured
 * substring straight into `src="…"` / `href="…"` without escaping it there.
 * They are safe for one reason only: parse_message() and parse_signature()
 * run forum_htmlencode() over the whole post *before* do_bbcode() sees it, so
 * by the time a tag matches there is no raw quote, `<` or `>` left to capture.
 *
 * That ordering is the entire defence, and it is easy to lose — a hook, a
 * reordering, or a tag added after the encode would remove it silently. These
 * cases drive the attribute break-outs through the real render path and assert
 * the encode still happened first.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttributeContextTest extends TestCase
{
	public static function attributeBreakouts(): array
	{
		return array(
			'img url'			=> array('[img]http://x"onerror=alert(1)//y.png[/img]'),
			'img url tag'		=> array('[img]http://x.png"><script>alert(1)</script>[/img]'),
			'img alt'			=> array('[img=a" onerror="alert(1)]http://y/z.png[/img]'),
			'url plain'			=> array('[url]http://x"onmouseover=alert(1)[/url]'),
			'url with label'	=> array('[url=http://x"onmouseover=alert(1)]click[/url]'),
			'url label tag'		=> array('[url=http://y/]<img src=x onerror=alert(1)>[/url]'),
			'email plain'		=> array('[email]a"onmouseover="alert(1)@b.c[/email]'),
			'email with label'	=> array('[email=a"onmouseover="alert(1)@b.c]text[/email]'),
			'quote cite'		=> array('[quote="a\\"onmouseover=\\"alert(1)"]x[/quote]'),
			'autolink'			=> array('go to http://evil"onmouseover=alert(1)/x now'),
		);
	}

	// [quote] is rejected in a signature before any of this runs, so it has
	// nothing to say about the encode ordering there.
	public static function signatureBreakouts(): array
	{
		$cases = self::attributeBreakouts();
		unset($cases['quote cite']);

		return $cases;
	}

	private function render(string $src): string
	{
		$errors = array();

		return parse_message(preparse_bbcode(forum_trim($src), $errors), 0);
	}

	//
	// Every `"` the input contributes has to arrive as `&quot;`. A raw one in
	// the rendered markup can only have come from the post, and it would close
	// whichever attribute it landed in.
	//
	#[DataProvider('attributeBreakouts')]
	public function testTheInjectedQuoteNeverReachesTheMarkupRaw(string $src): void
	{
		$html = $this->render($src);

		// Strip the attributes the parser writes itself, quotes and all; what
		// is left is the part the post is responsible for.
		$this->assertStringNotContainsString('"', $this->outsideAttributes($html),
			'a quote from the post survived into the markup: '.$html);
	}

	#[DataProvider('attributeBreakouts')]
	public function testTheInjectedTagNeverReachesTheMarkupRaw(string $src): void
	{
		$html = $this->render($src);

		// The four shapes the cases above try to produce. Each needs a raw `<`
		// or a raw `"` to exist at all, so none can survive the encode.
		foreach (array('<script', '<img src=x', '"onerror=', '"onmouseover=') as $breakout)
			$this->assertStringNotContainsString($breakout, $html,
				'the post produced live markup: '.$html);
	}

	//
	// Signatures take the same route through do_bbcode(), with its own encode
	// in parse_signature(); pin that one too rather than assuming they match.
	//
	#[DataProvider('signatureBreakouts')]
	public function testSignaturesEncodeBeforeTheTagsRun(string $src): void
	{
		$errors = array();
		$html = parse_signature(preparse_bbcode(forum_trim($src), $errors, true));

		$this->assertStringNotContainsString('"', $this->outsideAttributes($html),
			'a quote from the signature survived into the markup: '.$html);
	}

	//
	// [color] takes its argument from a whitelist rather than from the encode,
	// so a value outside it must not reach the style attribute at all.
	//
	public function testColourOnlyAcceptsANameOrAHexTriplet(): void
	{
		$this->assertStringContainsString('style="color: red"', $this->render('[color=red]x[/color]'));
		$this->assertStringContainsString('style="color: #ff0000"', $this->render('[color=#ff0000]x[/color]'));

		foreach (array('red;background:url(javascript:alert(1))', 'expression(alert(1))', '#ff0000;x') as $hostile)
			$this->assertStringNotContainsString('style="color:', $this->render('[color='.$hostile.']x[/color]'),
				$hostile.' reached the style attribute');
	}

	//
	// handle_url_tag() prepends http:// to anything whose scheme is not three
	// to six characters followed by `://`, which is what keeps `javascript:`
	// out of href. Pin the outcome, not the regex.
	//
	public function testASchemeThatCanRunScriptIsNeutralised(): void
	{
		foreach (array('javascript:alert(1)', 'javascript://%0aalert(1)', 'vbscript:alert(1)', 'data:text/html,<script>alert(1)</script>') as $hostile)
		{
			$html = $this->render('[url]'.$hostile.'[/url]');

			$this->assertMatchesRegularExpression('#href="http://#', $html,
				$hostile.' kept its own scheme in href: '.$html);
			$this->assertStringNotContainsString('href="'.$hostile, $html,
				$hostile.' still reached href unprefixed: '.$html);
		}
	}

	//
	// The negative control: without the encode the very same input does break
	// out, so the assertions above are testing something.
	//
	public function testTheBreakoutIsRealWithoutTheEncode(): void
	{
		$this->assertStringContainsString('"onerror=alert(1)',
			handle_img_tag('http://x"onerror=alert(1)//y.png'));
	}

	private function outsideAttributes(string $html): string
	{
		return (string) preg_replace('#(?:src|href|alt|class|style)="[^"]*"#', '', $html);
	}
}
