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
 * Regression tests for the personal playback history upgrade.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_media_supervideo_upgrade
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_media_supervideo_upgrade')]
final class upgrade_test extends \advanced_testcase {
    /**
     * Verify an upgrade from before playback history was introduced, including a retry.
     *
     * @covers ::xmldb_media_supervideo_upgrade
     */
    public function test_upgrade_creates_history_table(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();

        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        $dbman = $DB->get_manager();
        $tablename = 'media_supervideo_view';
        $table = new \xmldb_table($tablename);
        $oldversion = 2026112400;
        $targetversion = 2026112401;

        // PHPUnit installs the latest schema first. Recreate the pre-upgrade state.
        $dbman->drop_table($table);
        try {
            set_config('version', $oldversion, 'media_supervideo');
            unset_config('progresssecret', 'media_supervideo');

            $this->assertTrue(\xmldb_media_supervideo_upgrade($oldversion));
            $this->assertTrue($dbman->table_exists($table));

            $columns = $DB->get_columns($tablename);
            $this->assertCount(11, $columns);
            $this->assertArrayHasKey('id', $columns);
            $this->assertSame('R', $columns['id']->meta_type);
            $this->assertTrue($columns['id']->primary_key);
            $this->assertTrue($columns['id']->auto_increment);
            $integerfields = [
                'contextid', 'userid', 'currenttime', 'duration', 'percent',
                'lastsample', 'timecreated', 'timemodified',
            ];
            foreach ($integerfields as $name) {
                $this->assertArrayHasKey($name, $columns);
                $this->assertSame('I', $columns[$name]->meta_type);
            }
            $this->assertArrayHasKey('mediahash', $columns);
            $this->assertArrayHasKey('map', $columns);
            $this->assertSame('C', $columns['mediahash']->meta_type);
            $this->assertSame('X', $columns['map']->meta_type);

            $index = new \xmldb_index('context_user_media', XMLDB_INDEX_UNIQUE, ['contextid', 'userid', 'mediahash']);
            $this->assertTrue($dbman->index_exists($table, $index));
            $index = new \xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $this->assertTrue($dbman->index_exists($table, $index));

            $secret = get_config('media_supervideo', 'progresssecret');
            $this->assertIsString($secret);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secret);
            $this->assertEquals($targetversion, get_config('media_supervideo', 'version'));

            $id = $DB->insert_record($tablename, (object) [
                'contextid' => \context_system::instance()->id,
                'userid' => 2,
                'mediahash' => hash('sha256', 'video:upgrade-test'),
                'map' => '[]',
            ]);

            // Simulate an interrupted upgrade after table and secret creation.
            set_config('version', $oldversion, 'media_supervideo');
            $this->assertTrue(\xmldb_media_supervideo_upgrade($oldversion));
            $this->assertTrue($DB->record_exists($tablename, ['id' => $id]));
            $this->assertSame($secret, get_config('media_supervideo', 'progresssecret'));
            $this->assertEquals($targetversion, get_config('media_supervideo', 'version'));

            // Calling with the current version must be a no-op.
            $this->assertTrue(\xmldb_media_supervideo_upgrade($targetversion));
            $this->assertTrue($DB->record_exists($tablename, ['id' => $id]));
        } finally {
            // Restore the installed schema even when the migration fails.
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/../db/install.xml', $tablename);
        }
    }
}
