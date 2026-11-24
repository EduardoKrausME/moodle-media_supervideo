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
 * Recognise supported media without treating arbitrary hosts as providers.
 *
 * @package media_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source {
    /**
     * Resolve a media URL to a provider and its playback identity.
     *
     * @param \moodle_url $url
     * @return array|null
     */
    public static function resolve(\moodle_url $url): ?array {
        $raw = $url->out(false);
        $parts = parse_url($raw);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $id = null;
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com',
                'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be'], true)) {
            if ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                $id = $matches[1];
            } else if ($path === '/watch' && is_string($query['v'] ?? null)
                    && preg_match('~^[A-Za-z0-9_-]{11}$~D', $query['v'])) {
                $id = $query['v'];
            } else if (preg_match('~^/(?:embed|live|shorts)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                $id = $matches[1];
            }
            return $id === null ? null : [
                'type' => 'youtube', 'value' => $id, 'identity' => 'youtube:' . $id,
            ];
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            if (!preg_match('~^/(?:video/)?([0-9]+)(?:/([A-Za-z0-9]+))?/?$~D', $path, $matches)) {
                return null;
            }
            $hash = $matches[2] ?? ($query['h'] ?? '');
            if (!is_string($hash) || ($hash !== '' && !preg_match('~^[A-Za-z0-9]+$~D', $hash))) {
                return null;
            }
            $value = $matches[1] . ($hash !== '' ? '?h=' . $hash : '');
            return ['type' => 'vimeo', 'value' => $value, 'identity' => 'vimeo:' . $matches[1]];
        }

        $extension = strtolower(pathinfo(rawurldecode($path), PATHINFO_EXTENSION));
        if ($extension === 'm3u8') {
            $type = 'hls';
        } else if (in_array($extension, ['mp3', 'aac', 'm4a', 'oga', 'wav', 'flac'], true)) {
            $type = 'audio';
        } else if (in_array($extension, ['mp4', 'webm', 'ogg', 'ogv', 'm4v'], true)
                || strpos($path, '/_videos') === 0) {
            $type = 'video';
        } else {
            return null;
        }
        // Preserve query parameters: they may identify different media on the same endpoint.
        // Fragments do not identify a different media resource.
        return ['type' => $type, 'value' => $raw, 'identity' => $type . ':' . explode('#', $raw, 2)[0]];
    }
}
