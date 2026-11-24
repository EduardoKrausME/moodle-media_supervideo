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

namespace media_supervideo\external;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * AJAX access to personal media history.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history extends \external_api {
    /**
     * Define the common signed media parameters.
     *
     * @return array
     */
    private static function identity_parameters(): array {
        return [
            'contextid' => new \external_value(PARAM_INT, 'Actual Moodle context'),
            'mediahash' => new \external_value(PARAM_ALPHANUM, 'SHA-256 media identity'),
            'ticket' => new \external_value(PARAM_ALPHANUM, 'Signed context and media identity'),
        ];
    }

    /** @return \external_function_parameters */
    public static function load_parameters() {
        return new \external_function_parameters(self::identity_parameters());
    }

    /** @return \external_function_parameters */
    public static function save_parameters() {
        return new \external_function_parameters(self::identity_parameters() + [
            'currenttime' => new \external_value(PARAM_INT, 'Current position in seconds'),
            'duration' => new \external_value(PARAM_INT, 'Finite media duration in seconds'),
            'mark' => new \external_value(PARAM_BOOL, 'Whether the position was sampled during playback'),
        ]);
    }

    /**
     * Validate context and signed identity. User ownership comes exclusively from the session.
     *
     * @param array $params
     */
    private static function authorise(array $params): void {
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Media progress requires an authenticated user.');
        }
        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);
        if (!in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            throw new \invalid_parameter_exception('Unsupported media context.');
        }
        self::validate_context($context);
        if ($context->contextlevel === CONTEXT_MODULE) {
            $cm = get_fast_modinfo($context->get_course_context()->instanceid)->get_cm($context->instanceid);
            if (!$cm->uservisible) {
                throw new \require_login_exception('Activity is not available.');
            }
        }
        $expected = \media_supervideo\progress::ticket($context->id, $params['mediahash']);
        if (!preg_match('/^[a-f0-9]{64}$/D', $params['mediahash']) || $expected === ''
                || !hash_equals($expected, $params['ticket'])) {
            throw new \invalid_parameter_exception('Invalid media identity.');
        }
    }

    /** @return array */
    public static function load($contextid, $mediahash, $ticket) {
        global $USER;
        $params = self::validate_parameters(self::load_parameters(), compact('contextid', 'mediahash', 'ticket'));
        self::authorise($params);
        return \media_supervideo\progress::load($params['contextid'], $USER->id, $params['mediahash']);
    }

    /** @return array */
    public static function save($contextid, $mediahash, $ticket, $currenttime, $duration, $mark) {
        global $USER;
        $params = self::validate_parameters(self::save_parameters(),
            compact('contextid', 'mediahash', 'ticket', 'currenttime', 'duration', 'mark'));
        self::authorise($params);
        return \media_supervideo\progress::save($params['contextid'], $USER->id, $params['mediahash'],
            $params['currenttime'], $params['duration'], $params['mark']);
    }

    /** @return \external_single_structure */
    public static function load_returns() {
        return new \external_single_structure([
            'currenttime' => new \external_value(PARAM_INT, 'Current position'),
            'duration' => new \external_value(PARAM_INT, 'Duration'),
            'percent' => new \external_value(PARAM_INT, 'Watched percentage'),
            'map' => new \external_value(PARAM_RAW, 'JSON array of sampled segments'),
        ]);
    }

    /** @return \external_single_structure */
    public static function save_returns() {
        return self::load_returns();
    }
}
