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

define(['core/ajax'], function(Ajax) {
    'use strict';

    function create(root) {
        var identity = {contextid: Number(root.dataset.contextid),
            mediahash: root.dataset.mediahash, ticket: root.dataset.ticket};
        var panel = root.querySelector('.media-supervideo-history');
        var adapter;
        var stopped = false;
        var disabled = !identity.ticket || !panel;
        var playing = false;
        var everPlayed = false;
        var busy = false;
        var lastRequest = 0;
        var lastTime = -1;
        var pending = null;
        var initial;
        var resumed = false;

        function request(method, params) {
            return Promise.resolve(Ajax.call([{
                methodname: 'media_supervideo_history_' + method,
                args: Object.assign({}, identity, params || {})
            }])[0]);
        }

        function render(result) {
            if (disabled || stopped || !result.duration) {
                return;
            }
            var progress = panel.querySelector('progress');
            progress.value = result.percent;
            panel.querySelector('.media-supervideo-percent').textContent = result.percent + '%';
            var map = panel.querySelector('.media-supervideo-map');
            var segments;
            try {
                segments = new Set(JSON.parse(result.map));
            } catch (error) {
                segments = new Set();
            }
            var length = Math.min(100, result.duration);
            if (map.children.length !== length) {
                map.textContent = '';
                for (var i = 0; i < length; i++) {
                    map.appendChild(document.createElement('span'));
                }
            }
            Array.from(map.children).forEach(function(segment, index) {
                segment.classList.toggle('watched', segments.has(index + 1));
            });
            panel.hidden = false;
        }

        function resume() {
            if (disabled || stopped || resumed || !adapter || !initial) {
                return;
            }
            resumed = true;
            if (!everPlayed && initial.currenttime > 0 && initial.currenttime < initial.duration - 3) {
                Promise.resolve(adapter.seek(initial.currenttime)).catch(function() {});
            }
        }

        if (!disabled) {
            request('load').then(function(result) {
                initial = result;
                render(result);
                resume();
            }).catch(function() {
                // Authentication or context access can be unavailable on public pages.
                disabled = true;
            });
        }

        function flush() {
            if (stopped || disabled || busy || !pending) {
                return;
            }
            var payload = pending;
            pending = null;
            busy = true;
            lastRequest = Date.now();
            request('save', payload).then(render).catch(function() {
                // Playback must not depend on the availability of history.
            }).finally(function() {
                busy = false;
                flush();
            });
        }

        function sample(time, duration, mark, finalSample) {
            if (disabled || stopped || !everPlayed || (mark && !playing)
                    || !Number.isFinite(time) || !Number.isFinite(duration)) {
                return;
            }
            duration = Math.floor(duration);
            time = Math.max(0, Math.min(duration, Math.floor(time)));
            if (duration < 1 || duration > 604800 || time === lastTime) {
                return;
            }
            if (mark && !finalSample && Date.now() - lastRequest < 1000) {
                return;
            }
            lastTime = time;
            pending = {currenttime: time, duration: duration, mark: mark};
            flush();
        }

        return {
            attach: function(value) {
                adapter = value;
                resume();
            },
            play: function() {
                playing = true;
                everPlayed = true;
            },
            pause: function(time, duration) {
                playing = false;
                sample(time, duration, false);
            },
            sample: function(time, duration) {
                sample(time, duration, true);
            },
            finish: function(time, duration) {
                sample(time, duration, true, true);
                playing = false;
            },
            stop: function() {
                stopped = true;
                pending = null;
            }
        };
    }

    return {create: create};
});
