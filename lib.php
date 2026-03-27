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
 * Library hooks for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a direct navigation link for administrators.
 *
 * @param global_navigation $navigation Global navigation instance.
 * @return void
 */
function local_coursedateshiftpro_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $systemcontext = context_system::instance();
    if (!has_capability('local/coursedateshiftpro:manage', $systemcontext)) {
        return;
    }

    $url = new moodle_url('/local/coursedateshiftpro/index.php');
    $node = navigation_node::create(
        get_string('pluginname', 'local_coursedateshiftpro'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_coursedateshiftpro',
        new pix_icon('i/settings', '')
    );

    if ($navigation->find('sitehome', navigation_node::TYPE_SITE_ADMIN)) {
        $navigation->find('sitehome', navigation_node::TYPE_SITE_ADMIN)->add_node($node);
        return;
    }

    $navigation->add_node($node);
}
