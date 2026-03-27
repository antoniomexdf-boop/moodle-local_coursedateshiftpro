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
 * Date shifting engine for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursedateshiftpro\local;

use xmldb_field;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

/**
 * Pro demo service for selective course date shifting.
 */
class date_shifter {
    /** @var string History table name. */
    private const HISTORY_TABLE = 'local_coursedateshiftpro_hist';

    /** @var array<string, string[]> Suggested date fields by module name for validation hints. */
    private const EXPECTED_ACTIVITY_DATE_FIELDS = [
        'assign' => ['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'],
        'quiz' => ['timeopen', 'timeclose'],
        'forum' => ['duedate', 'cutoffdate'],
        'lesson' => ['availablefrom', 'availableuntil'],
        'workshop' => ['submissionstart', 'submissionend'],
    ];

    /** @var array<string, string> Date ordering rules. */
    private const FIELD_SEQUENCE_RULES = [
        'allowsubmissionsfromdate' => 'duedate',
        'duedate' => 'cutoffdate',
        'timeopen' => 'timeclose',
        'availablefrom' => 'availableuntil',
        'submissionstart' => 'submissionend',
    ];

    /** @var array<string, array<string, int>> Relative date suggestions driven by one anchor field. */
    private const LINKED_DATE_RULES = [
        'duedate' => [
            'allowsubmissionsfromdate' => -1,
            'cutoffdate' => 1,
            'gradingduedate' => 0,
        ],
        'timeclose' => [
            'timeopen' => -1,
        ],
        'availableuntil' => [
            'availablefrom' => -1,
        ],
        'submissionend' => [
            'submissionstart' => -1,
        ],
    ];

    /** @var string[] activity instance date fields. */
    private const ACTIVITY_DATE_FIELDS = [
        'startdate',
        'enddate',
        'timeopen',
        'timeclose',
        'timeavailable',
        'timeend',
        'timefinish',
        'timestart',
        'availablefrom',
        'availableuntil',
        'duedate',
        'cutoffdate',
        'allowsubmissionsfromdate',
        'gradingduedate',
        'submissionend',
        'submissionstart',
        'releaseafter',
    ];

    /** @var array<string,float> Relative workload weights used for weekly balance. */
    private const FIELD_WORKLOAD_WEIGHTS = [
        'duedate' => 1.5,
        'cutoffdate' => 1.25,
        'timeclose' => 1.25,
        'submissionend' => 1.25,
        'gradingduedate' => 1.0,
        'completionexpected' => 1.0,
        'enddate' => 1.0,
        'timeopen' => 0.75,
        'availableuntil' => 0.75,
        'availability' => 0.5,
        'availablefrom' => 0.5,
        'allowsubmissionsfromdate' => 0.5,
        'submissionstart' => 0.5,
        'startdate' => 0.5,
    ];

    /**
     * Returns course options.
     *
     * @return array
     */
    public static function get_course_options(): array {
        global $DB;

        $options = [];
        $records = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname, shortname');
        foreach ($records as $record) {
            $options[$record->id] = format_string($record->fullname) . ' (' . s($record->shortname) . ')';
        }

        return $options;
    }

