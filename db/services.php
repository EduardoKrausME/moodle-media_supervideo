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

defined('MOODLE_INTERNAL') || die;

$functions = [
    'media_supervideo_history_load' => [
        'classname' => 'media_supervideo\\external\\history',
        'methodname' => 'load',
        'description' => 'Read personal media playback history.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'media_supervideo_history_save' => [
        'classname' => 'media_supervideo\\external\\history',
        'methodname' => 'save',
        'description' => 'Save personal media playback history.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
