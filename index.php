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
 * Main page for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursedateshiftpro\form\shift_form;
use local_coursedateshiftpro\local\date_shifter;
use local_coursedateshiftpro\local\preview_renderer;

require_login();
require_capability('local/coursedateshiftpro:manage', context_system::instance());

// phpcs:disable moodle.Files.LineLength

$courseid = optional_param('courseid', 0, PARAM_INT);
$newstartdateraw = optional_param_array('newstartdate', [], PARAM_INT);
$newstartts = optional_param('newstartts', 0, PARAM_INT);
$rawfilters = optional_param_array('filters', [], PARAM_RAW_TRIMMED);
$filtersinitialised = optional_param('filtersinitialised', 0, PARAM_BOOL);
$applychanges = optional_param('applychanges', 0, PARAM_BOOL);
$undochanges = optional_param('undochanges', 0, PARAM_BOOL);
$historyrollbackid = optional_param('historyrollbackid', 0, PARAM_INT);
$historyviewid = optional_param('historyviewid', 0, PARAM_INT);
$continueafter = optional_param('continueafter', 0, PARAM_BOOL);
$confirmapply = optional_param('confirmapply', 0, PARAM_BOOL);
$acknowledgealerts = optional_param('acknowledgealerts', 0, PARAM_BOOL);
$useautoschedule = optional_param('useautoschedule', 0, PARAM_BOOL);
$refreshpreview = optional_param('refreshpreview', 0, PARAM_BOOL);
$manualdatesraw = optional_param_array('manualdates', [], PARAM_RAW_TRIMMED);
$selecteditemkeys = optional_param_array('selectedkeys', [], PARAM_ALPHANUMEXT);
$previewrequested = optional_param('previewdates', '', PARAM_TEXT) !== '' || !empty($refreshpreview);
$loadcoursepressed = optional_param('loadcourse', '', PARAM_TEXT) !== '';
$manualdates = local_coursedateshiftpro_parse_manual_dates($manualdatesraw);

$filters = date_shifter::normalise_filters($rawfilters, empty($filtersinitialised));
$newstartdate = (int)$newstartts;
if (empty($newstartdate) && !empty($newstartdateraw)) {
    $newstartdate = make_timestamp(
        (int)($newstartdateraw['year'] ?? 0),
        (int)($newstartdateraw['month'] ?? 0),
        (int)($newstartdateraw['day'] ?? 0),
        (int)($newstartdateraw['hour'] ?? 0),
        (int)($newstartdateraw['minute'] ?? 0)
    );
}

admin_externalpage_setup('local_coursedateshiftpro');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/coursedateshiftpro/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('pageheading', 'local_coursedateshiftpro'));
$PAGE->set_heading(get_string('pageheading', 'local_coursedateshiftpro'));
$PAGE->requires->css(new moodle_url('/local/coursedateshiftpro/styles.css'));
$PAGE->requires->js_call_amd('local_coursedateshiftpro/preview', 'init');

$courseoptions = date_shifter::get_course_options();
$selectedcourse = null;
$courseloaded = false;
$loadedhistory = null;
$showsetup = $courseid > 0 || $loadcoursepressed || $previewrequested || $applychanges || $historyviewid > 0;
if ($continueafter) {
    unset($SESSION->local_coursedateshiftpro_lastapply);
    redirect(new moodle_url('/local/coursedateshiftpro/index.php'));
}

$postapplystate = false;
if (!empty($SESSION->local_coursedateshiftpro_lastapply)) {
    $lastrun = $SESSION->local_coursedateshiftpro_lastapply;
    if (
        !empty($lastrun['courseid']) &&
        (int)$lastrun['courseid'] === $courseid &&
        empty($loadcoursepressed) &&
        empty($historyviewid) &&
        empty($previewrequested)
    ) {
        $postapplystate = true;
    }
}

if ($undochanges && !empty($SESSION->local_coursedateshiftpro_lastapply)) {
    require_sesskey();
    $lastrun = $SESSION->local_coursedateshiftpro_lastapply;
    $rollbacksummary = date_shifter::rollback_execution((int)$lastrun['executionid']);
    unset($SESSION->local_coursedateshiftpro_lastapply);
    \core\notification::success(get_string('changesundone', 'local_coursedateshiftpro'));
    redirect(new moodle_url('/local/coursedateshiftpro/index.php', ['courseid' => (int)$lastrun['courseid']]));
}

