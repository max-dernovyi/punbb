<?php

use PHPUnit\Framework\TestCase;

class DoClicableTest extends TestCase {
    public function testDoClicable(): void {
        $this->assertEquals('[url=http://xn--caf-dma.com]http://café.com[/url]',
            do_clickable('http://xn--caf-dma.com', TRUE));

        $this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai]http://яндекс.рф[/url]',
            do_clickable('http://яндекс.рф', TRUE));

        $this->assertEquals('В лесу родилась [url=http://xn--d1acpjx3f.xn--p1ai/?text=ёлочка]http://яндекс.рф/?text=ёлочка[/url] и...', do_clickable('В лесу родилась http://яндекс.рф/?text=ёлочка и...', TRUE));

        $this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai]http://яндекс.рф[/url]',
            do_clickable('http://xn--d1acpjx3f.xn--p1ai', TRUE));

        $this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai/]http://яндекс.рф/[/url]',
            do_clickable('http://xn--d1acpjx3f.xn--p1ai/', TRUE));

        $this->assertEquals('[url=http://xn--d1acpjx3f.xn--p1ai/]http://яндекс.рф/[/url]',
            do_clickable('http://яндекс.рф/', TRUE));

        $this->assertEquals('[url]http://ya.ru/[/url]', do_clickable('http://ya.ru/'));
    }
}
