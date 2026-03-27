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
 * External service declarations for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursedateshiftpro_get_preview' => [
        'classname' => 'local_coursedateshiftpro\\external\\get_preview',
        'description' => 'Builds the interactive course date shift preview for AJAX clients.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/coursedateshiftpro:manage',
    ],
];
