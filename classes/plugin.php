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
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class media_supervideo_plugin
 */
class media_supervideo_plugin extends core_media_player_external {
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
            if (\media_supervideo\source::resolve($url) !== null) {
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

        $source = \media_supervideo\source::resolve($url);
        if ($source === null) {
            return '';
        }
        $config = get_config('supervideo');
        $elementid = uniqid('media-supervideo-');
        $title = trim(strip_tags((string)$name));
        if ($title === '') {
            $title = get_string('support_supervideo', 'media_supervideo');
        }
        $data = [
            'elementid' => $elementid,
            'name' => $title,
            'type' => $source['type'],
            'providerhost' => $source['type'] === 'youtube' && get_config('media_youtube', 'nocookie') ?
                'https://www.youtube-nocookie.com' : ($source['providerhost'] ?? ''),
            'vimeodnt' => $source['type'] === 'vimeo' && get_config('media_vimeo', 'donottrack') ? 1 : 0,
            'value' => $source['value'],
            'originalurl' => $url->out(false),
            'width' => max(0, (int)$width),
            'height' => max(0, (int)$height),
            'controls' => $config->controls ?? 'play,progress,current-time,mute,volume,settings,fullscreen',
            'speed' => $config->speed ?? '0.5,1,1.5,2',
        ];
        // Media history is available only in actual course or activity contexts.
        // Do not embed user-specific data in formatted/cached content.
        try {
            $context = $PAGE->context;
        } catch (coding_exception $e) {
            $context = null;
        }
        if ($context && in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            $data['contextid'] = $context->id;
            $data['mediahash'] = hash('sha256', $source['identity']);
            $data['ticket'] = \media_supervideo\progress::ticket($context->id, $data['mediahash']);
            $data['progresslabel'] = get_string('progresslabel', 'media_supervideo');
        }
        $PAGE->requires->js_call_amd('media_supervideo/player', 'init', [$elementid]);
        return $OUTPUT->render_from_template('media_supervideo/player', $data);
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
        return ['youtube.com/', 'youtube-nocookie.com/', 'youtu.be/', 'vimeo.com/',
            '.m3u8', '.mp4', '.mp3', '.webm', '.ogg', '.ogv', '.oga', '.m4a', '.aac',
            '.m4v', '.wav', '.flac', '/_videos'];
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
