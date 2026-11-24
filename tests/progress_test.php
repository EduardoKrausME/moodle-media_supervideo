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
 * Personal progress tests.
 *
 * @package media_supervideo
 * @covers \media_supervideo\progress
 */
final class progress_test extends \advanced_testcase {
    public function test_load_does_not_create_records(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        $hash = hash('sha256', 'youtube:abcdefghijk');
        $this->assertSame(0, progress::load($context->id, $user->id, $hash)['currenttime']);
        $this->assertEquals(0, $DB->count_records('media_supervideo_view'));
        progress::save($context->id, $user->id, $hash, 0, 100, true);
        $this->assertEquals(0, $DB->count_records('media_supervideo_view'));
    }

    public function test_media_user_and_context_isolation(): void {
        $this->resetAfterTest();
        $first = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $second = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $hash = hash('sha256', 'youtube:abcdefghijk');
        $hash2 = hash('sha256', 'vimeo:123456');
        $result = progress::save($first->id, $user->id, $hash, 50, 100, true);
        $this->assertSame(1, $result['percent']);
        $this->assertSame('[50]', $result['map']);
        $this->assertSame(50, progress::load($first->id, $user->id, $hash)['currenttime']);
        $this->assertSame(0, progress::load($second->id, $user->id, $hash)['currenttime']);
        $this->assertSame(0, progress::load($first->id, $other->id, $hash)['currenttime']);
        $this->assertSame(0, progress::load($first->id, $user->id, $hash2)['currenttime']);
        $this->assertNotSame(progress::ticket($first->id, $hash), progress::ticket($second->id, $hash));
        $this->assertNotSame(progress::ticket($first->id, $hash), progress::ticket($first->id, $hash2));
    }

    public function test_resume_does_not_mark_skipped_segments(): void {
        $this->resetAfterTest();
        $context = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $user = $this->getDataGenerator()->create_user();
        $hash = hash('sha256', 'video:test');
        progress::save($context->id, $user->id, $hash, 10, 100, true);
        $result = progress::save($context->id, $user->id, $hash, 90, 100, false);
        $this->assertSame(90, $result['currenttime']);
        $this->assertSame('[10]', $result['map']);
        $this->assertSame(1, $result['percent']);
    }
}