    /**
     * Loads one course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_course(int $courseid): ?\stdClass {
        global $DB;

        if ($courseid <= 0 || $courseid === SITEID) {
            return null;
        }

        return $DB->get_record('course', ['id' => $courseid]);
    }

    /**
     * Ensures the filter payload always has the expected keys.
     *
     * @param array $rawfilters
     * @param bool $usedefaults
     * @return array
     */
    public static function normalise_filters(array $rawfilters, bool $usedefaults = true): array {
        $defaults = [
            'courseenddate' => 1,
            'activities' => 1,
            'sections' => 1,
            'restrictions' => 1,
            'overrides' => 1,
            'completionexpected' => 1,
        ];

        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $rawfilters)) {
                $rawfilters[$key] = $usedefaults ? $default : 0;
            }
        }

        return array_map(static function($value): int {
            return empty($value) ? 0 : 1;
        }, $rawfilters);
    }

    /**
     * Builds the preview of all shiftable items.
     *
     * @param int $courseid
     * @param int $newstartdate
     * @param array $filters
     * @return array
     */
    public static function build_preview(
        int $courseid,
        int $newstartdate,
        array $filters,
        bool $useautoschedule = false,
        array $manualdates = []
    ): array {
        global $DB;

        $course = self::get_course($courseid);
        if (!$course) {
            return [];
        }

        $oldstartdate = (int)$course->startdate;
        if ($oldstartdate <= 0) {
            return [];
        }

        $delta = $newstartdate - $oldstartdate;
        $items = [];
        $skipped = [];
        $validations = [
            'errors' => [],
            'warnings' => [],
            'info' => [],
        ];

        if (!empty($filters['courseenddate']) && !empty($course->enddate)) {
            self::add_item($items, [
                'category' => 'courseenddate',
                'table' => 'course',
                'recordid' => (int)$course->id,
                'field' => 'enddate',
                'courseorder' => 0,
                'label' => format_string($course->fullname),
                'itemurl' => self::get_course_url((int)$course->id),
                'fieldlabel' => self::humanise_field('enddate'),
                'current' => (int)$course->enddate,
                'new' => self::shift_timestamp((int)$course->enddate, $delta),
            ]);
        }

        if (!empty($filters['sections'])) {
            $sections = $DB->get_records('course_sections', ['course' => $courseid], '', 'id, section, name, availability');
            foreach ($sections as $section) {
                if (empty($section->availability)) {
                    continue;
                }

                $decoded = json_decode($section->availability, true);
                if (!is_array($decoded)) {
                    continue;
                }

                $label = trim((string)$section->name) !== '' ? format_string($section->name) : get_string('sectionx', 'moodle', $section->section);
                self::collect_availability_items(
                    $decoded,
                    $delta,
                    'sections',
                    'course_sections',
                    (int)$section->id,
                    $label,
                    (int)$section->section,
                    '',
                    $items
                );
            }
        }

        $modulemap = self::get_module_table_map();
        $coursemoduleordermap = self::get_course_module_order_map($courseid);
        $modinfo = get_fast_modinfo($course);
        $cms = $DB->get_records('course_modules', ['course' => $courseid], '', 'id, module, instance, availability, completionexpected');

        foreach ($cms as $cm) {
            $modname = $modulemap[$cm->module] ?? '';
            $cmname = self::get_cm_name($modinfo, (int)$cm->id, $modname);
            $courseorder = $coursemoduleordermap[(int)$cm->id] ?? (10000 + (int)$cm->id);
            $moduledatefieldsdetected = [];
            $hasavailabilityrestrictions = false;

            if (!empty($filters['completionexpected']) && !empty($cm->completionexpected)) {
                self::add_item($items, [
                    'category' => 'completionexpected',
                    'table' => 'course_modules',
                    'recordid' => (int)$cm->id,
                    'field' => 'completionexpected',
                    'courseorder' => $courseorder,
                    'itemurl' => self::get_cm_url($modinfo, (int)$cm->id),
                    'label' => $cmname,
                    'fieldlabel' => self::humanise_field('completionexpected'),
                    'current' => (int)$cm->completionexpected,
                    'new' => self::shift_timestamp((int)$cm->completionexpected, $delta),
                ]);
            }

            if (!empty($filters['restrictions']) && !empty($cm->availability)) {
                $decoded = json_decode($cm->availability, true);
                if (is_array($decoded)) {
                    $hasavailabilityrestrictions = true;
                    self::collect_availability_items(
                        $decoded,
                        $delta,
                        'restrictions',
                        'course_modules',
                        (int)$cm->id,
                        $cmname,
                        $courseorder,
                        self::get_cm_url($modinfo, (int)$cm->id),
                        $items
                    );
                }
            }

            $modtable = $modulemap[$cm->module] ?? null;
            if (empty($modtable)) {
                $skipped[] = get_string('skipunknownmodule', 'local_coursedateshiftpro', $cmname);
                continue;
            }

            if (!empty($filters['activities'])) {
                $record = $DB->get_record($modtable, ['id' => $cm->instance]);
                if ($record) {
                    foreach (self::ACTIVITY_DATE_FIELDS as $field) {
                        if (!property_exists($record, $field) || empty($record->{$field})) {
                            continue;
                        }

                        self::add_item($items, [
                            'category' => 'activities',
                            'table' => $modtable,
                            'recordid' => (int)$record->id,
                            'field' => $field,
                            'courseorder' => $courseorder,
                            'itemurl' => self::get_cm_url($modinfo, (int)$cm->id),
                            'label' => $cmname,
                            'fieldlabel' => self::humanise_field_for_module($field, $modname),
                            'current' => (int)$record->{$field},
                            'new' => self::shift_timestamp((int)$record->{$field}, $delta),
                        ]);
                        $moduledatefieldsdetected[] = $field;
                    }

                    self::collect_missing_date_proposals(
                        $modname,
                        $modtable,
                        (int)$record->id,
                        $cmname,
                        $courseorder,
                        self::get_cm_url($modinfo, (int)$cm->id),
                        $moduledatefieldsdetected,
                        $newstartdate,
                        $delta,
                        $items,
                        $validations
                    );

                    self::collect_missing_date_validation(
                        $modname,
                        $cmname,
                        $moduledatefieldsdetected,
                        $hasavailabilityrestrictions,
                        $validations
                    );
                } else {
                    $skipped[] = get_string('skipmissingactivityrecord', 'local_coursedateshiftpro', $cmname);
                }
            }

            if (!empty($filters['overrides'])) {
                self::collect_override_items(
                    $modtable,
                    $modname,
                    (int)$cm->instance,
                    $cmname,
                    $delta,
                    $courseorder,
                    self::get_cm_url($modinfo, (int)$cm->id),
                    $items
                );
            }
        }

        $futurecourseend = !empty($course->enddate) ? self::shift_timestamp((int)$course->enddate, $delta) : 0;
        $autoschedule = self::build_autoschedule_plan($items, $newstartdate, $futurecourseend);
        $recommendeditems = !empty($autoschedule['items']) ? $autoschedule['items'] : $items;
        $manualitems = $items;
        if (!empty($manualdates)) {
            $recommendeditems = self::apply_manual_date_overrides(
                $recommendeditems,
                $manualdates,
                $newstartdate,
                $futurecourseend
            );
            $manualitems = self::apply_manual_date_overrides(
                $items,
                $manualdates,
                $newstartdate,
                $futurecourseend
            );
            $autoschedule['summary'][] = get_string('autoschedulemanualoverride', 'local_coursedateshiftpro');
        }

        $effectiveitems = $items;
        if (!empty($useautoschedule) && !empty($autoschedule['available'])) {
            $effectiveitems = $recommendeditems;
        } else if (!empty($manualdates)) {
            $effectiveitems = $manualitems;
        }

        self::collect_course_validations($course, $newstartdate, $effectiveitems, $validations);
        self::collect_effective_sequence_validations($effectiveitems, $validations);
        self::collect_schedule_balance_advisories($effectiveitems, $validations);
        $itemadvisories = self::build_item_advisories($course, $newstartdate, $items, $effectiveitems);

        $groupeditems = [];
        foreach ($items as $item) {
            $groupeditems[$item['category']][] = $item;
        }

        return [
            'courseid' => $courseid,
            'coursename' => format_string($course->fullname),
            'oldstartdate' => $oldstartdate,
            'newstartdate' => $newstartdate,
            'delta' => $delta,
            'items' => $items,
            'effectiveitems' => $effectiveitems,
            'weeklyload' => self::build_weekly_load_overview($effectiveitems, $newstartdate, self::get_effective_course_end_date($course, $effectiveitems, $newstartdate)),
            'groupeditems' => $groupeditems,
            'skipped' => array_values(array_unique($skipped)),
            'autoschedule' => [
                'enabled' => $useautoschedule ? 1 : 0,
                'available' => !empty($autoschedule['available']) ? 1 : 0,
                'adjustmentcount' => (int)($autoschedule['adjustmentcount'] ?? 0),
                'summary' => $autoschedule['summary'] ?? [],
                'items' => $recommendeditems,
            ],
            'manualitems' => $manualitems,
            'itemadvisories' => $itemadvisories,
            'validations' => [
                'errors' => array_values(array_unique($validations['errors'])),
                'warnings' => array_values(array_unique($validations['warnings'])),
                'info' => array_values(array_unique($validations['info'])),
            ],
        ];
    }

    /**
     * Builds a user-friendly weekly load overview for the current preview.
     *
     * @param array $items
     * @param int $coursestart
     * @param int $courseend
     * @return array
     */
    private static function build_weekly_load_overview(array $items, int $coursestart, int $courseend): array {
        $lasttimestamp = $courseend;
        foreach ($items as $item) {
            $timestamp = (int)($item['new'] ?? 0);
            if ($timestamp > $lasttimestamp) {
                $lasttimestamp = $timestamp;
            }
        }

        if ($coursestart <= 0) {
            return [];
        }

        if ($lasttimestamp <= $coursestart) {
            $lasttimestamp = $coursestart + (DAYSECS - 1);
        }

        $totalseconds = max(1, $lasttimestamp - $coursestart);
        $totalweeks = max(1, (int)ceil(($totalseconds + 1) / WEEKSECS));
        $weeks = [];
        $itemweekranges = [];

        for ($index = 1; $index <= $totalweeks; $index++) {
            $weekstart = $coursestart + (($index - 1) * WEEKSECS);
            $weekend = min($lasttimestamp, $weekstart + WEEKSECS - 1);
            $weeks[$index] = [
                'weeknumber' => $index,
                'start' => $weekstart,
                'end' => $weekend,
                'datecount' => 0,
                'activitycount' => 0,
                'weight' => 0.0,
                'items' => [],
            ];
        }

        foreach ($items as $item) {
            $timestamp = (int)($item['new'] ?? 0);
            if ($timestamp <= 0 || $timestamp < $coursestart) {
                continue;
            }

            $weekindex = (int)floor(($timestamp - $coursestart) / WEEKSECS) + 1;
            $weekindex = max(1, min($totalweeks, $weekindex));
            $weeks[$weekindex]['datecount']++;
            $weeks[$weekindex]['weight'] += self::get_item_workload_weight($item);
            $label = (string)($item['label'] ?? '');
            $signature = implode(':', [
                (string)($item['category'] ?? ''),
                (string)($item['table'] ?? ''),
                (int)($item['recordid'] ?? 0),
                $label,
            ]);
            if ($label !== '') {
                if (!isset($itemweekranges[$signature])) {
                    $itemweekranges[$signature] = [
                        'firstweek' => $weekindex,
                        'lastweek' => $weekindex,
                    ];
                } else {
                    $itemweekranges[$signature]['firstweek'] = min($itemweekranges[$signature]['firstweek'], $weekindex);
                    $itemweekranges[$signature]['lastweek'] = max($itemweekranges[$signature]['lastweek'], $weekindex);
                }
                if (!isset($weeks[$weekindex]['items'][$signature])) {
                    $weeks[$weekindex]['items'][$signature] = [
                        'signature' => $signature,
                        'label' => $label,
                        'itemurl' => (string)($item['itemurl'] ?? ''),
                        'count' => 0,
                        'sortts' => $timestamp,
                    ];
                }
                $weeks[$weekindex]['items'][$signature]['count']++;
                if ($timestamp > 0 && $timestamp < (int)$weeks[$weekindex]['items'][$signature]['sortts']) {
                    $weeks[$weekindex]['items'][$signature]['sortts'] = $timestamp;
                }
            }
        }

        $busyweeks = 0;
        $emptyweeks = 0;
        $scheduleddates = 0;
        $scheduledactivities = [];
        $weekappearances = 0;

        foreach ($weeks as $index => $week) {
            $weeks[$index]['activitycount'] = count($week['items']);
            uasort($weeks[$index]['items'], static function(array $left, array $right): int {
                if ((int)$left['sortts'] !== (int)$right['sortts']) {
                    return (int)$left['sortts'] <=> (int)$right['sortts'];
                }

                return strcmp((string)$left['label'], (string)$right['label']);
            });
            $weeks[$index]['itemsummary'] = [];
            foreach ($weeks[$index]['items'] as $weekitem) {
                $signature = (string)($weekitem['signature'] ?? '');
                $firstrangeweek = (int)($itemweekranges[$signature]['firstweek'] ?? 0);
                $lastrangeweek = (int)($itemweekranges[$signature]['lastweek'] ?? 0);
                $marker = '';
                if ($firstrangeweek > 0 && $lastrangeweek > 0) {
                    if ($firstrangeweek === $index && $lastrangeweek === $index) {
                        $marker = 'single';
                    } else if ($firstrangeweek === $index) {
                        $marker = 'start';
                    } else if ($lastrangeweek === $index) {
                        $marker = 'end';
                    }
                }
                $weeks[$index]['itemsummary'][] = [
                    'label' => (string)$weekitem['label'],
                    'itemurl' => (string)($weekitem['itemurl'] ?? ''),
                    'count' => (int)$weekitem['count'],
                    'sortts' => (int)$weekitem['sortts'],
                    'marker' => $marker,
                ];
            }
            unset($weeks[$index]['items']);
            $weeks[$index]['loadlevel'] = self::get_weekly_load_level((float)$weeks[$index]['weight']);

            if ($weeks[$index]['loadlevel'] === 'high') {
                $busyweeks++;
            }
            if ($weeks[$index]['datecount'] === 0) {
                $emptyweeks++;
            }

            $scheduleddates += $weeks[$index]['datecount'];
            $weekappearances += $weeks[$index]['activitycount'];
        }

        foreach ($items as $item) {
            if (!empty($item['label']) && !empty($item['new'])) {
                $scheduledactivities[$item['label']] = true;
            }
        }

        return [
            'totalweeks' => $totalweeks,
            'scheduleddates' => $scheduleddates,
            'scheduledactivities' => count($scheduledactivities),
            'weekappearances' => $weekappearances,
            'busyweeks' => $busyweeks,
            'emptyweeks' => $emptyweeks,
            'weeks' => array_values($weeks),
        ];
    }

    /**
     * Returns a simplified user-facing weekly load level.
     *
     * @param float $weight
     * @return string
     */
    private static function get_weekly_load_level(float $weight): string {
        if ($weight >= 6.5) {
            return 'high';
        }

        if ($weight >= 3.5) {
            return 'medium';
        }

        return 'light';
    }

    /**
     * Applies only the selected changes plus the new course start date.
     *
     * @param int $courseid
     * @param int $newstartdate
     * @param array $filters
     * @param array $selectedkeys
     * @return array
     */
    public static function apply_selected_changes(
        int $courseid,
        int $newstartdate,
        array $filters,
        array $selectedkeys,
        bool $useautoschedule = false,
        array $manualdates = []
    ): array {
        global $DB;

        $preview = self::build_preview($courseid, $newstartdate, $filters, $useautoschedule, $manualdates);
        $course = self::get_course($courseid);
        if (!$course) {
            throw new \moodle_exception('errorcoursemissing', 'local_coursedateshiftpro');
        }

        if (empty($course->startdate)) {
            throw new \moodle_exception('errorcurrentstartmissing', 'local_coursedateshiftpro');
        }

        $selectedlookup = array_flip($selectedkeys);
        $summary = [
            'course' => 0,
            'sections' => 0,
            'activities' => 0,
            'overrides' => 0,
            'availability' => 0,
            'fields' => 0,
            'records' => 0,
            'blocks' => [],
            'skipped' => $preview['skipped'] ?? [],
            'rollback' => [],
            'report' => [
                'lines' => [],
            ],
        ];

        if ((int)$course->startdate === $newstartdate && empty($selectedlookup)) {
            return $summary;
        }

        $updates = [];
        if ((int)$course->startdate !== $newstartdate) {
            $updates['course'][$courseid] = [
                'fields' => ['startdate' => $newstartdate],
                'availability' => [],
            ];
        }

        $transaction = $DB->start_delegated_transaction();

        $previewitems = $preview['effectiveitems'] ?? $preview['items'];
        $reportitems = self::build_execution_report_lines(
            $course,
            $preview,
            $selectedlookup,
            $newstartdate,
            $useautoschedule,
            $manualdates
        );
        $summary['report']['lines'] = $reportitems;

        foreach ($previewitems as $item) {
            if (!isset($selectedlookup[$item['key']])) {
                continue;
            }

            if (!empty($item['autoproposedonly']) && empty($useautoschedule) && empty($manualdates[$item['key']])) {
                continue;
            }

            $summary['blocks'][$item['category']] = self::get_block_label($item['category']);

            if (!isset($updates[$item['table']][$item['recordid']])) {
                $updates[$item['table']][$item['recordid']] = [
                    'fields' => [],
                    'availability' => [],
                ];
            }

            if ($item['field'] === 'availability') {
                $updates[$item['table']][$item['recordid']]['availability'][] = [
                    'path' => $item['path'],
                    'new' => $item['new'],
                    'category' => $item['category'],
                ];
            } else {
                $updates[$item['table']][$item['recordid']]['fields'][$item['field']] = $item['new'];
            }
        }

        foreach ($updates as $table => $records) {
            foreach ($records as $recordid => $payload) {
                $changes = ['id' => $recordid];
                $recordtouched = false;
                $rollbackentry = [
                    'table' => $table,
                    'recordid' => $recordid,
                    'fields' => [],
                    'availability' => null,
                ];

                $existingrecord = $DB->get_record($table, ['id' => $recordid]);
                if (!$existingrecord) {
                    continue;
                }

                foreach ($payload['fields'] as $field => $value) {
                    $rollbackentry['fields'][$field] = $existingrecord->{$field} ?? null;
                    $changes[$field] = $value;
                    $summary['fields']++;
                    $recordtouched = true;
                }

                if (!empty($payload['availability'])) {
                    if (!empty($existingrecord->availability)) {
                        $decoded = json_decode($existingrecord->availability, true);
                        if (is_array($decoded)) {
                            $rollbackentry['availability'] = $existingrecord->availability;
                            foreach ($payload['availability'] as $availabilitychange) {
                                if (self::set_availability_timestamp_by_path($decoded, $availabilitychange['path'], (int)$availabilitychange['new'])) {
                                    $summary['availability']++;
                                    $recordtouched = true;
                                }
                            }

                            $changes['availability'] = json_encode($decoded);
                        }
                    }
                }

                if (!$recordtouched) {
                    continue;
                }

                $DB->update_record($table, (object)$changes);
                self::increment_summary_bucket($summary, $table);
                $summary['records']++;
                $summary['rollback'][] = $rollbackentry;
            }
        }

        rebuild_course_cache($courseid, true);
        $transaction->allow_commit();

        $summary['autoscheduleapplied'] = !empty($preview['autoschedule']['enabled']) && !empty($preview['autoschedule']['available']) ? 1 : 0;
        $summary['autoscheduleadjustments'] = (int)($preview['autoschedule']['adjustmentcount'] ?? 0);

        return $summary;
    }

    /**
     * Rolls back one previously applied snapshot.
     *
     * @param int $courseid
     * @param array $rollbackentries
     * @return array
     */
    public static function rollback_selected_changes(int $courseid, array $rollbackentries): array {
        global $DB;

        $summary = [
            'records' => 0,
            'fields' => 0,
        ];

        if (empty($rollbackentries)) {
            return $summary;
        }

        $transaction = $DB->start_delegated_transaction();
        foreach ($rollbackentries as $entry) {
            $changes = ['id' => (int)$entry['recordid']];
            $touched = false;

            foreach (($entry['fields'] ?? []) as $field => $value) {
                $changes[$field] = $value;
                $summary['fields']++;
                $touched = true;
            }

            if (!empty($entry['availability'])) {
                $changes['availability'] = $entry['availability'];
                $summary['fields']++;
                $touched = true;
            }

            if (!$touched) {
                continue;
            }

            $DB->update_record($entry['table'], (object)$changes);
            $summary['records']++;
        }

        rebuild_course_cache($courseid, true);
        $transaction->allow_commit();

        return $summary;
    }

    /**
     * Stores one completed execution in the plugin history.
     *
     * @param int $courseid
     * @param array $preview
     * @param array $filters
     * @param array $selectedkeys
     * @param array $summary
     * @return int
     */
    public static function store_execution_history(
        int $courseid,
        array $preview,
        array $filters,
        array $selectedkeys,
        array $summary
    ): int {
        global $DB, $USER;

        $storedsummary = self::normalise_summary_for_storage($summary);
        if (!empty($preview['weeklyload']) && is_array($preview['weeklyload'])) {
            $storedsummary['weeklyload'] = $preview['weeklyload'];
        }

        $record = (object)[
            'courseid' => $courseid,
            'userid' => (int)$USER->id,
            'action' => 'apply',
            'status' => 'completed',
            'sourceexecutionid' => 0,
            'oldstartdate' => (int)($preview['oldstartdate'] ?? 0),
            'newstartdate' => (int)($preview['newstartdate'] ?? 0),
            'delta' => (int)($preview['delta'] ?? 0),
            'filtersjson' => json_encode($filters),
            'selectedkeysjson' => json_encode(array_values($selectedkeys)),
            'summaryjson' => json_encode($storedsummary),
            'rollbackjson' => json_encode(array_values($summary['rollback'] ?? [])),
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        return (int)$DB->insert_record(self::HISTORY_TABLE, $record);
    }

    /**
     * Rolls back one stored execution and registers the rollback in history.
     *
     * @param int $executionid
     * @return array
     */
    public static function rollback_execution(int $executionid): array {
        global $DB, $USER;

        $execution = $DB->get_record(self::HISTORY_TABLE, ['id' => $executionid, 'action' => 'apply']);
        if (!$execution) {
            throw new \moodle_exception('errorhistorymissing', 'local_coursedateshiftpro');
        }

        if ($execution->status === 'rolledback') {
            throw new \moodle_exception('errorhistoryrolledback', 'local_coursedateshiftpro');
        }

        $rollbackentries = json_decode((string)$execution->rollbackjson, true);
        if (empty($rollbackentries) || !is_array($rollbackentries)) {
            throw new \moodle_exception('errorrollbackmissing', 'local_coursedateshiftpro');
        }

        $summary = self::rollback_selected_changes((int)$execution->courseid, $rollbackentries);
        $storedsummary = json_decode((string)$execution->summaryjson, true) ?: [];
        if (!empty($storedsummary['blocks'])) {
            $summary['blocks'] = $storedsummary['blocks'];
        }
        if (!empty($storedsummary['weeklyload']) && is_array($storedsummary['weeklyload'])) {
            $summary['weeklyload'] = $storedsummary['weeklyload'];
        }
        if (!empty($storedsummary['report'])) {
            $summary['report'] = self::reverse_execution_report((array)$storedsummary['report']);
        }
        $time = time();

        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::HISTORY_TABLE, (object)[
            'id' => (int)$execution->id,
            'status' => 'rolledback',
            'timemodified' => $time,
        ]);

        $rollbackrecord = (object)[
            'courseid' => (int)$execution->courseid,
            'userid' => (int)$USER->id,
            'action' => 'rollback',
            'status' => 'completed',
            'sourceexecutionid' => (int)$execution->id,
            'oldstartdate' => (int)$execution->newstartdate,
            'newstartdate' => (int)$execution->oldstartdate,
            'delta' => (int)$execution->oldstartdate - (int)$execution->newstartdate,
            'filtersjson' => (string)$execution->filtersjson,
            'selectedkeysjson' => (string)$execution->selectedkeysjson,
            'summaryjson' => json_encode($summary),
            'rollbackjson' => json_encode([]),
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        $rollbackid = (int)$DB->insert_record(self::HISTORY_TABLE, $rollbackrecord);
        $transaction->allow_commit();

        $summary['executionid'] = $executionid;
        $summary['rollbackhistoryid'] = $rollbackid;
        $summary['courseid'] = (int)$execution->courseid;

        return $summary;
    }

    /**
     * Returns the most recent executions for one course or for all courses.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_history(int $courseid = 0, int $limit = 10): array {
        global $DB;

        $params = [];
        $where = '';
        if ($courseid > 0) {
            $where = 'WHERE h.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT h.*, c.fullname AS coursename, c.shortname,
                       u.firstname, u.lastname
                  FROM {" . self::HISTORY_TABLE . "} h
                  JOIN {course} c ON c.id = h.courseid
             LEFT JOIN {user} u ON u.id = h.userid
                  {$where}
              ORDER BY h.timecreated DESC, h.id DESC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        foreach ($records as $record) {
            $record->summary = json_decode((string)$record->summaryjson, true) ?: [];
            $record->canrollback = $record->action === 'apply' && $record->status !== 'rolledback';
            $record->statuslabel = self::get_history_status_label((string)$record->action, (string)$record->status);
            $record->actorname = trim(fullname($record)) !== '' ? fullname($record) : get_string('deleteduser', 'moodle');
        }

        return $records;
    }

    /**
     * Returns one stored execution with decoded payloads.
     *
     * @param int $executionid
     * @return \stdClass
     */
    public static function get_history_execution(int $executionid): \stdClass {
        global $DB;

        $sql = "SELECT h.*, c.fullname AS coursename, c.shortname,
                       u.firstname, u.lastname
                  FROM {" . self::HISTORY_TABLE . "} h
                  JOIN {course} c ON c.id = h.courseid
             LEFT JOIN {user} u ON u.id = h.userid
                 WHERE h.id = :id";
        $record = $DB->get_record_sql($sql, ['id' => $executionid]);
        if (!$record) {
            throw new \moodle_exception('errorhistorymissing', 'local_coursedateshiftpro');
        }

        $record->filters = json_decode((string)$record->filtersjson, true) ?: [];
        $record->selectedkeys = json_decode((string)$record->selectedkeysjson, true) ?: [];
        $record->summary = json_decode((string)$record->summaryjson, true) ?: [];
        $record->statuslabel = self::get_history_status_label((string)$record->action, (string)$record->status);
        $record->actorname = trim(fullname($record)) !== '' ? fullname($record) : get_string('deleteduser', 'moodle');
        if (($record->action ?? '') === 'rollback' && empty($record->summary['report']) && !empty($record->sourceexecutionid)) {
            $source = $DB->get_record(self::HISTORY_TABLE, ['id' => (int)$record->sourceexecutionid]);
            if ($source) {
                $sourcesummary = json_decode((string)$source->summaryjson, true) ?: [];
                if (!empty($sourcesummary['report'])) {
                    $record->summary['report'] = self::reverse_execution_report((array)$sourcesummary['report']);
                }
            }
        }
        return $record;
    }

    /**
     * Adds one item with a stable key.
     *
     * @param array $items
     * @param array $item
     * @return void
     */
    private static function add_item(array &$items, array $item): void {
        $parts = [
            $item['category'],
            $item['table'],
            $item['recordid'],
            $item['field'],
        ];

        if (!empty($item['path'])) {
            $parts[] = $item['path'];
        }

        $item['signature'] = implode(':', $parts);
        $item['key'] = 'k_' . md5($item['signature']);
        $item['path'] = $item['path'] ?? '';
        $item['courseorder'] = $item['courseorder'] ?? 999999;
        $item['itemurl'] = $item['itemurl'] ?? '';
        $items[$item['key']] = $item;
    }

    /**
     * Collects override items if the table exists.
     *
     * @param string $modtable
     * @param int $instanceid
     * @param string $cmname
     * @param int $delta
     * @param array $items
     * @return void
     */
    private static function collect_override_items(
        string $modtable,
        string $modname,
        int $instanceid,
        string $cmname,
        int $delta,
        int $courseorder,
        string $itemurl,
        array &$items
    ): void {
        global $DB;

        $overridetable = $modtable . '_overrides';
        $foreignkey = $modtable . 'id';
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(new xmldb_table($overridetable))) {
            return;
        }

        if (!$dbman->field_exists(new xmldb_table($overridetable), new xmldb_field($foreignkey))) {
            return;
        }

        $records = $DB->get_records($overridetable, [$foreignkey => $instanceid]);
        foreach ($records as $record) {
            foreach (self::ACTIVITY_DATE_FIELDS as $field) {
                if (!property_exists($record, $field) || empty($record->{$field})) {
                    continue;
                }

                self::add_item($items, [
                    'category' => 'overrides',
                    'table' => $overridetable,
                    'recordid' => (int)$record->id,
                    'field' => $field,
                    'courseorder' => $courseorder,
                    'itemurl' => $itemurl,
                    'label' => $cmname . ' - ' . get_string('overrideitemlabel', 'local_coursedateshiftpro', $record->id),
                    'fieldlabel' => self::humanise_field_for_module($field, $modname),
                    'current' => (int)$record->{$field},
                    'new' => self::shift_timestamp((int)$record->{$field}, $delta),
                ]);
            }
        }
    }

    /**
     * Collects date restrictions from availability JSON.
     *
     * @param array $node
     * @param int $delta
     * @param string $category
     * @param string $table
     * @param int $recordid
     * @param string $label
     * @param array $items
     * @param array $path
     * @return void
     */
    private static function collect_availability_items(
        array $node,
        int $delta,
        string $category,
        string $table,
        int $recordid,
        string $label,
        int $courseorder,
        string $itemurl,
        array &$items,
        array $path = []
    ): void {
        if (($node['type'] ?? null) === 'date' && !empty($node['t'])) {
            self::add_item($items, [
                'category' => $category,
                'table' => $table,
                'recordid' => $recordid,
                'field' => 'availability',
                'courseorder' => $courseorder,
                'itemurl' => $itemurl,
                'path' => implode('-', $path),
                'label' => $label,
                'fieldlabel' => self::humanise_field('availability'),
                'current' => (int)$node['t'],
                'new' => self::shift_timestamp((int)$node['t'], $delta),
            ]);
        }

        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (self::is_assoc($value)) {
                self::collect_availability_items($value, $delta, $category, $table, $recordid, $label, $courseorder, $itemurl, $items, array_merge($path, [$key]));
                continue;
            }

            foreach ($value as $index => $child) {
                if (is_array($child)) {
                    self::collect_availability_items(
                        $child,
                        $delta,
                        $category,
                        $table,
                        $recordid,
                        $label,
                        $courseorder,
                        $itemurl,
                        $items,
                        array_merge($path, [$key, $index])
                    );
                }
            }
        }
    }

    /**
     * Returns the course module display name.
     *
     * @param \course_modinfo $modinfo
     * @param int $cmid
     * @param string $fallback
     * @return string
     */
    private static function get_cm_name(\course_modinfo $modinfo, int $cmid, string $fallback): string {
        $cm = $modinfo->get_cm($cmid);
        if ($cm && trim((string)$cm->name) !== '') {
            return format_string($cm->name);
        }

        return $fallback !== '' ? $fallback : get_string('activity');
    }

    /**
     * Returns module id => table name.
     *
     * @return array
     */
    private static function get_module_table_map(): array {
        global $DB;

        static $modulemap = null;
        if ($modulemap !== null) {
            return $modulemap;
        }

        $modulemap = [];
        $modules = $DB->get_records('modules', null, '', 'id, name');
        foreach ($modules as $module) {
            $modulemap[(int)$module->id] = $module->name;
        }

        return $modulemap;
    }

    /**
     * Updates one availability timestamp by a given path.
     *
     * @param array $node
     * @param string $path
     * @param int $newvalue
     * @return bool
     */
    private static function set_availability_timestamp_by_path(array &$node, string $path, int $newvalue): bool {
        if ($path === '') {
            if (($node['type'] ?? null) === 'date') {
                $node['t'] = $newvalue;
                return true;
            }

            return false;
        }

        $segments = explode('-', $path);
        $cursor = &$node;
        foreach ($segments as $segment) {
            if (!isset($cursor[$segment])) {
                return false;
            }

            $cursor = &$cursor[$segment];
        }

        if (($cursor['type'] ?? null) !== 'date') {
            return false;
        }

        $cursor['t'] = $newvalue;
        return true;
    }

    /**
     * Returns a human readable field label.
     *
     * @param string $field
     * @return string
     */
    private static function humanise_field(string $field): string {
        $map = [
            'enddate' => get_string('field_enddate', 'local_coursedateshiftpro'),
            'startdate' => get_string('field_startdate', 'local_coursedateshiftpro'),
            'timeopen' => get_string('field_timeopen', 'local_coursedateshiftpro'),
            'timeclose' => get_string('field_timeclose', 'local_coursedateshiftpro'),
            'timeavailable' => get_string('field_timeavailable', 'local_coursedateshiftpro'),
            'timeend' => get_string('field_timeend', 'local_coursedateshiftpro'),
            'timefinish' => get_string('field_timefinish', 'local_coursedateshiftpro'),
            'timestart' => get_string('field_timestart', 'local_coursedateshiftpro'),
            'availablefrom' => get_string('field_availablefrom', 'local_coursedateshiftpro'),
            'availableuntil' => get_string('field_availableuntil', 'local_coursedateshiftpro'),
            'duedate' => get_string('field_duedate', 'local_coursedateshiftpro'),
            'cutoffdate' => get_string('field_cutoffdate', 'local_coursedateshiftpro'),
            'allowsubmissionsfromdate' => get_string('field_allowsubmissionsfromdate', 'local_coursedateshiftpro'),
            'completionexpected' => get_string('field_completionexpected', 'local_coursedateshiftpro'),
            'gradingduedate' => get_string('field_gradingduedate', 'local_coursedateshiftpro'),
            'submissionend' => get_string('field_submissionend', 'local_coursedateshiftpro'),
            'submissionstart' => get_string('field_submissionstart', 'local_coursedateshiftpro'),
            'releaseafter' => get_string('field_releaseafter', 'local_coursedateshiftpro'),
            'availability' => get_string('field_availability', 'local_coursedateshiftpro'),
        ];

        return $map[$field] ?? $field;
    }

    /**
     * Returns a Moodle-standard label for known module fields when possible.
     *
     * @param string $field
     * @param string $modname
     * @return string
     */
    private static function humanise_field_for_module(string $field, string $modname = ''): string {
        if ($modname === 'assign') {
            $assignmap = [
                'allowsubmissionsfromdate' => ['identifier' => 'allowsubmissionsfromdate', 'component' => 'assign'],
                'duedate' => ['identifier' => 'duedate', 'component' => 'assign'],
                'cutoffdate' => ['identifier' => 'cutoffdate', 'component' => 'assign'],
                'gradingduedate' => ['identifier' => 'gradingduedate', 'component' => 'assign'],
            ];

            if (isset($assignmap[$field])) {
                return get_string($assignmap[$field]['identifier'], $assignmap[$field]['component']);
            }
        }

        return self::humanise_field($field);
    }

    /**
     * Returns the label for one block/category.
     *
     * @param string $category
     * @return string
     */
    private static function get_block_label(string $category): string {
        $labels = [
            'courseenddate' => get_string('filtercourseenddate', 'local_coursedateshiftpro'),
            'activities' => get_string('filteractivities', 'local_coursedateshiftpro'),
            'sections' => get_string('filtersections', 'local_coursedateshiftpro'),
            'restrictions' => get_string('filterrestrictions', 'local_coursedateshiftpro'),
            'overrides' => get_string('filteroverrides', 'local_coursedateshiftpro'),
            'completionexpected' => get_string('filtercompletionexpected', 'local_coursedateshiftpro'),
        ];

        return $labels[$category] ?? $category;
    }

    /**
     * Increments the summary bucket based on the table name.
     *
     * @param array $summary
     * @param string $table
     * @return void
     */
    private static function increment_summary_bucket(array &$summary, string $table): void {
        if ($table === 'course') {
            $summary['course']++;
            $summary['blocks']['courseenddate'] = get_string('filtercourseenddate', 'local_coursedateshiftpro');
            return;
        }

        if ($table === 'course_sections') {
            $summary['sections']++;
            $summary['blocks']['sections'] = get_string('filtersections', 'local_coursedateshiftpro');
            return;
        }

        if ($table === 'course_modules') {
            $summary['activities']++;
            $summary['blocks']['completionexpected'] = get_string('filtercompletionexpected', 'local_coursedateshiftpro');
            return;
        }

        if (substr($table, -10) === '_overrides') {
            $summary['overrides']++;
            $summary['blocks']['overrides'] = get_string('filteroverrides', 'local_coursedateshiftpro');
            return;
        }

        $summary['activities']++;
        $summary['blocks']['activities'] = get_string('filteractivities', 'local_coursedateshiftpro');
    }

    /**
     * Returns whether an array is associative.
     *
     * @param array $array
     * @return bool
     */
    private static function is_assoc(array $array): bool {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Shifts a timestamp by delta without returning non-positive values.
     *
     * @param int $timestamp
     * @param int $delta
     * @return int
     */
    private static function shift_timestamp(int $timestamp, int $delta): int {
        return max(1, $timestamp + $delta);
    }

    /**
     * Converts a runtime summary into a compact storable payload.
     *
     * @param array $summary
     * @return array
     */
    private static function normalise_summary_for_storage(array $summary): array {
        unset($summary['rollback']);
        $summary['blocks'] = array_values($summary['blocks'] ?? []);
        return $summary;
    }

    /**
     * Builds report lines for one applied execution using the same effective preview.
     *
     * @param \stdClass $course
     * @param array $preview
     * @param array $selectedlookup
     * @param int $newstartdate
     * @param bool $useautoschedule
     * @param array $manualdates
     * @return array
     */
    private static function build_execution_report_lines(
        \stdClass $course,
        array $preview,
        array $selectedlookup,
        int $newstartdate,
        bool $useautoschedule,
        array $manualdates
    ): array {
        $lines = [];

        if ((int)$course->startdate !== $newstartdate) {
            $lines[] = [
                'label' => format_string($course->fullname),
                'fieldlabel' => self::humanise_field('startdate'),
                'oldvalue' => (int)$course->startdate,
                'newvalue' => $newstartdate,
                'category' => 'course',
                'weeknumber' => 1,
            ];
        }

        $coursestart = (int)($preview['newstartdate'] ?? 0);
        $items = $preview['effectiveitems'] ?? $preview['items'] ?? [];
        foreach ($items as $item) {
            if (!isset($selectedlookup[$item['key']])) {
                continue;
            }

            if (!empty($item['autoproposedonly']) && empty($useautoschedule) && empty($manualdates[$item['key']])) {
                continue;
            }

            $lines[] = [
                'label' => (string)($item['label'] ?? ''),
                'fieldlabel' => (string)($item['fieldlabel'] ?? self::humanise_field((string)($item['field'] ?? ''))),
                'oldvalue' => (int)($item['current'] ?? 0),
                'newvalue' => (int)($item['new'] ?? 0),
                'category' => (string)($item['category'] ?? ''),
                'weeknumber' => self::get_course_week_number((int)($item['new'] ?? 0), $coursestart),
            ];
        }

        usort($lines, static function(array $left, array $right): int {
            if ((int)$left['weeknumber'] !== (int)$right['weeknumber']) {
                return (int)$left['weeknumber'] <=> (int)$right['weeknumber'];
            }
            if ((int)$left['newvalue'] !== (int)$right['newvalue']) {
                return (int)$left['newvalue'] <=> (int)$right['newvalue'];
            }
            return strcmp((string)$left['label'], (string)$right['label']);
        });

        return $lines;
    }

    /**
     * Reverses one stored execution report for restore exports.
     *
     * @param array $report
     * @return array
     */
    private static function reverse_execution_report(array $report): array {
        $lines = [];
        foreach (($report['lines'] ?? []) as $line) {
            $lines[] = [
                'label' => (string)($line['label'] ?? ''),
                'fieldlabel' => (string)($line['fieldlabel'] ?? ''),
                'oldvalue' => (int)($line['newvalue'] ?? 0),
                'newvalue' => (int)($line['oldvalue'] ?? 0),
                'category' => (string)($line['category'] ?? ''),
                'weeknumber' => (int)($line['weeknumber'] ?? 0),
            ];
        }

        return ['lines' => $lines];
    }

    /**
     * Returns a course-relative week number.
     *
     * @param int $timestamp
     * @param int $coursestart
     * @return int
     */
    private static function get_course_week_number(int $timestamp, int $coursestart): int {
        if ($timestamp <= 0 || $coursestart <= 0) {
            return 0;
        }

        if ($timestamp <= $coursestart) {
            return 1;
        }

        return (int)floor(($timestamp - $coursestart) / WEEKSECS) + 1;
    }

    /**
     * Adds warnings for modules with missing scheduling dates.
     *
     * @param string $modname
     * @param string $cmname
     * @param array $detectedfields
     * @param bool $hasavailabilityrestrictions
     * @param array $validations
     * @return void
     */
    private static function collect_missing_date_validation(
        string $modname,
        string $cmname,
        array $detectedfields,
        bool $hasavailabilityrestrictions,
        array &$validations
    ): void {
        if ($cmname === '') {
            return;
        }

        $detectedfields = array_values(array_unique($detectedfields));
        if (isset(self::EXPECTED_ACTIVITY_DATE_FIELDS[$modname])) {
            $expected = self::EXPECTED_ACTIVITY_DATE_FIELDS[$modname];
            $missing = array_diff($expected, $detectedfields);
            if (!empty($missing)) {
                $validations['info'][] = get_string('validationmissingexpecteddates', 'local_coursedateshiftpro', (object)[
                    'item' => $cmname,
                    'fields' => implode(', ', array_map([self::class, 'humanise_field'], $missing)),
                ]);
            }
            return;
        }

        if (empty($detectedfields) && !$hasavailabilityrestrictions) {
            $validations['info'][] = get_string('validationnodatestodetect', 'local_coursedateshiftpro', $cmname);
        }
    }

    /**
     * Adds validation messages for likely date order issues.
     *
     * @param string $modname
     * @param string $cmname
     * @param \stdClass $record
     * @param int $delta
     * @param array $validations
     * @return void
     */
    /**
     * Adds global course-level validations after preview collection.
     *
     * @param \stdClass $course
     * @param int $newstartdate
     * @param array $items
     * @param array $validations
     * @return void
     */
    private static function collect_course_validations(
        \stdClass $course,
        int $newstartdate,
        array $items,
        array &$validations
    ): void {
        $futurecourseend = self::get_effective_course_end_date($course, $items, $newstartdate);

        if ($futurecourseend > 0 && $futurecourseend <= $newstartdate) {
            $validations['errors'][] = get_string('validationcourseendbeforestart', 'local_coursedateshiftpro');
        }

        foreach ($items as $item) {
            if ($item['new'] < $newstartdate) {
                $validations['warnings'][] = get_string('validationbeforecoursestart', 'local_coursedateshiftpro', (object)[
                    'item' => $item['label'],
                    'field' => $item['fieldlabel'],
                ]);
            }

            if ($futurecourseend > 0 && $item['new'] > $futurecourseend) {
                $validations['warnings'][] = get_string('validationaftercourseend', 'local_coursedateshiftpro', (object)[
                    'item' => $item['label'],
                    'field' => $item['fieldlabel'],
                ]);
            }
        }
    }

    /**
     * Adds blocking validations for unresolved field sequence conflicts using effective preview values.
     *
     * @param array $items
     * @param array $validations
     * @return void
     */
    private static function collect_effective_sequence_validations(array $items, array &$validations): void {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[self::get_record_signature($item)][$item['field']] = $item;
        }

        foreach ($grouped as $groupitems) {
            foreach (self::FIELD_SEQUENCE_RULES as $startfield => $endfield) {
                $startitem = $groupitems[$startfield] ?? null;
                $enditem = $groupitems[$endfield] ?? null;
                if (!$startitem || !$enditem) {
                    continue;
                }

                $startvalue = (int)($startitem['new'] ?? 0);
                $endvalue = (int)($enditem['new'] ?? 0);
                if ($startvalue <= 0 || $endvalue <= 0 || $startvalue < $endvalue) {
                    continue;
                }

                $validations['errors'][] = get_string('validationsequenceerror', 'local_coursedateshiftpro', (object)[
                    'item' => (string)($startitem['label'] ?? ''),
                    'startfield' => self::humanise_field($startfield),
                    'endfield' => self::humanise_field($endfield),
                ]);
            }
        }
    }

    /**
     * Adds non-blocking advisories about schedule concentration and long gaps.
     *
     * @param array $items
     * @param array $validations
     * @return void
     */
    private static function collect_schedule_balance_advisories(array $items, array &$validations): void {
        $daycounts = [];
        $weekloads = [];
        $timestamps = [];

        foreach ($items as $item) {
            if (empty($item['new'])) {
                continue;
            }

            $timestamp = (int)$item['new'];
            $daykey = gmdate('Y-m-d', $timestamp);
            $weekkey = date('o-\WW', $timestamp);
            $weight = self::get_item_workload_weight($item);

            $daycounts[$daykey] = ($daycounts[$daykey] ?? 0) + 1;
            $weekloads[$weekkey] = ($weekloads[$weekkey] ?? 0) + $weight;
            $timestamps[] = $timestamp;
        }

        foreach ($daycounts as $daykey => $count) {
            if ($count >= 4) {
                $validations['warnings'][] = get_string('validationdenseactivityday', 'local_coursedateshiftpro', (object)[
                    'day' => $daykey,
                    'count' => $count,
                ]);
            }
        }

        foreach ($weekloads as $weekkey => $count) {
            if ($count >= 6.5) {
                $validations['warnings'][] = get_string('validationdenseactivityweek', 'local_coursedateshiftpro', (object)[
                    'week' => $weekkey,
                    'count' => round($count, 1),
                ]);
            }
        }

        sort($timestamps);
        for ($i = 1; $i < count($timestamps); $i++) {
            $gapdays = (int)floor(($timestamps[$i] - $timestamps[$i - 1]) / DAYSECS);
            if ($gapdays >= 14) {
                $validations['info'][] = get_string('validationlonggapwindow', 'local_coursedateshiftpro', (object)[
                    'days' => $gapdays,
                    'start' => userdate($timestamps[$i - 1]),
                    'end' => userdate($timestamps[$i]),
                ]);
                break;
            }
        }
    }

    /**
     * Creates proposed items for missing expected dates on common activities.
     *
     * @param string $modname
     * @param string $table
     * @param int $recordid
     * @param string $cmname
     * @param array $detectedfields
     * @param int $newstartdate
     * @param int $delta
     * @param array $items
     * @param array $validations
     * @return void
     */
    private static function collect_missing_date_proposals(
        string $modname,
        string $table,
        int $recordid,
        string $cmname,
        int $courseorder,
        string $itemurl,
        array $detectedfields,
        int $newstartdate,
        int $delta,
        array &$items,
        array &$validations
    ): void {
        if (empty(self::EXPECTED_ACTIVITY_DATE_FIELDS[$modname])) {
            return;
        }

        $expectedfields = self::EXPECTED_ACTIVITY_DATE_FIELDS[$modname];
        $missingfields = array_values(array_diff($expectedfields, array_unique($detectedfields)));
        if (empty($missingfields)) {
            return;
        }

        foreach ($missingfields as $index => $field) {
            $suggesteddate = self::suggest_missing_field_date($field, $newstartdate, $courseorder, $index);
            self::add_item($items, [
                'category' => 'activities',
                'table' => $table,
                'recordid' => $recordid,
                'field' => $field,
                'courseorder' => $courseorder,
                'itemurl' => $itemurl,
                'label' => $cmname,
                'fieldlabel' => self::humanise_field_for_module($field, $modname),
                'current' => 0,
                'new' => $suggesteddate,
                'autoproposedonly' => 1,
                'suggestionreason' => 'missingdate',
            ]);
        }

        $validations['info'][] = get_string('validationmissingdatesproposed', 'local_coursedateshiftpro', (object)[
            'item' => $cmname,
            'count' => count($missingfields),
        ]);
    }

    /**
     * Suggests a reasonable timestamp for a missing field.
     *
     * @param string $field
     * @param int $newstartdate
     * @param int $courseorder
     * @param int $index
     * @return int
     */
    private static function suggest_missing_field_date(string $field, int $newstartdate, int $courseorder, int $index): int {
        $offsets = [
            'allowsubmissionsfromdate' => 0,
            'timeopen' => 0,
            'availablefrom' => 0,
            'submissionstart' => 0,
            'duedate' => 7,
            'timeclose' => 7,
            'availableuntil' => 7,
            'submissionend' => 7,
            'cutoffdate' => 9,
            'gradingduedate' => 10,
        ];

        $offsetdays = $offsets[$field] ?? 7;
        $courseanchor = max(0, (int)floor(max(0, $courseorder - 1) * 1.5));
        $base = $newstartdate + (($courseanchor + $offsetdays + $index) * DAYSECS);
        return self::move_to_business_day($base);
    }

    /**
     * Builds an optional auto-scheduling proposal to reduce dense days and weeks.
     *
     * @param array $items
     * @param int $newstartdate
     * @param int $futurecourseend
     * @return array
     */
    private static function build_autoschedule_plan(array $items, int $newstartdate, int $futurecourseend = 0): array {
        $adjusteditems = $items;
        $adjustments = [];
        $daycounts = [];
        $weekcounts = [];

        uasort($adjusteditems, static function(array $a, array $b): int {
            return (int)$a['new'] <=> (int)$b['new'];
        });

        foreach ($adjusteditems as $key => $item) {
            $timestamp = (int)($item['new'] ?? 0);
            if ($timestamp <= 0 || !self::is_autoschedule_eligible($item)) {
                continue;
            }

            [$minbound, $maxbound] = self::get_autoschedule_bounds($item, $adjusteditems, $newstartdate, $futurecourseend);
            $candidate = self::normalise_candidate_within_bounds($timestamp, $minbound, $maxbound);
            if ($candidate <= 0) {
                $adjusteditems[$key]['baselinenew'] = $timestamp;
                $adjusteditems[$key]['recommendednew'] = $timestamp;
                $adjusteditems[$key]['autoscheduled'] = 0;
                continue;
            }

            while (true) {
                $daykey = gmdate('Y-m-d', $candidate);
                $weekkey = date('o-\WW', $candidate);
                $dayload = $daycounts[$daykey] ?? 0;
                $weekload = $weekcounts[$weekkey] ?? 0;

                if ($dayload < 3 && $weekload < 6) {
                    break;
                }

                $nextcandidate = self::normalise_candidate_within_bounds($candidate + DAYSECS, $minbound, $maxbound);
                if ($nextcandidate <= 0 || $nextcandidate === $candidate) {
                    break;
                }

                $candidate = $nextcandidate;
            }

            $newdaykey = gmdate('Y-m-d', $candidate);
            $newweekkey = date('o-\WW', $candidate);
            $daycounts[$newdaykey] = ($daycounts[$newdaykey] ?? 0) + 1;
            $weekcounts[$newweekkey] = ($weekcounts[$newweekkey] ?? 0) + 1;

            if ($candidate !== $timestamp) {
                $adjusteditems[$key]['baselinenew'] = $timestamp;
                $adjusteditems[$key]['recommendednew'] = $candidate;
                $adjusteditems[$key]['new'] = $candidate;
                $adjusteditems[$key]['autoscheduled'] = 1;
                $adjusteditems[$key]['suggestionreason'] = 'balance';
                $adjustments[] = get_string('autoscheduleitemadjusted', 'local_coursedateshiftpro', (object)[
                    'item' => $item['label'],
                    'field' => $item['fieldlabel'],
                    'date' => userdate($candidate),
                ]);
            } else {
                $adjusteditems[$key]['baselinenew'] = $timestamp;
                $adjusteditems[$key]['recommendednew'] = $timestamp;
                $adjusteditems[$key]['autoscheduled'] = 0;
            }
        }

        $adjusteditems = self::enforce_autoschedule_consistency($adjusteditems, $newstartdate, $futurecourseend);

        return [
            'available' => !empty($adjustments),
            'adjustmentcount' => count($adjustments),
            'summary' => array_slice($adjustments, 0, 6),
            'items' => $adjusteditems,
        ];
    }

    /**
     * Returns whether an item can be moved by the auto-scheduler.
     *
     * @param array $item
     * @return bool
     */
    private static function is_autoschedule_eligible(array $item): bool {
        return in_array($item['category'], ['activities', 'overrides', 'completionexpected'], true);
    }

    /**
     * Applies manual date overrides entered by the user in the preview table.
     *
     * @param array $items
     * @param array $manualdates
     * @return array
     */
    private static function apply_manual_date_overrides(
        array $items,
        array $manualdates,
        int $newstartdate,
        int $futurecourseend
    ): array {
        foreach ($items as $key => $item) {
            if (empty($manualdates[$key])) {
                continue;
            }

            $items[$key]['new'] = (int)$manualdates[$key];
            $items[$key]['autoscheduled'] = 1;
        }

        $items = self::apply_linked_date_suggestions($items, $manualdates, $newstartdate, $futurecourseend);

        return self::enforce_autoschedule_consistency($items, $newstartdate, $futurecourseend, true);
    }

    /**
     * Applies linked date suggestions when one anchor field is edited manually.
     *
     * @param array $items
     * @param array $manualdates
     * @param int $newstartdate
     * @param int $futurecourseend
     * @return array
     */
    private static function apply_linked_date_suggestions(
        array $items,
        array $manualdates,
        int $newstartdate,
        int $futurecourseend
    ): array {
        $grouped = [];
        foreach ($items as $key => $item) {
            $grouped[self::get_record_signature($item)][$key] = $item;
        }

        foreach ($grouped as $groupitems) {
            foreach (self::LINKED_DATE_RULES as $anchorfield => $relatedfields) {
                $anchorkey = null;
                foreach ($groupitems as $key => $groupitem) {
                    if (($groupitem['field'] ?? '') === $anchorfield) {
                        $anchorkey = $key;
                        break;
                    }
                }

                if ($anchorkey === null || empty($manualdates[$anchorkey])) {
                    continue;
                }

                $anchorts = (int)($items[$anchorkey]['new'] ?? 0);
                if ($anchorts <= 0) {
                    continue;
                }

                foreach ($relatedfields as $targetfield => $offsetdays) {
                    $targetkey = null;
                    foreach ($groupitems as $key => $groupitem) {
                        if (($groupitem['field'] ?? '') === $targetfield) {
                            $targetkey = $key;
                            break;
                        }
                    }

                    if ($targetkey === null || !empty($manualdates[$targetkey])) {
                        continue;
                    }

                    [$minbound, $maxbound] = self::get_autoschedule_bounds($items[$targetkey], $items, $newstartdate, $futurecourseend);
                    $candidate = $anchorts + ($offsetdays * DAYSECS);
                    $bounded = self::normalise_candidate_within_bounds($candidate, $minbound, $maxbound, true);
                    if ($bounded <= 0) {
                        continue;
                    }

                    $items[$targetkey]['new'] = $bounded;
                    $items[$targetkey]['recommendednew'] = $bounded;
                    $items[$targetkey]['autoscheduled'] = 1;
                    $items[$targetkey]['suggestionreason'] = 'linked:' . $anchorfield;
                }
            }
        }

        return $items;
    }

    /**
     * Returns the allowed bounds for one auto-scheduled item.
     *
     * @param array $item
     * @param array $adjusteditems
     * @param int $newstartdate
     * @param int $futurecourseend
     * @return array
     */
    private static function get_autoschedule_bounds(array $item, array $adjusteditems, int $newstartdate, int $futurecourseend): array {
        $minbound = max(1, $newstartdate);
        $maxbound = $futurecourseend > 0 ? $futurecourseend : PHP_INT_MAX;
        $recordsignature = self::get_record_signature($item);

        foreach (self::FIELD_SEQUENCE_RULES as $startfield => $endfield) {
            if (($item['field'] ?? '') === $startfield) {
                $paired = self::get_related_item_by_field($adjusteditems, $recordsignature, $endfield);
                if ($paired) {
                    $pairedts = (int)($paired['new'] ?? 0);
                    if ($pairedts > 0) {
                        $maxbound = min($maxbound, $pairedts - HOURSECS);
                    }
                }
            }

            if (($item['field'] ?? '') === $endfield) {
                $paired = self::get_related_item_by_field($adjusteditems, $recordsignature, $startfield);
                if ($paired) {
                    $pairedts = (int)($paired['new'] ?? 0);
                    if ($pairedts > 0) {
                        $minbound = max($minbound, $pairedts + HOURSECS);
                    }
                }
            }
        }

        return [$minbound, $maxbound];
    }

    /**
     * Returns a related item from the same record by field name.
     *
     * @param array $items
     * @param string $recordsignature
     * @param string $field
     * @return array|null
     */
    private static function get_related_item_by_field(array $items, string $recordsignature, string $field): ?array {
        foreach ($items as $candidate) {
            if (self::get_record_signature($candidate) !== $recordsignature) {
                continue;
            }

            if (($candidate['field'] ?? '') === $field) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Enforces sequence and course-bound consistency after auto-scheduling.
     *
     * @param array $items
     * @param int $newstartdate
     * @param int $futurecourseend
     * @return array
     */
    private static function enforce_autoschedule_consistency(
        array $items,
        int $newstartdate,
        int $futurecourseend,
        bool $preservemanualdates = false
    ): array {
        $grouped = [];
        foreach ($items as $key => $item) {
            $grouped[self::get_record_signature($item)][$key] = $item;
        }

        foreach ($grouped as $groupitems) {
            foreach (self::FIELD_SEQUENCE_RULES as $startfield => $endfield) {
                $startkey = null;
                $endkey = null;
                foreach ($groupitems as $key => $groupitem) {
                    if (($groupitem['field'] ?? '') === $startfield) {
                        $startkey = $key;
                    } else if (($groupitem['field'] ?? '') === $endfield) {
                        $endkey = $key;
                    }
                }

                if ($startkey === null || $endkey === null) {
                    continue;
                }

                $startts = (int)($items[$startkey]['new'] ?? 0);
                $endts = (int)($items[$endkey]['new'] ?? 0);
                if ($startts <= 0 || $endts <= 0 || $startts < $endts) {
                    continue;
                }

                $startmax = $endts - HOURSECS;
                $resolvedstart = self::normalise_candidate_within_bounds(
                    $startmax,
                    $newstartdate,
                    $startmax,
                    $preservemanualdates
                );
                if ($resolvedstart > 0 && $resolvedstart < $endts) {
                    $items[$startkey]['new'] = $resolvedstart;
                    $items[$startkey]['recommendednew'] = $resolvedstart;
                    $items[$startkey]['autoscheduled'] = 1;
                    $startts = $resolvedstart;
                }

                if ($startts >= $endts) {
                    $endmin = $startts + HOURSECS;
                    $resolvedend = self::normalise_candidate_within_bounds(
                        $endmin,
                        $endmin,
                        $futurecourseend > 0 ? $futurecourseend : PHP_INT_MAX,
                        $preservemanualdates
                    );
                    if ($resolvedend > 0 && $resolvedend > $startts) {
                        $items[$endkey]['new'] = $resolvedend;
                        $items[$endkey]['recommendednew'] = $resolvedend;
                        $items[$endkey]['autoscheduled'] = 1;
                    }
                }
            }

            foreach ($groupitems as $key => $groupitem) {
                $timestamp = (int)($items[$key]['new'] ?? 0);
                if ($timestamp <= 0) {
                    continue;
                }

                $maxbound = $futurecourseend > 0 ? $futurecourseend : PHP_INT_MAX;
                $bounded = self::normalise_candidate_within_bounds(
                    $timestamp,
                    $newstartdate,
                    $maxbound,
                    $preservemanualdates
                );
                if ($bounded > 0) {
                    $items[$key]['new'] = $bounded;
                    $items[$key]['recommendednew'] = $bounded;
                }
            }
        }

        return $items;
    }

    /**
     * Normalises one candidate date so it stays within bounds and on a business day.
     *
     * @param int $candidate
     * @param int $minbound
     * @param int $maxbound
     * @return int
     */
    private static function normalise_candidate_within_bounds(
        int $candidate,
        int $minbound,
        int $maxbound,
        bool $preserveweekends = false
    ): int {
        if ($maxbound > 0 && $minbound > $maxbound) {
            return 0;
        }

        $candidate = max($candidate, $minbound);
        if ($maxbound > 0) {
            $candidate = min($candidate, $maxbound);
        }

        if ($preserveweekends) {
            if ($candidate < $minbound || ($maxbound > 0 && $candidate > $maxbound)) {
                return 0;
            }

            return $candidate;
        }

        $businesscandidate = self::move_to_business_day($candidate);
        if ($maxbound > 0 && $businesscandidate > $maxbound) {
            $businesscandidate = self::move_to_previous_business_day($maxbound);
        }

        if ($businesscandidate < $minbound || ($maxbound > 0 && $businesscandidate > $maxbound)) {
            return 0;
        }

        return $businesscandidate;
    }

    /**
     * Returns one course module order map based on section sequence.
     *
     * @param int $courseid
     * @return array
     */
    private static function get_course_module_order_map(int $courseid): array {
        global $DB;

        $map = [];
        $index = 1;
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC', 'id, sequence');
        foreach ($sections as $section) {
            $sequence = trim((string)($section->sequence ?? ''));
            if ($sequence === '') {
                continue;
            }

            foreach (explode(',', $sequence) as $cmid) {
                $cmid = (int)$cmid;
                if ($cmid <= 0 || isset($map[$cmid])) {
                    continue;
                }

                $map[$cmid] = $index++;
            }
        }

        return $map;
    }

    /**
     * Returns the activity URL for a course module when available.
     *
     * @param \course_modinfo $modinfo
     * @param int $cmid
     * @return string
     */
    private static function get_cm_url(\course_modinfo $modinfo, int $cmid): string {
        $cm = $modinfo->get_cm($cmid);
        if ($cm && !empty($cm->url)) {
            return $cm->url->out(false);
        }

        return '';
    }

    /**
     * Returns the main course URL.
     *
     * @param int $courseid
     * @return string
     */
    private static function get_course_url(int $courseid): string {
        return (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
    }

    /**
     * Builds row-level advisories so the preview table can replace warning/info lists.
     *
     * @param \stdClass $course
     * @param int $newstartdate
     * @param array $items
     * @param array $effectiveitems
     * @return array
     */
    private static function build_item_advisories(\stdClass $course, int $newstartdate, array $items, array $effectiveitems): array {
        $advisories = [];
        $futurecourseend = self::get_effective_course_end_date($course, $effectiveitems, $newstartdate);
        $effectivelookup = [];
        $effectivebyrecord = [];
        foreach ($effectiveitems as $effectiveitem) {
            $effectivelookup[$effectiveitem['key']] = $effectiveitem;
            $recordsignature = self::get_record_signature($effectiveitem);
            $effectivebyrecord[$recordsignature][$effectiveitem['field']] = $effectiveitem;
        }

        $daycounts = [];
        $weekloads = [];
        foreach ($effectiveitems as $item) {
            $timestamp = (int)($item['new'] ?? 0);
            if ($timestamp <= 0) {
                continue;
            }

            $daycounts[gmdate('Y-m-d', $timestamp)] = ($daycounts[gmdate('Y-m-d', $timestamp)] ?? 0) + 1;
            $weekkey = date('o-\WW', $timestamp);
            $weekloads[$weekkey] = ($weekloads[$weekkey] ?? 0) + self::get_item_workload_weight($item);
        }

        foreach ($items as $item) {
            $effectiveitem = $effectivelookup[$item['key']] ?? $item;
            $rowmessages = [];

            if (!empty($item['autoproposedonly']) || (int)($item['current'] ?? 0) <= 0) {
                $rowmessages[] = [
                    'level' => 'info',
                    'text' => get_string('rowadvisorymissingdate', 'local_coursedateshiftpro'),
                ];
            }

            if ($item['category'] === 'courseenddate' && $futurecourseend > 0 && $futurecourseend <= $newstartdate) {
                $rowmessages[] = [
                    'level' => 'error',
                    'text' => get_string('rowadvisorycourseendinvalid', 'local_coursedateshiftpro', (object)[
                        'newend' => userdate($futurecourseend),
                        'newstart' => userdate($newstartdate),
                    ]),
                ];
            }

            if ((int)($effectiveitem['new'] ?? 0) < $newstartdate) {
                $rowmessages[] = [
                    'level' => 'warning',
                    'text' => get_string('rowadvisorybeforecoursestart', 'local_coursedateshiftpro', (object)[
                        'current' => userdate((int)($effectiveitem['new'] ?? 0)),
                        'newstart' => userdate($newstartdate),
                    ]),
                ];
            }

            if ($futurecourseend > 0 && (int)($effectiveitem['new'] ?? 0) > $futurecourseend) {
                $rowmessages[] = [
                    'level' => 'warning',
                    'text' => get_string('rowadvisoryaftercourseend', 'local_coursedateshiftpro', (object)[
                        'current' => userdate((int)($effectiveitem['new'] ?? 0)),
                        'newend' => userdate($futurecourseend),
                    ]),
                ];
            }

            $daykey = gmdate('Y-m-d', (int)($effectiveitem['new'] ?? 0));
            if (!empty($daycounts[$daykey]) && $daycounts[$daykey] >= 4) {
                $rowmessages[] = [
                    'level' => 'warning',
                    'text' => get_string('rowadvisorydenseday', 'local_coursedateshiftpro', $daykey),
                ];
            }

            $weekkey = date('o-\WW', (int)($effectiveitem['new'] ?? 0));
            if (!empty($weekloads[$weekkey]) && $weekloads[$weekkey] >= 6.5) {
                $rowmessages[] = [
                    'level' => 'notice',
                    'text' => get_string('rowadvisorydenseweek', 'local_coursedateshiftpro', $weekkey),
                ];
            }

            $recordsignature = self::get_record_signature($item);
            foreach (self::FIELD_SEQUENCE_RULES as $startfield => $endfield) {
                if ($item['field'] !== $startfield && $item['field'] !== $endfield) {
                    continue;
                }

                $startitem = $effectivebyrecord[$recordsignature][$startfield] ?? null;
                $enditem = $effectivebyrecord[$recordsignature][$endfield] ?? null;
                if (!$startitem || !$enditem) {
                    continue;
                }

                $startts = (int)($startitem['new'] ?? 0);
                $endts = (int)($enditem['new'] ?? 0);
                if ($startts > 0 && $endts > 0 && $startts >= $endts) {
                    $rowmessages[] = [
                        'level' => 'error',
                        'text' => get_string('rowadvisorysequenceerror', 'local_coursedateshiftpro', (object)[
                            'startfield' => self::humanise_field($startfield),
                            'endfield' => self::humanise_field($endfield),
                            'startdate' => userdate($startts),
                            'enddate' => userdate($endts),
                        ]),
                    ];
                    break;
                }
            }

            $advisories[$item['key']] = $rowmessages;
        }

        return $advisories;
    }

    /**
     * Returns the effective course end date from preview items when available.
     *
     * @param \stdClass $course
     * @param array $items
     * @param int $newstartdate
     * @return int
     */
    private static function get_effective_course_end_date(\stdClass $course, array $items, int $newstartdate): int {
        foreach ($items as $item) {
            if (($item['category'] ?? '') === 'courseenddate' && ($item['field'] ?? '') === 'enddate' && !empty($item['new'])) {
                return (int)$item['new'];
            }
        }

        return !empty($course->enddate) ? (int)$course->enddate + ($newstartdate - (int)$course->startdate) : 0;
    }

    /**
     * Returns the relative workload weight for one item based on its field.
     *
     * @param array $item
     * @return float
     */
    private static function get_item_workload_weight(array $item): float {
        $field = (string)($item['field'] ?? '');
        return self::FIELD_WORKLOAD_WEIGHTS[$field] ?? 0.75;
    }

    /**
     * Returns a stable record signature to group related fields.
     *
     * @param array $item
     * @return string
     */
    private static function get_record_signature(array $item): string {
        return implode(':', [
            (string)($item['category'] ?? ''),
            (string)($item['table'] ?? ''),
            (int)($item['recordid'] ?? 0),
        ]);
    }

    /**
     * Moves timestamps that fall on weekends to the next Monday.
     *
     * @param int $timestamp
     * @return int
     */
    private static function move_to_business_day(int $timestamp): int {
        $weekday = (int)gmdate('N', $timestamp);
        if ($weekday === 6) {
            return $timestamp + (2 * DAYSECS);
        }

        if ($weekday === 7) {
            return $timestamp + DAYSECS;
        }

        return $timestamp;
    }

    /**
     * Moves timestamps that fall on weekends to the previous Friday.
     *
     * @param int $timestamp
     * @return int
     */
    private static function move_to_previous_business_day(int $timestamp): int {
        $weekday = (int)gmdate('N', $timestamp);
        if ($weekday === 6) {
            return $timestamp - DAYSECS;
        }

        if ($weekday === 7) {
            return $timestamp - (2 * DAYSECS);
        }

        return $timestamp;
    }

    /**
     * Returns a translated history status label.
     *
     * @param string $action
     * @param string $status
     * @return string
     */
    private static function get_history_status_label(string $action, string $status): string {
        if ($action === 'rollback') {
            return get_string('historystatusrollback', 'local_coursedateshiftpro');
        }

        if ($status === 'rolledback') {
            return get_string('historystatusrolledback', 'local_coursedateshiftpro');
        }

        return get_string('historystatusapplied', 'local_coursedateshiftpro');
    }
}
