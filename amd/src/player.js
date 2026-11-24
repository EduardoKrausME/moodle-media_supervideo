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

// Inspired by gaelb80's media_supervideo PR #1.
define(['mod_supervideo/player_render', 'media_supervideo/history'], function(PlayerRender, History) {
    'use strict';

    var scripts = {};
    var players = new WeakMap();

    function script(url, ready, integrity) {
        if (ready()) {
            return Promise.resolve();
        }
        if (!scripts[url]) {
            scripts[url] = new Promise(function(resolve, reject) {
                var tag = Array.from(document.scripts).find(function(node) {
                    return node.src === url;
                });
                var created = !tag;
                if (created) {
                    tag = document.createElement('script');
                    tag.src = url;
                    tag.async = true;
                    if (integrity) {
                        tag.integrity = integrity;
                        tag.crossOrigin = 'anonymous';
                    }
                }
                var attempts = 0;
                var timer = setInterval(function() {
                    if (ready()) {
                        clearInterval(timer);
                        resolve();
                    } else if (++attempts >= 150) {
                        clearInterval(timer);
                        reject(new Error('Media API unavailable'));
                    }
                }, 100);
                tag.addEventListener('error', function() {
                    clearInterval(timer);
                    reject(new Error('Media API unavailable'));
                }, {once: true});
                if (created) {
                    document.head.appendChild(tag);
                }
            }).catch(function(error) {
                delete scripts[url];
                throw error;
            });
        }
        return scripts[url];
    }

    function init(id) {
        var root = document.getElementById(id);
        if (!root || players.has(root)) {
            return;
        }
        var state = {destroy: function() {}, disposed: false, failed: false};
        players.set(root, state);
        var history = History.create(root);
        var host = root.querySelector('.media-supervideo-host');
        var fallback = root.querySelector('.media-supervideo-fallback');
        var type = root.dataset.type;
        var value = root.dataset.value;
        var observer = new MutationObserver(function() {
            if (!root.isConnected) {
                observer.disconnect();
                state.disposed = true;
                history.stop();
                state.destroy();
                players.delete(root);
            }
        });
        observer.observe(document.documentElement, {childList: true, subtree: true});

        function fail() {
            if (state.disposed || state.failed) {
                return;
            }
            state.failed = true;
            var destroy = state.destroy;
            state.destroy = function() {};
            history.stop();
            destroy();
            host.hidden = true;
            fallback.hidden = false;
        }

        function ready() {
            if (state.disposed || state.failed) {
                return false;
            }
            host.hidden = false;
            fallback.hidden = true;
            return true;
        }

        function frame() {
            host.style.aspectRatio = root.dataset.width > 0 && root.dataset.height > 0 ?
                root.dataset.width + ' / ' + root.dataset.height : '16 / 9';
            host.style.width = '100%';
        }

        if (type === 'youtube') {
            frame();
            script('https://www.youtube.com/iframe_api', function() {
                return window.YT && window.YT.Player;
            }).then(function() {
                if (!ready()) {
                    return;
                }
                var target = document.createElement('div');
                host.appendChild(target);
                var timer = null;
                var player = new window.YT.Player(target, {
                    width: '100%', height: '100%', videoId: value,
                    host: root.dataset.providerhost || 'https://www.youtube.com',
                    playerVars: {playsinline: 1, autoplay: 0, origin: window.location.origin},
                    events: {
                        onError: fail,
                        onReady: function(event) {
                            event.target.getIframe().title = root.dataset.name;
                            history.attach({seek: function(time) { event.target.seekTo(time, true); }});
                        },
                        onStateChange: function(event) {
                            var api = event.target;
                            if (event.data === window.YT.PlayerState.PLAYING) {
                                history.play();
                                if (timer === null) {
                                    timer = setInterval(function() {
                                        history.sample(api.getCurrentTime(), api.getDuration());
                                    }, 1000);
                                }
                            } else {
                                if (timer !== null) {
                                    clearInterval(timer);
                                    timer = null;
                                }
                                if (event.data === window.YT.PlayerState.ENDED) {
                                    history.finish(api.getDuration(), api.getDuration());
                                } else {
                                    history.pause(api.getCurrentTime(), api.getDuration());
                                }
                            }
                        }
                    }
                });
                state.destroy = function() {
                    clearInterval(timer);
                    player.destroy();
                };
            }).catch(fail);
            return;
        }

        if (type === 'vimeo') {
            frame();
            script('https://player.vimeo.com/api/player.js', function() {
                return window.Vimeo && window.Vimeo.Player;
            }).then(function() {
                if (!ready()) {
                    return;
                }
                var iframe = document.createElement('iframe');
                iframe.src = 'https://player.vimeo.com/video/' + value;
                iframe.title = root.dataset.name;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
                iframe.allowFullscreen = true;
                host.appendChild(iframe);
                var player = new window.Vimeo.Player(iframe);
                state.destroy = function() { player.destroy().catch(function() {}); };
                player.ready().then(function() {
                    history.attach({seek: function(time) { return player.setCurrentTime(time); }});
                }).catch(fail);
                player.on('play', function() { history.play(); });
                player.on('pause', function(data) { history.pause(data.seconds, data.duration); });
                player.on('ended', function(data) { history.finish(data.seconds, data.duration); });
                player.on('timeupdate', function(data) { history.sample(data.seconds, data.duration); });
                player.on('error', fail);
            }).catch(fail);
            return;
        }

        var media = document.createElement(type === 'audio' ? 'audio' : 'video');
        media.controls = true;
        media.preload = 'metadata';
        media.playsInline = true;
        media.style.width = '100%';
        media.setAttribute('aria-label', root.dataset.name);
        host.appendChild(media);
        var plyr;
        var hls;
        state.destroy = function() {
            if (hls) {
                hls.destroy();
            }
            if (plyr) {
                plyr.destroy();
            }
            media.pause();
            media.removeAttribute('src');
            media.load();
        };
        media.addEventListener('error', fail);
        media.addEventListener('loadedmetadata', function() {
            history.attach({seek: function(time) { media.currentTime = time; }});
        }, {once: true});
        media.addEventListener('playing', function() { history.play(); });
        media.addEventListener('pause', function() { history.pause(media.currentTime, media.duration); });
        media.addEventListener('ended', function() { history.finish(media.currentTime, media.duration); });
        media.addEventListener('timeupdate', function() {
            if (!media.paused && !media.seeking) {
                history.sample(media.currentTime, media.duration);
            }
        });
        function enhance() {
            if (!ready()) {
                return;
            }
            try {
                plyr = new PlayerRender(media, {
                    controls: root.dataset.controls.split(',').filter(Boolean),
                    settings: ['speed', 'loop'],
                    speed: {selected: 1, options: root.dataset.speed.split(',').map(Number).filter(function(n) {
                        return Number.isFinite(n) && n > 0;
                    })},
                    storage: {enabled: false},
                    autoplay: false
                });
            } catch (error) {
                // Keep the browser's native controls if the optional skin cannot load.
                media.controls = true;
            }
        }
        if (type !== 'hls' || media.canPlayType('application/vnd.apple.mpegurl')) {
            media.src = value;
            enhance();
        } else {
            script('https://cdn.jsdelivr.net/npm/hls.js@1.7.3/dist/hls.min.js', function() {
                return !!window.Hls;
            }, 'sha256-oS5+4c1kpp3NsxQVfkXa/LpwW/sLFEC3k1yyZdN0Qj4=').then(function() {
                if (state.disposed) {
                    return;
                }
                if (!window.Hls.isSupported()) {
                    fail();
                    return;
                }
                hls = new window.Hls();
                hls.on(window.Hls.Events.ERROR, function(event, data) {
                    if (data.fatal) {
                        fail();
                    }
                });
                hls.on(window.Hls.Events.LEVEL_LOADED, function(event, data) {
                    if (data.details && data.details.live) {
                        history.stop();
                    }
                });
                hls.loadSource(value);
                hls.attachMedia(media);
                enhance();
            }).catch(fail);
        }
    }

    return {init: init};
});
