<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace media_supervideo;

/**
 * Media provider resolution tests.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \media_supervideo\source
 */
#[\PHPUnit\Framework\Attributes\CoversClass(source::class)]
final class source_test extends \advanced_testcase {
    /**
     * Verify valid provider and file URLs.
     */
    public function test_supported_urls(): void {
        $cases = [
            ['https://www.youtube.com/watch?feature=share&v=abcdefghijk', 'youtube', 'abcdefghijk'],
            ['https://youtu.be/abcdefghijk?t=10', 'youtube', 'abcdefghijk'],
            ['https://www.youtube.com/shorts/abcdefghijk', 'youtube', 'abcdefghijk'],
            ['https://player.vimeo.com/video/123456?h=abcd123', 'vimeo', '123456?h=abcd123'],
            ['https://vimeo.com/123456/abcd123', 'vimeo', '123456?h=abcd123'],
            ['https://example.org/video.M3U8?token=abc', 'hls', 'https://example.org/video.M3U8?token=abc'],
            ['https://example.org/audio.m4a', 'audio', 'https://example.org/audio.m4a'],
            ['https://example.org/video.mp4', 'video', 'https://example.org/video.mp4'],
        ];
        foreach ($cases as [$url, $type, $value]) {
            $source = source::resolve(new \moodle_url($url));
            $this->assertSame($type, $source['type']);
            $this->assertSame($value, $source['value']);
        }
    }

    /**
     * Verify that the rendered fallback preserves query parameters and escapes HTML.
     */
    public function test_fallback_url_is_escaped_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_context(\context_system::instance());
        $url = new \moodle_url('https://example.org/video.mp4', ['a' => '1', 'b' => '"<tag>']);
        $player = new \media_supervideo_plugin();
        $html = $player->embed([$url], 'Video', 0, 0, []);
        $this->assertStringContainsString('href="' . htmlspecialchars($url->out(false),
            ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * Verify that unrelated hosts and invalid paths are not accepted.
     */
    public function test_rejects_unrecognised_urls(): void {
        foreach ([
            'https://notvimeo.com/123456',
            'https://youtube.com.example.org/watch?v=abcdefghijk',
            'https://example.org/download?name=video.m3u8',
            'https://example.org/video.mp4.html',
            'https://www.youtube.com/watch?v=short',
            'https://player.vimeo.com/video/123?h[]=invalid',
        ] as $url) {
            $this->assertNull(source::resolve(new \moodle_url($url)));
        }
    }
}
