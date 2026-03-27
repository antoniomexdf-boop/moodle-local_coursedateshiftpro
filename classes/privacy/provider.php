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
 * Privacy provider for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursedateshiftpro\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_coursedateshiftpro.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {
    /**
     * Describes stored metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_coursedateshiftpro_hist', [
            'courseid' => 'privacy:metadata:history:courseid',
            'userid' => 'privacy:metadata:history:userid',
            'action' => 'privacy:metadata:history:action',
            'status' => 'privacy:metadata:history:status',
            'sourceexecutionid' => 'privacy:metadata:history:sourceexecutionid',
            'oldstartdate' => 'privacy:metadata:history:oldstartdate',
            'newstartdate' => 'privacy:metadata:history:newstartdate',
            'delta' => 'privacy:metadata:history:delta',
            'filtersjson' => 'privacy:metadata:history:filtersjson',
            'selectedkeysjson' => 'privacy:metadata:history:selectedkeysjson',
            'summaryjson' => 'privacy:metadata:history:summaryjson',
            'rollbackjson' => 'privacy:metadata:history:rollbackjson',
            'timecreated' => 'privacy:metadata:history:timecreated',
            'timemodified' => 'privacy:metadata:history:timemodified',
        ], 'privacy:metadata:history');

        return $collection;
    }

    /**
     * Gets contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('local_coursedateshiftpro_hist', ['userid' => $userid])) {
            $contextlist->add_context(context_system::instance()->id);
        }

        return $contextlist;
    }

    /**
     * Exports user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $systemcontext = context_system::instance();
        if (!in_array($systemcontext->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;
        $records = $DB->get_records('local_coursedateshiftpro_hist', ['userid' => $userid], 'timecreated ASC, id ASC');
        if (empty($records)) {
            return;
        }

        $export = [];
        foreach ($records as $record) {
            $export[] = (object)[
                'id' => (int)$record->id,
                'courseid' => (int)$record->courseid,
                'action' => (string)$record->action,
                'status' => (string)$record->status,
                'sourceexecutionid' => (int)$record->sourceexecutionid,
                'oldstartdate' => transform::datetime((int)$record->oldstartdate),
                'newstartdate' => transform::datetime((int)$record->newstartdate),
                'delta' => (int)$record->delta,
                'filters' => json_decode((string)$record->filtersjson, true) ?: [],
                'selectedkeys' => json_decode((string)$record->selectedkeysjson, true) ?: [],
                'summary' => json_decode((string)$record->summaryjson, true) ?: [],
                'rollbacksnapshot' => json_decode((string)$record->rollbackjson, true) ?: [],
                'timecreated' => transform::datetime((int)$record->timecreated),
                'timemodified' => transform::datetime((int)$record->timemodified),
            ];
        }

        writer::with_context($systemcontext)->export_data(
            [get_string('pluginname', 'local_coursedateshiftpro'), get_string('privacy:historypath', 'local_coursedateshiftpro')],
            (object)['executions' => $export]
        );
    }

    /**
     * Deletes all user data within one context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_coursedateshiftpro_hist');
    }

    /**
     * Deletes all user data for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $DB->delete_records('local_coursedateshiftpro_hist', ['userid' => (int)$contextlist->get_user()->id]);
    }

    /**
     * Returns the list of users with data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $DB->get_fieldset_select(
            'local_coursedateshiftpro_hist',
            'DISTINCT userid',
            'userid IS NOT NULL AND userid > 0'
        );
        $userlist->add_users($userids);
    }

    /**
     * Deletes user data in bulk for an approved user list.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_coursedateshiftpro_hist', 'userid ' . $insql, $params);
    }
}
