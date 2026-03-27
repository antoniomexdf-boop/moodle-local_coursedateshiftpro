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
 * External preview service for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursedateshiftpro\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use local_coursedateshiftpro\local\date_shifter;
use local_coursedateshiftpro\local\preview_renderer;

/**
 * External function to build an AJAX preview safely through Moodle services.
 */
class get_preview extends external_api {
    /**
     * Describes parameters for the preview service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'newstartts' => new external_value(PARAM_INT, 'Computed new course start timestamp', VALUE_DEFAULT, 0),
            'newstartyear' => new external_value(PARAM_INT, 'New start year', VALUE_DEFAULT, 0),
            'newstartmonth' => new external_value(PARAM_INT, 'New start month', VALUE_DEFAULT, 0),
            'newstartday' => new external_value(PARAM_INT, 'New start day', VALUE_DEFAULT, 0),
            'newstarthour' => new external_value(PARAM_INT, 'New start hour', VALUE_DEFAULT, 0),
            'newstartminute' => new external_value(PARAM_INT, 'New start minute', VALUE_DEFAULT, 0),
            'filters' => new external_single_structure([
                'courseenddate' => new external_value(PARAM_BOOL, 'Include course end date', VALUE_DEFAULT, 1),
                'activities' => new external_value(PARAM_BOOL, 'Include activities', VALUE_DEFAULT, 1),
                'sections' => new external_value(PARAM_BOOL, 'Include section restrictions', VALUE_DEFAULT, 1),
                'restrictions' => new external_value(PARAM_BOOL, 'Include activity restrictions', VALUE_DEFAULT, 1),
                'overrides' => new external_value(PARAM_BOOL, 'Include overrides', VALUE_DEFAULT, 1),
                'completionexpected' => new external_value(PARAM_BOOL, 'Include completion expected dates', VALUE_DEFAULT, 1),
            ]),
            'useautoschedule' => new external_value(PARAM_BOOL, 'Apply recommended re-calendarization', VALUE_DEFAULT, 0),
            'selectedkeys' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Selected item key'),
                'Selected preview rows',
                VALUE_DEFAULT,
                []
            ),
            'manualdates' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Preview item key'),
                    'value' => new external_value(PARAM_RAW_TRIMMED, 'datetime-local value', VALUE_DEFAULT, ''),
                ]),
                'Manual recommended date overrides',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Builds and renders the preview HTML.
     *
     * @param int $courseid
     * @param int $newstartts
     * @param int $newstartyear
     * @param int $newstartmonth
     * @param int $newstartday
     * @param int $newstarthour
     * @param int $newstartminute
     * @param array $filters
     * @param bool $useautoschedule
     * @param array $selectedkeys
     * @param array $manualdates
     * @return array
     */
    public static function execute(
        int $courseid,
        int $newstartts = 0,
        int $newstartyear = 0,
        int $newstartmonth = 0,
        int $newstartday = 0,
        int $newstarthour = 0,
        int $newstartminute = 0,
        array $filters = [],
        bool $useautoschedule = false,
        array $selectedkeys = [],
        array $manualdates = []
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'newstartts' => $newstartts,
            'newstartyear' => $newstartyear,
            'newstartmonth' => $newstartmonth,
            'newstartday' => $newstartday,
            'newstarthour' => $newstarthour,
            'newstartminute' => $newstartminute,
            'filters' => $filters,
            'useautoschedule' => $useautoschedule,
            'selectedkeys' => $selectedkeys,
            'manualdates' => $manualdates,
        ]);

        $systemcontext = \context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/coursedateshiftpro:manage', $systemcontext);

        $course = date_shifter::get_course($params['courseid']);
        if (!$course) {
            throw new \moodle_exception('errorcoursemissing', 'local_coursedateshiftpro');
        }

        $newstartdate = (int)$params['newstartts'];
        if ($newstartdate <= 0) {
            $newstartdate = make_timestamp(
                (int)$params['newstartyear'],
                (int)$params['newstartmonth'],
                (int)$params['newstartday'],
                (int)$params['newstarthour'],
                (int)$params['newstartminute']
            );
        }

        if ($newstartdate <= 0) {
            throw new \moodle_exception('errornewstartmissing', 'local_coursedateshiftpro');
        }

        $filters = date_shifter::normalise_filters($params['filters'], false);
        $parsedmanualdates = self::parse_manual_dates($params['manualdates']);
        $preview = date_shifter::build_preview(
            (int)$course->id,
            $newstartdate,
            $filters,
            !empty($params['useautoschedule']),
            $parsedmanualdates
        );

        $autoscheduleenabled = !empty($params['useautoschedule']);

        $selectedkeys = $params['selectedkeys'];
        if (empty($selectedkeys)) {
            $selectedkeys = array_keys($preview['items']);
        }

        $html = preview_renderer::render_preview($preview, $filters, $selectedkeys, false);

        return [
            'html' => $html,
            'autoscheduleenabled' => $autoscheduleenabled ? 1 : 0,
            'itemcount' => count($preview['items']),
        ];
    }

    /**
     * Describes the preview response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered preview HTML'),
            'autoscheduleenabled' => new external_value(PARAM_BOOL, 'Whether autoschedule ended up enabled'),
            'itemcount' => new external_value(PARAM_INT, 'Number of preview items returned'),
        ]);
    }

    /**
     * Parses manual date overrides from datetime-local values.
     *
     * @param array $manualdates
     * @return array
     */
    protected static function parse_manual_dates(array $manualdates): array {
        $parsed = [];
        foreach ($manualdates as $item) {
            $key = (string)($item['key'] ?? '');
            $value = trim((string)($item['value'] ?? ''));
            if (!preg_match('/^k_[a-f0-9]{32}$/', $key) || $value === '') {
                continue;
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false && $timestamp > 0) {
                $parsed[$key] = $timestamp;
            }
        }

        return $parsed;
    }
}
