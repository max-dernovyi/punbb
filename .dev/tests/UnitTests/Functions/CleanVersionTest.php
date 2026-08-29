<?php

use PHPUnit\Framework\TestCase;

class CleanVersionTest extends TestCase {
    public function testCleanVersion(): void {
        $this->assertEquals('1.5', clean_version('1.5.0'));
        $this->assertEquals('0.5.10', clean_version('0.5.10'));
        $this->assertEquals('0.5.100', clean_version('0.5.100'));
        $this->assertEquals('1.5.1', clean_version('1.5.1'));
    }

    public function testCleanVersionEdgeCases(): void {
        $this->assertEquals('', clean_version(''));
        $this->assertEquals('1.4.4', clean_version('1.4.4'));
        $this->assertEquals('1.4', clean_version('1.4.0'));
    }
}