if ($historyrollbackid > 0) {
    require_sesskey();
    $historysummary = date_shifter::rollback_execution($historyrollbackid);
    if (
        !empty($SESSION->local_coursedateshiftpro_lastapply['executionid']) &&
        (int)$SESSION->local_coursedateshiftpro_lastapply['executionid'] === $historyrollbackid
    ) {
        unset($SESSION->local_coursedateshiftpro_lastapply);
    }
    \core\notification::success(get_string('changesundonehistory', 'local_coursedateshiftpro'));
    redirect(new moodle_url('/local/coursedateshiftpro/index.php', ['courseid' => (int)$historysummary['courseid']]));
}

if ($historyviewid > 0) {
    $loadedhistory = date_shifter::get_history_execution($historyviewid);
    $courseid = (int)$loadedhistory->courseid;
    $newstartdate = (int)$loadedhistory->newstartdate;
    $filters = date_shifter::normalise_filters((array)$loadedhistory->filters, false);
    $selecteditemkeys = (array)$loadedhistory->selectedkeys;
    $useautoschedule = !empty($loadedhistory->summary['autoscheduleapplied']) ? 1 : 0;
    \core\notification::info(get_string('historyloadednotice', 'local_coursedateshiftpro', 'CDSP-' . $historyviewid));
}

if ($showsetup && $courseid > 0) {
    $selectedcourse = date_shifter::get_course($courseid);
    $courseloaded = !empty($selectedcourse);
    if ($selectedcourse && empty($newstartdate)) {
        $newstartdate = (int)$selectedcourse->startdate;
    }
}

