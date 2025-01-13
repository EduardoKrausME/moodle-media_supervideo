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

/**
 * Main class for plugin 'media_supervideo'
 *
 * @package   media_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class media_supervideo_plugin extends core_media_player_external {
    /**
     * List supported urls.
     *
     * @param array $urls
     * @param array $options
     * @return array
     */
    public function list_supported_urls(array $urls, array $options = array()) {
        $result = array();
        foreach ($urls as $url) {
            // If SuperVídeo support is enabled, URL is supported.

            if (strpos($url->get_path(), "_videos") === 1) {
                $result[] = $url;
            } else if (strpos($url->get_path(), ".mp4") > 1) {
                $result[] = $url;
            } else if (strpos($url->get_path(), ".mp3") > 1) {
                $result[] = $url;
            } else if (strpos($url->get_path(), ".webm") > 1) {
                $result[] = $url;
            }
        }

        return $result;
    }

    /**
     * Embed external.
     *
     * @param moodle_url $url
     * @param string $name
     * @param int $width
     * @param int $height
     * @param array $options
     * @return string
     */
    protected function embed_external(moodle_url $url, $name, $width, $height, $options) {
        global $PAGE;

        $uniqueid = uniqid();

        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_video",
            [0, 0, "media_supervideo-{$uniqueid}", $url->out(), false, true]);

        return "<div id=\"media_supervideo-{$uniqueid}\"></div>";
    }

    /**
     * Supports Text.
     *
     * @param array $usedextensions
     * @return mixed|string
     * @throws coding_exception
     */
    public function supports($usedextensions = []) {
        return get_string("support_supervideo", "media_supervideo");
    }

    /**
     * Get embeddable markers.
     *
     * @return array
     */
    public function get_embeddable_markers() {
        return [];
    }


    /**
     * Default rank
     * @return int
     */
    public function get_rank() {
        return 2002;
    }

    /**
     * Checks if player is enabled.
     *
     * @return bool True if player is enabled
     */
    public function is_enabled() {
        return true;
    }
}
