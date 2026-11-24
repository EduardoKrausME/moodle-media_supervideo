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
 * Remove orphaned playback history when its owner or context is deleted.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Delete playback history when its context is removed.
     *
     * @param \core\event\base $event
     */
    public static function context_deleted(\core\event\base $event): void {
        global $DB;
        $DB->delete_records('media_supervideo_view', ['contextid' => $event->contextid]);
    }

    /**
     * Delete playback history when its user is removed.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('media_supervideo_view', ['userid' => $event->objectid]);
    }
}
