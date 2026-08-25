<?php

use PHPUnit\Framework\TestCase;

class FlashMessengerTest extends TestCase {
    public function testAddError(): void {
        global $forum_flash;

        $this->expectOutputString('<span class="message_error">Error message</span>');
        $forum_flash->add_error("Error message");
        $forum_flash->show();
    }

    public function testShowOnlyReturn(): void {
        global $forum_flash;

        $this->expectOutputString('');
        $forum_flash->add_error("Error message");
        $forum_flash->show(TRUE);
    }

    public function testShow(): void {
        global $forum_flash;

        $this->expectOutputString('<span class="message_error">Error message</span>');
        $forum_flash->add_error("Error message");
        $forum_flash->show(FALSE);
    }

    public function testClear(): void {
        global $forum_flash;

        $this->expectOutputString("");
        $forum_flash->add_info("Test message");
        $forum_flash->clear();
        $forum_flash->show(FALSE);
    }
}
