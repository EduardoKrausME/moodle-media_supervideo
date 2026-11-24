# Moodle Plugin: Media SuperVídeo

This is a filter for Moodle that automatically transforms video links hosted on the platform into beautiful players, just like [**SuperVídeo**](https://moodle.org/plugins/mod_supervideo).

## Requirements

This filter requires the [**SuperVídeo**](https://moodle.org/plugins/mod_supervideo) module for compatible playback.

## Configuration

The **Media SuperVídeo** filter offers configuration options through Moodle administration:

- Navigate to: **Site Administration** > **Plugins** > **Filters** > **Media SuperVídeo**.
- Adjust the settings according to your Moodle environment's needs.

## Features

- **Automatic Player:** Automatically converts video links into integrated players.
- **Full Compatibility:** Works seamlessly with the **SuperVídeo** module to manage videos directly within Moodle.

## Personal viewing history

Authenticated users can resume finite-duration media in course and activity contexts.
The history is stored in a dedicated table, keyed by context, user and SHA-256 media identity.
A signed media identity is rendered without any personal data. AJAX requests validate
the current user and context. Guest users and unsupported contexts remain playback-only.
No academic grade or completion is changed. The same media identity in the same context
shares a history; different contexts do not. Live streams without a finite duration do not
have a percentage. History is personal, not proof of attendance or assessment, and is not
included in activity backup/restore. Privacy API supports export and deletion.
Media providers receive normal browser requests during playback setup.

## Supported media

YouTube (watch, live, shorts, embed), Vimeo (including unlisted video hashes), HLS/M3U8,
MP4, WebM, OGG and common audio formats. Each embedded player has independent playback
state and never invokes the SuperVideo activity grade service.

Provider URLs must use recognised hosts. HLS requires native browser support or hls.js;
cross-origin media must allow playback. The original link remains available if a provider fails.

Thanks to [gaelb80](https://github.com/gaelb80) for the proposal in PR #1.

## Support

For questions, issues, or support requests, contact:

- [SuperVídeo module repository](https://moodle.org/plugins/mod_supervideo)