$mform = new shift_form(null, [
    'courseoptions' => $courseoptions,
    'selectedcourse' => $selectedcourse,
    'newstartdate' => $newstartdate,
    'filters' => $filters,
    'courseloaded' => $courseloaded,
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/coursedateshiftpro/index.php'));
}

if ($loadcoursepressed) {
    unset($SESSION->local_coursedateshiftpro_lastapply);
    $postapplystate = false;
    if ($courseid > 0) {
        redirect(new moodle_url('/local/coursedateshiftpro/index.php', ['courseid' => $courseid]));
    }

    \core\notification::error(get_string('errorcoursemissing', 'local_coursedateshiftpro'));
}

$preview = [];
$summary = null;

if (($data = $mform->get_data()) && !empty($data->previewdates)) {
    $courseid = (int)$data->courseid;
    $selectedcourse = date_shifter::get_course($courseid);
    $newstartdate = (int)$data->newstartdate;
    $filters = date_shifter::normalise_filters((array)($data->filters ?? []), false);
    $preview = date_shifter::build_preview($courseid, $newstartdate, $filters, false, $manualdates);
    $selecteditemkeys = array_keys($preview['items']);
    $mform = new shift_form(null, [
        'courseoptions' => $courseoptions,
        'selectedcourse' => $selectedcourse,
        'newstartdate' => $newstartdate,
        'filters' => $filters,
        'courseloaded' => !empty($selectedcourse),
    ]);
} else if ($selectedcourse && !empty($newstartdate) && ($applychanges || $previewrequested || $historyviewid > 0)) {
    $preview = date_shifter::build_preview(
        (int)$selectedcourse->id,
        $newstartdate,
        $filters,
        !empty($useautoschedule),
        $manualdates
    );
    if (empty($selecteditemkeys)) {
        $selecteditemkeys = array_keys($preview['items']);
    }
}

if ($applychanges && $selectedcourse) {
    require_sesskey();
    $preview = date_shifter::build_preview(
        (int)$selectedcourse->id,
        $newstartdate,
        $filters,
        !empty($useautoschedule),
        $manualdates
    );
    $hasadvisories = !empty($preview['validations']['warnings']) || !empty($preview['validations']['info']);

    if (!empty($preview['validations']['errors'])) {
        \core\notification::error(get_string('errorvalidationserrors', 'local_coursedateshiftpro'));
    } else if ($hasadvisories && empty($acknowledgealerts)) {
        \core\notification::error(get_string('erroralertsackrequired', 'local_coursedateshiftpro'));
    } else if (empty($confirmapply)) {
        \core\notification::error(get_string('errorconfirmapply', 'local_coursedateshiftpro'));
    } else if (empty($selecteditemkeys)) {
        \core\notification::error(get_string('errornodateselected', 'local_coursedateshiftpro'));
    } else {
        $summary = date_shifter::apply_selected_changes(
            $courseid,
            $newstartdate,
            $filters,
            $selecteditemkeys,
            !empty($useautoschedule),
            $manualdates
        );

        if ($summary['fields'] > 0) {
            $executionid = date_shifter::store_execution_history($courseid, $preview, $filters, $selecteditemkeys, $summary);
            \core\notification::success(get_string('courseshifted', 'local_coursedateshiftpro'));
            $SESSION->local_coursedateshiftpro_lastapply = [
                'executionid' => $executionid,
                'courseid' => $courseid,
                'newstartdate' => $newstartdate,
                'filters' => $filters,
                'rollback' => $summary['rollback'],
                'summary' => $summary,
            ];
            $postapplystate = true;
        } else {
            \core\notification::info(get_string('nothingchanged', 'local_coursedateshiftpro'));
        }

        $selectedcourse = date_shifter::get_course($courseid);
        $preview = date_shifter::build_preview($courseid, $newstartdate, $filters, !empty($useautoschedule), $manualdates);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pageheading', 'local_coursedateshiftpro'));
echo $OUTPUT->notification(get_string('pagedescription', 'local_coursedateshiftpro'), \core\output\notification::NOTIFY_INFO);

if (empty($courseoptions)) {
    echo $OUTPUT->notification(get_string('nocourses', 'local_coursedateshiftpro'), \core\output\notification::NOTIFY_WARNING);
} else if (!$postapplystate) {
    $mform->display();
}

echo html_writer::start_div('', ['id' => 'cdsp-preview-root']);
if (!empty($preview)) {
    echo preview_renderer::render_preview($preview, $filters, $selecteditemkeys, $postapplystate);
}
echo html_writer::end_div();

if ($loadedhistory) {
    echo local_coursedateshiftpro_render_loaded_history_notice($loadedhistory);
}

if ($summary !== null) {
    $rows = [
        [get_string('summaryrecords', 'local_coursedateshiftpro'), $summary['records']],
        [get_string('summaryfields', 'local_coursedateshiftpro'), $summary['fields']],
        [get_string('summaryblocks', 'local_coursedateshiftpro'), s(implode(', ', $summary['blocks']))],
        [get_string('summarycourse', 'local_coursedateshiftpro'), $summary['course']],
        [get_string('summarysections', 'local_coursedateshiftpro'), $summary['sections']],
        [get_string('summaryactivities', 'local_coursedateshiftpro'), $summary['activities']],
        [get_string('summaryoverrides', 'local_coursedateshiftpro'), $summary['overrides']],
        [get_string('summaryavailability', 'local_coursedateshiftpro'), $summary['availability']],
    ];

    $table = new html_table();
    $table->head = [get_string('results', 'local_coursedateshiftpro'), ''];
    $table->data = $rows;

    echo html_writer::table($table);
    if (!empty($SESSION->local_coursedateshiftpro_lastapply['executionid'])) {
        $pdfurl = new moodle_url('/local/coursedateshiftpro/export.php', [
            'executionid' => (int)$SESSION->local_coursedateshiftpro_lastapply['executionid'],
            'sesskey' => sesskey(),
        ]);
        echo \html_writer::link(
            $pdfurl,
            \html_writer::tag('span', '', ['class' => 'icon fa fa-file-pdf-o fa-fw', 'aria-hidden' => 'true']) .
            s(get_string('historyexportpdf', 'local_coursedateshiftpro')),
            ['class' => 'btn btn-secondary']
        );
    }
    if (!empty($summary['skipped'])) {
        echo \html_writer::tag('h4', get_string('summaryskipped', 'local_coursedateshiftpro'));
        echo \html_writer::alist($summary['skipped']);
    }
}

$history = date_shifter::get_history($courseid, 12);
echo local_coursedateshiftpro_render_history($history, $courseid);

echo $OUTPUT->footer();

/**
 * Renders the execution history block.
 *
 * @param array $history
 * @param int $courseid
 * @return string
 */
function local_coursedateshiftpro_render_history(array $history, int $courseid): string {
    $title = get_string('historytitle', 'local_coursedateshiftpro');
    $stats = local_coursedateshiftpro_get_history_stats($history);

    $html = \html_writer::start_div('', [
        'class' => 'coursedateshiftpro-history',
    ]);
    $html .= \html_writer::tag('h3', s($title), [
        'class' => 'coursedateshiftpro-history__title',
    ]);
    $html .= \html_writer::start_div('coursedateshiftpro-history__body');
    $html .= \html_writer::tag('p', s(get_string('historydescription', 'local_coursedateshiftpro')), [
        'class' => 'coursedateshiftpro-history__description',
    ]);

    $html .= \html_writer::start_div('coursedateshiftpro-history__metrics');
    foreach ($stats as $label => $value) {
        $html .= \html_writer::start_div('coursedateshiftpro-history__metric');
        $html .= \html_writer::tag('div', s($label), ['class' => 'coursedateshiftpro-history__metric-label']);
        $html .= \html_writer::tag('div', s((string)$value), ['class' => 'coursedateshiftpro-history__metric-value']);
        $html .= \html_writer::end_div();
    }
    $html .= \html_writer::end_div();

    if (empty($history)) {
        $html .= \html_writer::tag('div', s(get_string('historyempty', 'local_coursedateshiftpro')), [
            'class' => 'coursedateshiftpro-history__empty',
        ]);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();
        return $html;
    }

    $table = new \html_table();
    $table->head = [
        get_string('historyid', 'local_coursedateshiftpro'),
        get_string('historydate', 'local_coursedateshiftpro'),
        get_string('historycourse', 'local_coursedateshiftpro'),
        get_string('historystatus', 'local_coursedateshiftpro'),
        get_string('historyimpact', 'local_coursedateshiftpro'),
        '',
    ];
    $table->attributes['class'] = 'generaltable table-sm coursedateshiftpro-history__table';
    $table->data = [];

    foreach ($history as $entry) {
        $summary = $entry->summary ?? [];
        $summarytext = get_string('historysummarytext', 'local_coursedateshiftpro', (object)[
            'records' => (int)($summary['records'] ?? 0),
            'fields' => (int)($summary['fields'] ?? 0),
        ]);

        $impact = \html_writer::tag('div', s($summarytext), ['class' => 'coursedateshiftpro-history__impact-text']);
        $impact .= local_coursedateshiftpro_render_history_detail($entry);

        $actions = '';
        $actions .= \html_writer::start_tag('form', [
            'method' => 'get',
            'action' => (new moodle_url('/local/coursedateshiftpro/index.php'))->out(false),
            'class' => 'coursedateshiftpro-history__actionform',
        ]);
        $actions .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'historyviewid', 'value' => (int)$entry->id]);
        $actions .= \html_writer::tag(
            'button',
            \html_writer::tag('span', '', ['class' => 'icon fa fa-folder-open fa-fw', 'aria-hidden' => 'true']) .
            \html_writer::tag('span', s(get_string('historyload', 'local_coursedateshiftpro')), ['class' => 'accesshide']),
            [
            'type' => 'submit',
            'class' => 'btn btn-icon btn-outline-primary',
            'title' => get_string('historyload', 'local_coursedateshiftpro'),
            ]
        );
        $actions .= \html_writer::end_tag('form');

        $pdfurl = new moodle_url('/local/coursedateshiftpro/export.php', [
            'executionid' => (int)$entry->id,
            'sesskey' => sesskey(),
        ]);
        $actions .= \html_writer::link(
            $pdfurl,
            \html_writer::tag('span', '', ['class' => 'icon fa fa-file-pdf-o fa-fw', 'aria-hidden' => 'true']) .
            \html_writer::tag('span', s(get_string('historyexportpdf', 'local_coursedateshiftpro')), ['class' => 'accesshide']),
            [
                'class' => 'btn btn-icon btn-outline-secondary',
                'title' => get_string('historyexportpdf', 'local_coursedateshiftpro'),
            ]
        );

        if (!empty($entry->canrollback)) {
            $actions .= \html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new moodle_url('/local/coursedateshiftpro/index.php'))->out(false),
                'class' => 'coursedateshiftpro-history__actionform',
            ]);
            $actions .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $actions .= \html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'historyrollbackid',
                'value' => (int)$entry->id,
            ]);
            if ($courseid > 0) {
                $actions .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
            }
            $actions .= \html_writer::tag(
                'button',
                \html_writer::tag('span', '', ['class' => 'icon fa fa-rotate-left fa-fw', 'aria-hidden' => 'true']) .
                \html_writer::tag('span', s(get_string('historyrollback', 'local_coursedateshiftpro')), ['class' => 'accesshide']),
                [
                'type' => 'submit',
                'class' => 'btn btn-icon btn-outline-danger',
                'title' => get_string('historyrollback', 'local_coursedateshiftpro'),
                ]
            );
            $actions .= \html_writer::end_tag('form');
        }

        $table->data[] = [
            'CDSP-' . (int)$entry->id,
            userdate((int)$entry->timecreated) . '<br><span class="text-muted small">' . s($entry->actorname) . '</span>',
            s(format_string($entry->coursename)) . '<br><span class="text-muted small">' . s($entry->shortname) . '</span>',
            \html_writer::tag('span', s($entry->statuslabel), ['class' => 'badge badge-light coursedateshiftpro-history__status']),
            $impact,
            $actions,
        ];
    }

    $html .= \html_writer::table($table);
    $html .= \html_writer::end_div();
    $html .= \html_writer::end_div();

    return $html;
}

