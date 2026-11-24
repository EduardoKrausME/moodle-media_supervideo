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

/**
 * AJAX access to personal media history.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history extends \core_external\external_api {
    /**
     * Define the common signed media parameters.
     *
     * @return array
     */
    private static function identity_parameters(): array {
        return [
            'contextid' => new \core_external\external_value(PARAM_INT, 'Actual Moodle context'),
            'mediahash' => new \core_external\external_value(PARAM_ALPHANUM, 'SHA-256 media identity'),
            'ticket' => new \core_external\external_value(PARAM_ALPHANUM, 'Signed context and media identity'),
        ];
    }

    /**
     * Define parameters for reading playback history.
     *
     * @return \core_external\external_function_parameters
     */
    public static function load_parameters() {
        return new \core_external\external_function_parameters(self::identity_parameters());
    }

    /**
     * Define parameters for saving playback history.
     *
     * @return \core_external\external_function_parameters
     */
    public static function save_parameters() {
        return new \core_external\external_function_parameters(self::identity_parameters() + [
            'currenttime' => new \core_external\external_value(PARAM_INT, 'Current position in seconds'),
            'duration' => new \core_external\external_value(PARAM_INT, 'Finite media duration in seconds'),
            'mark' => new \core_external\external_value(PARAM_BOOL, 'Whether the position was sampled during playback'),
        ]);
    }

    /**
     * Resolve a supported context for an authenticated user.
     *
     * @param array $params
     * @return \context
     */
    private static function request_context(array $params): \context {
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Media progress requires an authenticated user.');
        }
        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);
        if (!in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            throw new \invalid_parameter_exception('Unsupported media context.');
        }
        return $context;
    }

    /**
     * Authorise the signed media identity after validating the context.
     *
     * @param \context $context
     * @param array $params
     */
    private static function authorise(\context $context, array $params): void {
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

    /**
     * Read playback history for the authenticated user.
     *
     * @param int $contextid
     * @param string $mediahash
     * @param string $ticket
     * @return array
     */
    public static function load($contextid, $mediahash, $ticket) {
        global $USER;
        $params = self::validate_parameters(self::load_parameters(), compact('contextid', 'mediahash', 'ticket'));
        $context = self::request_context($params);
        self::validate_context($context);
        self::authorise($context, $params);
        return \media_supervideo\progress::load($params['contextid'], $USER->id, $params['mediahash']);
    }

    /**
     * Save playback history for the authenticated user.
     *
     * @param int $contextid
     * @param string $mediahash
     * @param string $ticket
     * @param int $currenttime
     * @param int $duration
     * @param bool $mark
     * @return array
     */
    public static function save($contextid, $mediahash, $ticket, $currenttime, $duration, $mark) {
        global $USER;
        $params = self::validate_parameters(self::save_parameters(),
            compact('contextid', 'mediahash', 'ticket', 'currenttime', 'duration', 'mark'));
        $context = self::request_context($params);
        self::validate_context($context);
        self::authorise($context, $params);
        return \media_supervideo\progress::save($params['contextid'], $USER->id, $params['mediahash'],
            $params['currenttime'], $params['duration'], $params['mark']);
    }

    /**
     * Describe the playback history response.
     *
     * @return \core_external\external_single_structure
     */
    public static function load_returns() {
        return new \core_external\external_single_structure([
            'currenttime' => new \core_external\external_value(PARAM_INT, 'Current position'),
            'duration' => new \core_external\external_value(PARAM_INT, 'Duration'),
            'percent' => new \core_external\external_value(PARAM_INT, 'Watched percentage'),
            'map' => new \core_external\external_value(PARAM_RAW, 'JSON array of sampled segments'),
        ]);
    }

    /**
     * Describe the saved playback history response.
     *
     * @return \core_external\external_single_structure
     */
    public static function save_returns() {
        return self::load_returns();
    }
}
