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
 * @copyright 2024 Eduardo kraus (@link https://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_supervideo\analytics\supervideo_view;

/**
 * Class media_supervideo_plugin
 */
class media_supervideo_plugin extends core_media_player_external {

    const YOUTUBE_PATTERN = '/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i';
    const VIMEO_PATTERN = "/vimeo.com\/(\d+)(\/(\w+))?/";
    const M3U_PATTERN = '/^https?.*\.(m3u8)/i';

    /**
     * List supported urls.
     *
     * @param array $urls
     * @param array $options
     * @return array
     */
    public function list_supported_urls(array $urls, array $options = []) {
        $result = [];
        foreach ($urls as $url) {
            $finalUrl = $url->out();
            // If SuperVídeo support is enabled, URL is supported.
            if (strpos($url->get_path(), "_videos") === 1) {
                $result[] = $url;
            } else if (strpos($url->get_path(), ".mp4") > 1) {
                $result[] = $url;
            } else if (strpos($url->get_path(), ".mp3") > 1) {
                $result[] = $url;
            } else if (strpos($url->get_path(), ".webm") > 1) {
                $result[] = $url;
            } else if (preg_match(self::YOUTUBE_PATTERN, $finalUrl)) {
                $result[] = $url;
            } else if (preg_match(self::VIMEO_PATTERN, $finalUrl)) {
                $result[] = $url;
            } else if (preg_match(self::M3U_PATTERN, $finalUrl)) {
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
        global $PAGE, $OUTPUT;

        $config = get_config("supervideo");

        $uniqueid = uniqid();
        $finalUrl = $url->out();
        $elementId = "media_supervideo-{$uniqueid}";
        // Generate a fake course module id
        $cmId = 9990000000000 + crc32($finalUrl);
        $supervideoview = supervideo_view::create($cmId);

        $text = $OUTPUT->heading(
            get_string("seu_mapa_view", "mod_supervideo") . " <span></span>",
            3,
            "main-view",
            "seu-mapa-view"
        );
        $mapa = $OUTPUT->render_from_template("mod_supervideo/mapa", [
            "style" => "",
            "data-mapa" => base64_encode($supervideoview->mapa),
            "text" => $text,
        ]);

        if (preg_match(self::YOUTUBE_PATTERN, $finalUrl, $output)) {
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "youtube", [
                $supervideoview->id,
                $supervideoview->currenttime,
                $elementId,
                $output[3],
                '',
                1,
                0,
            ]);

            $link = "<script src='https://www.youtube.com/iframe_api'></script>";
            return $link . $OUTPUT->render_from_template("mod_supervideo/embed_div", ["elementid" => $elementId]) . $mapa;

        } else if (preg_match(self::VIMEO_PATTERN, $finalUrl, $output)) {
            $parametersvimeo = implode("&amp;", [
                "pip=1",
                "title=0",
                "byline=0",
                "title=1",
                "autoplay=0",
                "controls=1",
            ]);

            if (preg_match("/vimeo.com\/(\d+)(\/(\w+))?/", $finalUrl, $output)) {
                if (isset($output[3])) {
                    $finalUrl = "{$output[1]}?h={$output[3]}&pip{$parametersvimeo}";
                } else {
                    $finalUrl = "{$output[1]}?pip{$parametersvimeo}";
                }
            }

            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "vimeo", [
                $supervideoview->id,
                $supervideoview->currenttime,
                $finalUrl,
                $elementId,
            ]);
            return $OUTPUT->render_from_template("mod_supervideo/embed_vimeo", [
                "elementid" => $elementId,
                "vimeo_id" => $url,
                "parametersvimeo" => $parametersvimeo,
            ]) . $mapa;

        } else {
            $mustachedata = [
                "elementid" => $elementId,
                "videourl" => $finalUrl,
                "autoplay" => 0,
                "showcontrols" => 1,
                "controls" => $config->controls,
                "speed" => $config->speed,
                "hls" => preg_match("/^https?.*\.(m3u8)/i", $finalUrl, $output),
                "has_audio" => preg_match("/^https?.*\.(mp3|aac|m4a)/i", $finalUrl, $output),
            ];
            $PAGE->requires->js_call_amd(
                "mod_supervideo/player_create",
                $mustachedata["has_audio"] ? "resource_audio" : "resource_video",
                [
                    $supervideoview->id,
                    $supervideoview->currenttime,
                    $elementId,
                    $mustachedata["hls"]
                ]
            );

            return $OUTPUT->render_from_template("mod_supervideo/embed_div", $mustachedata) . $mapa;

        }
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