/**
 * Builds history dashboard metrics.
 *
 * @param array $history
 * @return array
 */
function local_coursedateshiftpro_get_history_stats(array $history): array {
    $applied = 0;
    $restored = 0;
    $courses = [];
    $lastupdate = get_string('historylastupdate_none', 'local_coursedateshiftpro');
    $lastupdatets = 0;

    foreach ($history as $entry) {
        if (($entry->action ?? '') === 'apply') {
            $applied++;
        } else if (($entry->action ?? '') === 'rollback') {
            $restored++;
        }
        if (!empty($entry->courseid)) {
            $courses[(int)$entry->courseid] = true;
        }

        $candidates = [
            (int)($entry->timemodified ?? 0),
            (int)($entry->timecreated ?? 0),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate > $lastupdatets) {
                $lastupdatets = $candidate;
            }
        }
    }

    if ($lastupdatets > 0) {
        $lastupdate = userdate($lastupdatets);
    }

    return [
        get_string('historymetrics_total', 'local_coursedateshiftpro') => count($history),
        get_string('historymetrics_applied', 'local_coursedateshiftpro') => $applied,
        get_string('historymetrics_restored', 'local_coursedateshiftpro') => $restored,
        get_string('historymetrics_courses', 'local_coursedateshiftpro') => count($courses),
        get_string('historymetrics_lastupdate', 'local_coursedateshiftpro') => $lastupdate,
    ];
}

