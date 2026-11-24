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
 * Personal progress for a media identity within an actual Moodle context.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /**
     * Sign the media identity. No user identifier is rendered into cached HTML.
     *
     * @param int $contextid
     * @param string $mediahash
     * @return string
     */
    public static function ticket(int $contextid, string $mediahash): string {
        $secret = get_config('media_supervideo', 'progresssecret');
        if (!$secret) {
            return '';
        }
        return hash_hmac('sha256', $contextid . ':' . $mediahash, $secret);
    }

    /**
     * Return a user's existing playback history, without creating a record.
     *
     * @param int $contextid
     * @param int $userid
     * @param string $mediahash
     * @return array
     */
    public static function load(int $contextid, int $userid, string $mediahash): array {
        global $DB;
        $record = $DB->get_record('media_supervideo_view', [
            'contextid' => $contextid, 'userid' => $userid, 'mediahash' => $mediahash,
        ]);
        return self::result($record);
    }

    /**
     * Save a position and, at most once per server second, a sampled segment.
     * This is personal history, not evidence for academic credit.
     *
     * @param int $contextid
     * @param int $userid
     * @param string $mediahash
     * @param int $currenttime
     * @param int $duration
     * @param bool $mark
     * @return array
     */
    public static function save(int $contextid, int $userid, string $mediahash,
            int $currenttime, int $duration, bool $mark): array {
        global $DB;
        if ($duration < 1 || $duration > 604800 || $currenttime < 0 || $currenttime > $duration) {
            throw new \invalid_parameter_exception('Invalid media position or duration.');
        }
        $key = ['contextid' => $contextid, 'userid' => $userid, 'mediahash' => $mediahash];
        $factory = \core\lock\lock_config::get_lock_factory('media_supervideo');
        $lock = $factory->get_lock(hash('sha256', implode(':', $key)), 5);
        if (!$lock) {
            throw new \moodle_exception('progressbusy', 'media_supervideo');
        }
        try {
            $now = time();
            $record = $DB->get_record('media_supervideo_view', $key);
            if (!$record && (!$mark || $currenttime === 0)) {
                return self::result(false);
            }
            if (!$record) {
                $record = (object)($key + [
                    'currenttime' => 0, 'duration' => $duration, 'percent' => 0,
                    'map' => '[]', 'lastsample' => 0, 'timecreated' => $now, 'timemodified' => $now,
                ]);
            }
            $length = min(100, $duration);
            $segments = json_decode($record->map, true);
            if (!is_array($segments) || min(100, (int)$record->duration) !== $length) {
                $segments = [];
            }
            $segments = array_values(array_unique(array_filter($segments, static function($segment) use ($length) {
                return is_int($segment) && $segment >= 1 && $segment <= $length;
            })));
            if ($mark && $currenttime > 0 && $now > (int)$record->lastsample) {
                $segment = max(1, min($length, (int)ceil($currenttime / $duration * $length)));
                if (!in_array($segment, $segments, true)) {
                    $segments[] = $segment;
                }
                $record->lastsample = $now;
            }
            sort($segments, SORT_NUMERIC);
            $record->currenttime = $currenttime;
            $record->duration = $duration;
            $record->percent = (int)floor(count($segments) / $length * 100);
            $record->map = json_encode($segments);
            $record->timemodified = $now;
            if (isset($record->id)) {
                $DB->update_record('media_supervideo_view', $record);
            } else {
                $record->id = $DB->insert_record('media_supervideo_view', $record);
            }
            return self::result($record);
        } finally {
            $lock->release();
        }
    }

    /**
     * Return only the fields needed for playback.
     *
     * @param object|false $record
     * @return array
     */
    private static function result($record): array {
        return [
            'currenttime' => (int)($record->currenttime ?? 0),
            'duration' => (int)($record->duration ?? 0),
            'percent' => (int)($record->percent ?? 0),
            'map' => $record->map ?? '[]',
        ];
    }
}
