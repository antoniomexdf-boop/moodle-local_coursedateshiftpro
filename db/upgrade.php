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
 * Upgrade steps for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_coursedateshiftpro.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_coursedateshiftpro_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026032200) {
        $table = new xmldb_table('local_coursedateshiftpro_hist');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'apply');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'completed');
        $table->add_field('sourceexecutionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('oldstartdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('newstartdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('delta', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('filtersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('selectedkeysjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('summaryjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('rollbackjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('sourceexecutionid_ix', XMLDB_INDEX_NOTUNIQUE, ['sourceexecutionid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032200, 'local', 'coursedateshiftpro');
    }

    return true;
}