/**
 * Renders expanded execution details for one history row.
 *
 * @param stdClass $entry
 * @return string
 */
function local_coursedateshiftpro_render_history_detail(\stdClass $entry): string {
    $summary = $entry->summary ?? [];
    $blocks = !empty($summary['blocks']) && is_array($summary['blocks']) ?
        implode(', ', $summary['blocks']) :
        get_string('notset', 'local_coursedateshiftpro');
    $weektext = get_string('notset', 'local_coursedateshiftpro');
    $weeks = [];
    if (!empty($summary['weeklyload']['weeks']) && is_array($summary['weeklyload']['weeks'])) {
        $weeks = array_column($summary['weeklyload']['weeks'], 'weeknumber');
    } else if (!empty($summary['report']['lines']) && is_array($summary['report']['lines'])) {
        $weeks = array_column($summary['report']['lines'], 'weeknumber');
    }

    $weeks = array_filter(array_map('intval', $weeks));
    if (!empty($weeks)) {
        $firstweek = min($weeks);
        $lastweek = max($weeks);
        if ($firstweek === $lastweek) {
            $weektext = get_string('historydetailweeksingle', 'local_coursedateshiftpro', $firstweek);
        } else {
            $weektext = get_string('historydetailweekrange', 'local_coursedateshiftpro', (object)[
                'first' => $firstweek,
                'last' => $lastweek,
            ]);
        }
    }

    $rows = [
        get_string('historydetaildates', 'local_coursedateshiftpro') =>
            userdate((int)$entry->oldstartdate) . ' -> ' . userdate((int)$entry->newstartdate),
        get_string('historydetailshift', 'local_coursedateshiftpro') => format_time(abs((int)$entry->delta)),
        get_string('historydetailblocks', 'local_coursedateshiftpro') => $blocks,
        get_string('historydetailrecords', 'local_coursedateshiftpro') => (int)($summary['records'] ?? 0),
        get_string('historydetailfields', 'local_coursedateshiftpro') => (int)($summary['fields'] ?? 0),
        get_string('historydetailweeks', 'local_coursedateshiftpro') => $weektext,
        get_string('historydetailautoschedule', 'local_coursedateshiftpro') =>
            !empty($summary['autoscheduleapplied']) ?
                get_string('historydetailautoschedule_yes', 'local_coursedateshiftpro') :
                get_string('historydetailautoschedule_no', 'local_coursedateshiftpro'),
    ];

    $html = \html_writer::start_tag('details', ['class' => 'coursedateshiftpro-history__details']);
    $html .= \html_writer::tag('summary', s(get_string('historydetailview', 'local_coursedateshiftpro')), [
        'class' => 'coursedateshiftpro-history__details-summary',
    ]);
    $html .= \html_writer::start_div('coursedateshiftpro-history__details-grid');
    foreach ($rows as $label => $value) {
        $html .= \html_writer::start_div('coursedateshiftpro-history__detail-card');
        $html .= \html_writer::tag('div', s($label), ['class' => 'coursedateshiftpro-history__detail-label']);
        $html .= \html_writer::tag('div', s((string)$value), ['class' => 'coursedateshiftpro-history__detail-value']);
        $html .= \html_writer::end_div();
    }
    $html .= \html_writer::end_div();
    $html .= \html_writer::end_tag('details');

    return $html;
}

