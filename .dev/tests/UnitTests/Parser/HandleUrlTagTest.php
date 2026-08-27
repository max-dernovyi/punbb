<?php

use PHPUnit\Framework\TestCase;

class HandleUrlTagTest extends TestCase {
	public function testHandleUrlTag(): void {
		$this->assertEquals('<a href="http://ya.ru/">http://ya.ru/</a>', handle_url_tag('http://ya.ru/'));
		$this->assertEquals('<a href="http://ya.ru">http://ya.ru</a>', handle_url_tag('http://ya.ru'));
		$this->assertEquals('<a href="http://ya.ru">ya.ru</a>', handle_url_tag('ya.ru'));
		$this->assertEquals('<a href="http://www.ya.ru">www.ya.ru</a>', handle_url_tag('www.ya.ru'));
		$this->assertEquals('<a href="ftp://ya.ru/">ftp://ya.ru/</a>', handle_url_tag('ftp://ya.ru/'));
	}

	public function testHandleUrlTagWithBadChars(): void {
		$this->assertEquals('<a href="http://ya.ru/?cache=123">http://ya.ru/?cache=123</a>', handle_url_tag('http://ya.ru/?cache=123'));
		$this->assertEquals('<a href="http://ya.ru/?cache=123%204">http://ya.ru/?cache=123 4</a>', handle_url_tag('http://ya.ru/?cache=123 4'));
		$this->assertEquals('<a href="http://ya.ru/?cache=123">http://ya.ru/?cache=123"</a>', handle_url_tag('http://ya.ru/?cache=123"'));
	}

	public function testHandleUrlTagWithBBcode(): void {
		$this->assertEquals('[url=http://ya.ru][/url]', handle_url_tag('http://ya.ru', '', TRUE));
		$this->assertEquals('[url=http://ya.ru][/url]', handle_url_tag('ya.ru', '', TRUE));
		$this->assertEquals('[url=http://www.ya.ru][/url]', handle_url_tag('www.ya.ru', '', TRUE));
		$this->assertEquals('[url=ftp://ya.ru/][/url]', handle_url_tag('ftp://ya.ru/', '', TRUE));
	}

	public function testHandleUrlTagInternational(): void {
		$this->assertEquals('<a href="http://xn--l1adgmc.xn--p1ai?viewtopic=1234#p4">http://форум.рф?viewtopic=1234#p4</a>', handle_url_tag('http://форум.рф?viewtopic=1234#p4'));
		$this->assertEquals('<a href="http://xn--caf-dma.com">http://café.com</a>', handle_url_tag('http://café.com'));
		$this->assertEquals('<a href="http://xn--caf-dma.com">http://café.com</a>', handle_url_tag('http://xn--caf-dma.com'));
	}

	/**
	 * UTS-46 rejects hosts IDNA2003 used to encode anyway. forum_idna_encode()
	 * hands the URL back unchanged, so the raw host reaches the href.
	 */
	public function testHandleUrlTagUnconvertibleHost(): void {
		$this->assertEquals('<a href="http://-пример-.рф/x">http://-пример-.рф/x</a>', handle_url_tag('http://-пример-.рф/x'));
		// Nothing to encode, so no [url=...] alias is emitted.
		$this->assertEquals('[url]http://-пример-.рф[/url]', do_clickable('http://-пример-.рф', TRUE));
	}

	public function testDoClicable(): void {
		$this->assertEquals('[url=http://xn--caf-dma.com]http://café.com[/url]', do_clickable('http://xn--caf-dma.com', TRUE));
		$this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai]http://яндекс.рф[/url]', do_clickable('http://яндекс.рф', TRUE));
		$this->assertEquals('В лесу родилась [url=http://xn--d1acpjx3f.xn--p1ai/?text=ёлочка]http://яндекс.рф/?text=ёлочка[/url] и...', do_clickable('В лесу родилась http://яндекс.рф/?text=ёлочка и...', TRUE));
		$this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai]http://яндекс.рф[/url]', do_clickable('http://xn--d1acpjx3f.xn--p1ai', TRUE));
		$this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai/]http://яндекс.рф/[/url]', do_clickable('http://xn--d1acpjx3f.xn--p1ai/', TRUE));
		$this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai/]http://яндекс.рф/[/url]', do_clickable('http://яндекс.рф/', TRUE));
		$this->assertEquals('[url]http://ya.ru/[/url]', do_clickable('http://ya.ru/'));
	}
}
