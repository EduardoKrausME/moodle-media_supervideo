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

namespace media_supervideo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API for personal playback history.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {
    /** @return collection */
    public static function get_metadata(collection $collection): collection {
        $fields = [];
        foreach (['contextid', 'userid', 'mediahash', 'currenttime', 'duration', 'percent',
                'map', 'lastsample', 'timecreated', 'timemodified'] as $field) {
            $fields[$field] = 'privacy:metadata:history:' . $field;
        }
        $collection->add_database_table('media_supervideo_view', $fields, 'privacy:metadata:history');
        foreach (['youtube', 'vimeo', 'jsdelivr', 'mediahost'] as $location) {
            $collection->add_external_location_link($location,
                ['connection' => 'privacy:metadata:connection'], 'privacy:metadata:' . $location);
        }
        return $collection;
    }

    /** @return contextlist */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql('SELECT DISTINCT contextid FROM {media_supervideo_view} WHERE userid = :userid',
            ['userid' => $userid]);
        return $list;
    }

    /** @param approved_contextlist $contextlist */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $records = $DB->get_records('media_supervideo_view',
                ['contextid' => $context->id, 'userid' => $contextlist->get_user()->id]);
            foreach ($records as $record) {
                unset($record->id, $record->contextid, $record->userid);
                $record->timecreated = \core_privacy\local\request\transform::datetime($record->timecreated);
                $record->timemodified = \core_privacy\local\request\transform::datetime($record->timemodified);
                $record->lastsample = \core_privacy\local\request\transform::datetime($record->lastsample);
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'media_supervideo'), $record->mediahash], $record);
            }
        }
    }

    /** @param \context $context */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records('media_supervideo_view', ['contextid' => $context->id]);
    }

    /** @param approved_contextlist $contextlist */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('media_supervideo_view',
                ['contextid' => $context->id, 'userid' => $contextlist->get_user()->id]);
        }
    }

    /** @param userlist $userlist */
    public static function get_users_in_context(userlist $userlist): void {
        $userlist->add_from_sql('userid',
            'SELECT userid FROM {media_supervideo_view} WHERE contextid = :contextid',
            ['contextid' => $userlist->get_context()->id]);
    }

    /** @param approved_userlist $userlist */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $ids = $userlist->get_userids();
        if (!$ids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['contextid'] = $userlist->get_context()->id;
        $DB->delete_records_select('media_supervideo_view', "contextid = :contextid AND userid $insql", $params);
    }
}