/**
 * Renders a small notice for one loaded history execution.
 *
 * @param stdClass $historyentry
 * @return string
 */
function local_coursedateshiftpro_render_loaded_history_notice(\stdClass $historyentry): string {
    $details = [
        get_string('historyid', 'local_coursedateshiftpro') => 'CDSP-' . (int)$historyentry->id,
        get_string('historydate', 'local_coursedateshiftpro') => userdate((int)$historyentry->timecreated),
        get_string('historystatus', 'local_coursedateshiftpro') => s((string)$historyentry->status),
    ];

    $html = \html_writer::start_div('', [
        'class' => 'coursedateshiftpro-history__loaded',
    ]);
    $html .= \html_writer::tag(
        'h3',
        s(get_string('historyloadedtitle', 'local_coursedateshiftpro')),
        ['class' => 'coursedateshiftpro-history__loaded-title']
    );
    foreach ($details as $label => $value) {
        $html .= \html_writer::tag('div', s($label) . ': ' . $value, ['class' => 'coursedateshiftpro-history__loaded-row']);
    }
    $html .= \html_writer::end_div();

    return $html;
}

/**
 * Parses manual preview date overrides from datetime-local input values.
 *
 * @param array $manualdatesraw
 * @return array
 */
function local_coursedateshiftpro_parse_manual_dates(array $manualdatesraw): array {
    $parsed = [];
    foreach ($manualdatesraw as $key => $value) {
        if (!preg_match('/^k_[a-f0-9]{32}$/', (string)$key)) {
            continue;
        }

        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false && $timestamp > 0) {
            $parsed[$key] = $timestamp;
        }
    }

    return $parsed;
}
