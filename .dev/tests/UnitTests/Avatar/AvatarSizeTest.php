<?php
/**
 * Covers forum_avatar_size(), the seam that replaced the suppressed
 * getimagesize() call in profile.php and admin/db_update.php.
 *
 * The old code destructured the return value straight into a list(), so a file
 * that is not an image unpacked false into nulls behind a suppressed warning.
 * These tests pin the four upload outcomes: valid image, corrupt file,
 * non-image file, and an image past the board's size limits.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class AvatarSizeTest extends TestCase {
	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir().'/punbb-avatar-'.getmypid();
		if (!is_dir($this->dir))
			mkdir($this->dir, 0777, true);
	}

	protected function tearDown(): void {
		foreach (glob($this->dir.'/*') as $file)
			unlink($file);

		if (is_dir($this->dir))
			rmdir($this->dir);
	}

	private function write(string $name, string $bytes): string {
		$path = $this->dir.'/'.$name;
		file_put_contents($path, $bytes);

		return $path;
	}

	/** A GIF89a header is all getimagesize() reads for dimensions. */
	private function gif(int $width, int $height): string {
		return 'GIF89a'.pack('vv', $width, $height)."\x00\x00\x00";
	}

	/** A structurally valid single-colour PNG — no ext-gd in the container. */
	private function png(int $width, int $height): string {
		$chunk = function (string $type, string $data): string {
			return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
		};

		$scanline = "\x00".str_repeat("\x00", $width * 3);

		return "\x89PNG\r\n\x1a\n"
			.$chunk('IHDR', pack('NN', $width, $height)."\x08\x02\x00\x00\x00")
			.$chunk('IDAT', gzcompress(str_repeat($scanline, $height)))
			.$chunk('IEND', '');
	}

	/** SOI + SOF0 + EOI: the markers getimagesize() walks to find the frame. */
	private function jpeg(int $width, int $height): string {
		$sof0 = "\x08".pack('nn', $height, $width)."\x03\x01\x11\x00\x02\x11\x01\x03\x11\x01";

		return "\xFF\xD8\xFF\xC0".pack('n', strlen($sof0) + 2).$sof0."\xFF\xD9";
	}

	public function testValidImagesReportTheirSizeAndType(): void {
		$this->assertSame(array(48, 32, IMAGETYPE_GIF), forum_avatar_size($this->write('a.gif', $this->gif(48, 32))));
		$this->assertSame(array(16, 24, IMAGETYPE_PNG), forum_avatar_size($this->write('a.png', $this->png(16, 24))));
		$this->assertSame(array(60, 40, IMAGETYPE_JPEG), forum_avatar_size($this->write('a.jpg', $this->jpeg(60, 40))));
	}

	public function testCorruptImageIsRejected(): void {
		// A PNG signature and a truncated IHDR: the header promises an image the
		// file does not contain. getimagesize() warns and returns false.
		$truncated = substr($this->png(16, 16), 0, 20);

		$this->assertFalse(forum_avatar_size($this->write('corrupt.png', $truncated)));
		$this->assertFalse(forum_avatar_size($this->write('empty.png', '')));
	}

	public function testNonImageIsRejected(): void {
		$this->assertFalse(forum_avatar_size($this->write('script.gif', "<?php echo 'hi';")));
		$this->assertFalse(forum_avatar_size($this->write('text.png', str_repeat("not an image\n", 64))));
	}

	public function testMissingPathIsRejected(): void {
		$this->assertFalse(forum_avatar_size($this->dir.'/nothing-here.png'));
		$this->assertFalse(forum_avatar_size($this->dir));
		$this->assertFalse(forum_avatar_size(''));
	}

	/**
	 * An oversized upload is a real image — the caller compares the returned
	 * dimensions against o_avatars_width / o_avatars_height and unlinks it.
	 */
	public function testOversizedImageReportsSizeSoTheCallerCanRejectIt(): void {
		$max_width = 60;
		$max_height = 60;

		$size = forum_avatar_size($this->write('big.png', $this->png(200, 150)));

		$this->assertSame(array(200, 150, IMAGETYPE_PNG), $size);
		$this->assertTrue($size[0] > $max_width || $size[1] > $max_height);
	}

	/** Zero-dimension images would divide by nothing in the markup helper. */
	public function testZeroDimensionImageIsRejected(): void {
		$this->assertFalse(forum_avatar_size($this->write('zero.gif', $this->gif(0, 0))));
	}
}
