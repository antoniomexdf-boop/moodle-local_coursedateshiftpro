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
 * Preview rendering helpers for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursedateshiftpro\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared renderer for preview HTML used by the page and AJAX responses.
 */
class preview_renderer {
    /**
     * Renders the preview block with summary, timelines, comparison table and safe apply form.
     *
     * @param array $preview
     * @param array $filters
     * @param array $selecteditemkeys
     * @param bool $postapplystate
     * @return string
     */
    public static function render_preview(array $preview, array $filters, array $selecteditemkeys, bool $postapplystate = false): string {
        $selectedlookup = array_flip($selecteditemkeys ?: array_keys($preview['items']));
        $autoscheduleenabled = !empty($preview['autoschedule']['enabled']);
        $showautoschedule = !empty($preview['autoschedule']['available']);

        $deltaindays = (int)round(((int)$preview['newstartdate'] - (int)$preview['oldstartdate']) / DAYSECS);
        $courseenddates = self::extract_course_end_dates($preview);
        $html = \html_writer::start_div('coursedateshiftpro-preview cdsp-mode-simple');
        $html .= \html_writer::tag('h3', get_string('previewtitle', 'local_coursedateshiftpro'), ['class' => 'coursedateshiftpro-preview__title']);

        $summaryrows = [
            get_string('previewcourse', 'local_coursedateshiftpro') => s($preview['coursename'] ?? ''),
            get_string('previeworiginaldates', 'local_coursedateshiftpro') =>
                \html_writer::tag('div', s(get_string('previeworiginalstart', 'local_coursedateshiftpro')) . ': ' . userdate((int)$preview['oldstartdate'])) .
                \html_writer::tag('div', s(get_string('previeworiginalend', 'local_coursedateshiftpro')) . ': ' . self::format_preview_date($courseenddates['current']), ['style' => 'margin-top:4px;']),
            get_string('previewnewdates', 'local_coursedateshiftpro') =>
                \html_writer::tag('div', s(get_string('previewnewstart', 'local_coursedateshiftpro')) . ': ' . userdate((int)$preview['newstartdate'])) .
                \html_writer::tag('div', s(get_string('previewnewend', 'local_coursedateshiftpro')) . ': ' . self::format_preview_date($courseenddates['new']), ['style' => 'margin-top:4px;']),
            get_string('previewdelta', 'local_coursedateshiftpro') => get_string('previewdeltadays', 'local_coursedateshiftpro', $deltaindays),
        ];

        $html .= \html_writer::start_div('coursedateshiftpro-preview__summary');
        foreach ($summaryrows as $label => $value) {
            $html .= \html_writer::start_div('coursedateshiftpro-preview__card');
            $html .= \html_writer::tag('div', s($label), ['class' => 'coursedateshiftpro-preview__card-label']);
            $html .= \html_writer::tag('div', $value, ['class' => 'coursedateshiftpro-preview__card-value']);
            $html .= \html_writer::end_div();
        }
        $html .= \html_writer::end_div();
        $html .= self::render_decision_panel($preview);
        $html .= self::render_weekly_load_overview($preview['weeklyload'] ?? []);
        $html .= self::render_readiness_summary($preview);

        if (!empty($preview['validations']['errors'])) {
            $html .= self::render_validations($preview['validations'] ?? [], $preview['autoschedule'] ?? []);
        }

        if (!$postapplystate) {
            $html .= \html_writer::tag(
                'div',
                get_string('confirmrealchanges', 'local_coursedateshiftpro'),
                ['class' => 'coursedateshiftpro-preview__notice']
            );

            $html .= \html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new \moodle_url('/local/coursedateshiftpro/index.php'))->out(false),
            ]);
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => (int)$preview['courseid']]);
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'newstartts', 'value' => (int)$preview['newstartdate']]);
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'filtersinitialised', 'value' => 1]);
            foreach ($filters as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                $html .= \html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 'filters[' . $key . ']',
                    'value' => 1,
                ]);
            }
        } else {
            $html .= \html_writer::tag(
                'div',
                get_string('postapplymessage', 'local_coursedateshiftpro'),
                ['class' => 'coursedateshiftpro-preview__notice coursedateshiftpro-preview__notice--success']
            );
        }

        $html .= \html_writer::start_div('cdsp-review-mode-switch');
        $html .= \html_writer::tag('button', get_string('simpleviewlabel', 'local_coursedateshiftpro'), [
            'type' => 'button',
            'class' => 'btn btn-primary cdsp-mode-button is-active',
            'data-mode' => 'simple',
        ]);
        $html .= \html_writer::tag('button', get_string('advancedviewlabel', 'local_coursedateshiftpro'), [
            'type' => 'button',
            'class' => 'btn btn-secondary cdsp-mode-button',
            'data-mode' => 'advanced',
        ]);
        $html .= \html_writer::end_div();

        $timelineitems = self::prepare_timeline_groups($preview);
        $html .= \html_writer::start_div('coursedateshiftpro-preview__timelines cdsp-advanced-only');
        $html .= self::render_timeline(
            get_string('currenttimeline', 'local_coursedateshiftpro'),
            $timelineitems,
            'current',
            'current'
        );
        $html .= self::render_timeline(
            get_string('newtimeline', 'local_coursedateshiftpro'),
            $timelineitems,
            'new',
            'new'
        );
        $html .= \html_writer::end_div();

        $html .= \html_writer::tag('p', s(get_string('simpleviewhelp', 'local_coursedateshiftpro')), ['class' => 'cdsp-table-help cdsp-simple-only']);
        $html .= \html_writer::tag('p', s(get_string('previewtablehelp', 'local_coursedateshiftpro')), ['class' => 'cdsp-table-help cdsp-advanced-only']);
        $html .= self::render_review_table($preview, $selectedlookup, $postapplystate);

        if (!$postapplystate) {
            $html .= \html_writer::tag(
                'label',
                '<input type="checkbox" name="confirmapply" value="1"> ' . s(get_string('confirmapplylabel', 'local_coursedateshiftpro')),
                ['class' => 'cdsp-form-check']
            );
            $html .= \html_writer::tag(
                'label',
                '<input type="checkbox" name="acknowledgealerts" value="1"> ' . s(get_string('acknowledgealertslabel', 'local_coursedateshiftpro')),
                ['class' => 'cdsp-form-check']
            );
            if ($showautoschedule) {
                $checked = $autoscheduleenabled ? ' checked' : '';
                $html .= \html_writer::tag(
                    'label',
                    '<input type="checkbox" name="useautoschedule" value="1"' . $checked . '> ' .
                        s(get_string('useautoschedulelabel', 'local_coursedateshiftpro')),
                    ['class' => 'cdsp-form-check']
                );
            }
            $html .= \html_writer::start_div('cdsp-form-actions');
            $html .= \html_writer::tag(
                'button',
                s(get_string('shiftdates', 'local_coursedateshiftpro')),
                ['type' => 'submit', 'name' => 'applychanges', 'value' => 1, 'class' => 'btn btn-primary']
            );
            $html .= \html_writer::link(
                new \moodle_url('/local/coursedateshiftpro/index.php'),
                get_string('cancelstep', 'local_coursedateshiftpro'),
                ['class' => 'btn btn-secondary']
            );
            $html .= \html_writer::end_div();
            $html .= \html_writer::end_tag('form');
        }

        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Renders a user-friendly weekly course load block.
     *
     * @param array $weeklyload
     * @return string
     */
    private static function render_weekly_load_overview(array $weeklyload): string {
        if (empty($weeklyload['weeks'])) {
            return '';
        }

        $maxdates = 1;
        foreach ($weeklyload['weeks'] as $week) {
            $maxdates = max($maxdates, (int)($week['datecount'] ?? 0));
        }

        $summary = [
            get_string('weeklyloadtotalweeks', 'local_coursedateshiftpro') => (int)($weeklyload['totalweeks'] ?? 0),
            get_string('weeklyloadactivities', 'local_coursedateshiftpro') => (int)($weeklyload['scheduledactivities'] ?? 0),
            get_string('weeklyloadappearances', 'local_coursedateshiftpro') => (int)($weeklyload['weekappearances'] ?? 0),
            get_string('weeklyloaddates', 'local_coursedateshiftpro') => (int)($weeklyload['scheduleddates'] ?? 0),
            get_string('weeklyloadbusyweeks', 'local_coursedateshiftpro') => (int)($weeklyload['busyweeks'] ?? 0),
        ];

        $html = \html_writer::start_div('cdsp-weekly-load');
        $html .= \html_writer::tag('h4', get_string('weeklyloadtitle', 'local_coursedateshiftpro'), ['class' => 'cdsp-weekly-load__title']);
        $html .= \html_writer::tag('p', s(get_string('weeklyloadhelp', 'local_coursedateshiftpro')), ['class' => 'cdsp-weekly-load__help']);

        $html .= \html_writer::start_div('cdsp-weekly-load__summary');
        foreach ($summary as $label => $value) {
            $html .= \html_writer::start_div('cdsp-weekly-load__summary-card');
            $html .= \html_writer::tag('div', s($label), ['class' => 'cdsp-weekly-load__summary-label']);
            $html .= \html_writer::tag('div', (string)$value, ['class' => 'cdsp-weekly-load__summary-value']);
            $html .= \html_writer::end_div();
        }
        $html .= \html_writer::end_div();

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable table-sm cdsp-weekly-load__table';
        $table->head = [
            get_string('weeklyloadweekcolumn', 'local_coursedateshiftpro'),
            get_string('weeklyloaddatescolumn', 'local_coursedateshiftpro'),
            get_string('weeklyloadactivitiescolumn', 'local_coursedateshiftpro'),
            get_string('weeklyloaddatecountcolumn', 'local_coursedateshiftpro'),
            get_string('weeklyloadlevelcolumn', 'local_coursedateshiftpro'),
        ];

        foreach ($weeklyload['weeks'] as $week) {
            $level = (string)($week['loadlevel'] ?? 'light');
            $leveltext = get_string('weeklyloadlevel_' . $level, 'local_coursedateshiftpro');
            $levelbadge = \html_writer::tag('span', s($leveltext), [
                'class' => 'badge rounded-pill cdsp-load-badge cdsp-load-badge--' . $level,
            ]);
            $barwidth = max(12, (int)round((((int)$week['datecount']) / $maxdates) * 100));
            $loadbar = \html_writer::start_div('cdsp-load-visual');
            $loadbar .= \html_writer::tag('div', '', [
                'class' => 'cdsp-load-visual__bar cdsp-load-visual__bar--' . $level,
                'style' => 'width:' . $barwidth . '%;',
            ]);
            $loadbar .= \html_writer::end_div();
            $itemsummary = '';
            if (!empty($week['itemsummary'])) {
                $itemsummary .= \html_writer::start_tag('details', ['class' => 'cdsp-weekly-load__details']);
                $itemsummary .= \html_writer::tag(
                    'summary',
                    get_string('weeklyloadviewitems', 'local_coursedateshiftpro', count($week['itemsummary'])),
                    ['class' => 'cdsp-weekly-load__details-summary']
                );
                $itemsummary .= \html_writer::tag('div', s(get_string('weeklyloadappearancehelp', 'local_coursedateshiftpro')), [
                    'class' => 'cdsp-weekly-load__details-help',
                ]);
                $itemsummary .= \html_writer::start_tag('ul', ['class' => 'cdsp-weekly-load__list']);
                foreach ($week['itemsummary'] as $weekitem) {
                    $markerhtml = self::render_week_item_marker((string)($weekitem['marker'] ?? ''));
                    $labelhtml = s($weekitem['label']);
                    if (!empty($weekitem['itemurl'])) {
                        $labelhtml = \html_writer::link((string)$weekitem['itemurl'], $labelhtml, [
                            'class' => 'cdsp-weekly-load__item-link',
                            'target' => '_blank',
                            'rel' => 'noopener',
                        ]);
                    }
                    $itemsummary .= \html_writer::tag(
                        'li',
                        $markerhtml . $labelhtml . ' - ' . self::format_compact_preview_date((int)($weekitem['sortts'] ?? 0)) .
                            ' (' . (int)$weekitem['count'] . ')'
                    );
                }
                $itemsummary .= \html_writer::end_tag('ul');
                $itemsummary .= \html_writer::end_tag('details');
            }

            $table->data[] = [
                get_string('weeklyloadweeklabel', 'local_coursedateshiftpro', (int)$week['weeknumber']),
                userdate((int)$week['start'], get_string('strftimedateshort', 'langconfig')) . ' - ' .
                    userdate((int)$week['end'], get_string('strftimedateshort', 'langconfig')),
                (int)$week['activitycount'] . $itemsummary,
                (int)$week['datecount'],
                $loadbar . $levelbadge,
            ];
        }

        $html .= \html_writer::table($table);
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Renders a top-level decision panel with the current recommendation at a glance.
     *
     * @param array $preview
     * @return string
     */
    private static function render_decision_panel(array $preview): string {
        $groups = self::build_review_groups($preview);
        if (empty($groups)) {
            return '';
        }

        $weeklyload = $preview['weeklyload'] ?? [];
        $state = self::get_decision_state($groups, $weeklyload);

        $html = \html_writer::start_div('cdsp-decision-panel');
        $html .= \html_writer::start_div('cdsp-decision-panel__hero');
        $html .= \html_writer::tag('div', s(get_string('decisionpaneltitle', 'local_coursedateshiftpro')), ['class' => 'cdsp-decision-panel__eyebrow']);
        $html .= \html_writer::tag('h4', s($state['headline']), ['class' => 'cdsp-decision-panel__headline']);
        $html .= \html_writer::tag('p', s($state['message']), ['class' => 'cdsp-decision-panel__message']);
        $html .= \html_writer::end_div();

        $html .= \html_writer::start_div('cdsp-decision-panel__grid');
        $cards = [
            ['label' => get_string('decisionpanelcourseweeks', 'local_coursedateshiftpro'), 'value' => (int)($weeklyload['totalweeks'] ?? 0)],
            ['label' => get_string('decisionpanelmilestones', 'local_coursedateshiftpro'), 'value' => (int)($weeklyload['scheduleddates'] ?? 0)],
            ['label' => get_string('decisionpanelweekreview', 'local_coursedateshiftpro'), 'value' => $state['reviewweek']],
            ['label' => get_string('decisionpanelnextaction', 'local_coursedateshiftpro'), 'value' => $state['nextaction']],
        ];

        foreach ($cards as $card) {
            $html .= \html_writer::start_div('cdsp-decision-panel__card');
            $html .= \html_writer::tag('div', s($card['label']), ['class' => 'cdsp-decision-panel__label']);
            $html .= \html_writer::tag('div', s((string)$card['value']), ['class' => 'cdsp-decision-panel__value']);
            $html .= \html_writer::end_div();
        }

        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Renders a simple readiness summary before apply.
     *
     * @param array $preview
     * @return string
     */
    private static function render_readiness_summary(array $preview): string {
        $groups = self::build_review_groups($preview);
        if (empty($groups)) {
            return '';
        }

        $summary = [
            'conflicts' => 0,
            'review' => 0,
            'suggested' => 0,
            'ready' => 0,
        ];

        foreach ($groups as $group) {
            $status = (string)($group['status'] ?? '');
            if ($status === trim(get_string('previewstatusconflict', 'local_coursedateshiftpro'))) {
                $summary['conflicts']++;
            } else if ($status === trim(get_string('previewstatusrecommended', 'local_coursedateshiftpro'))) {
                $summary['review']++;
            } else if ($status === trim(get_string('previewstatussuggested', 'local_coursedateshiftpro'))) {
                $summary['suggested']++;
            } else {
                $summary['ready']++;
            }
        }

        $html = \html_writer::start_div('cdsp-readiness');
        $html .= \html_writer::tag('h4', get_string('readinesstitle', 'local_coursedateshiftpro'), ['class' => 'cdsp-readiness__title']);
        $html .= \html_writer::tag('p', s(get_string('readinesshelp', 'local_coursedateshiftpro')), ['class' => 'cdsp-readiness__help']);
        $html .= \html_writer::start_div('cdsp-readiness__grid');

        $cards = [
            ['label' => get_string('readinessconflicts', 'local_coursedateshiftpro'), 'value' => $summary['conflicts'], 'class' => 'is-conflict'],
            ['label' => get_string('readinessreview', 'local_coursedateshiftpro'), 'value' => $summary['review'], 'class' => 'is-review'],
            ['label' => get_string('readinesssuggested', 'local_coursedateshiftpro'), 'value' => $summary['suggested'], 'class' => 'is-suggested'],
            ['label' => get_string('readinessready', 'local_coursedateshiftpro'), 'value' => $summary['ready'], 'class' => 'is-ready'],
        ];

        foreach ($cards as $card) {
            $html .= \html_writer::start_div('cdsp-readiness__card ' . $card['class']);
            $html .= \html_writer::tag('div', s($card['label']), ['class' => 'cdsp-readiness__label']);
            $html .= \html_writer::tag('div', (string)$card['value'], ['class' => 'cdsp-readiness__value']);
            $html .= \html_writer::end_div();
        }

        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Builds a high-level decision state for the preview.
     *
     * @param array $groups
     * @param array $weeklyload
     * @return array
     */
    private static function get_decision_state(array $groups, array $weeklyload): array {
        $conflicts = 0;
        $review = 0;
        $suggested = 0;
        foreach ($groups as $group) {
            $status = (string)($group['status'] ?? '');
            if ($status === trim(get_string('previewstatusconflict', 'local_coursedateshiftpro'))) {
                $conflicts++;
            } else if ($status === trim(get_string('previewstatusrecommended', 'local_coursedateshiftpro'))) {
                $review++;
            } else if ($status === trim(get_string('previewstatussuggested', 'local_coursedateshiftpro'))) {
                $suggested++;
            }
        }

        $reviewweek = get_string('decisionpanelnone', 'local_coursedateshiftpro');
        $highestweek = null;
        foreach (($weeklyload['weeks'] ?? []) as $week) {
            if ($highestweek === null || (int)$week['datecount'] > (int)$highestweek['datecount']) {
                $highestweek = $week;
            }
            if (($week['loadlevel'] ?? '') === 'high') {
                $reviewweek = get_string('weeklyloadweeklabel', 'local_coursedateshiftpro', (int)$week['weeknumber']);
                break;
            }
        }

        if ($reviewweek === get_string('decisionpanelnone', 'local_coursedateshiftpro') && !empty($highestweek)) {
            $reviewweek = get_string('weeklyloadweeklabel', 'local_coursedateshiftpro', (int)$highestweek['weeknumber']);
        }

        if ($conflicts > 0) {
            return [
                'headline' => get_string('decisionpanelheadline_conflicts', 'local_coursedateshiftpro'),
                'message' => get_string('decisionpanelmessage_conflicts', 'local_coursedateshiftpro', $conflicts),
                'reviewweek' => $reviewweek,
                'nextaction' => get_string('decisionpanelaction_conflicts', 'local_coursedateshiftpro'),
            ];
        }

        if ($review > 0 || $suggested > 0) {
            return [
                'headline' => get_string('decisionpanelheadline_review', 'local_coursedateshiftpro'),
                'message' => get_string('decisionpanelmessage_review', 'local_coursedateshiftpro', (object)[
                    'review' => $review,
                    'suggested' => $suggested,
                ]),
                'reviewweek' => $reviewweek,
                'nextaction' => get_string('decisionpanelaction_review', 'local_coursedateshiftpro'),
            ];
        }

        return [
            'headline' => get_string('decisionpanelheadline_ready', 'local_coursedateshiftpro'),
            'message' => get_string('decisionpanelmessage_ready', 'local_coursedateshiftpro'),
            'reviewweek' => $reviewweek,
            'nextaction' => get_string('decisionpanelaction_ready', 'local_coursedateshiftpro'),
        ];
    }

    /**
     * Renders one simple timeline column.
     *
     * @param string $title
     * @param array $items
     * @param string $field
     * @param string $variant
     * @return string
     */
    public static function render_timeline(string $title, array $items, string $field, string $variant = 'current'): string {
        $accent = '#0a66c2';
        $background = '#fff';
        if ($variant === 'recommended') {
            $accent = '#d97706';
            $background = '#fffaf0';
        } else if ($variant === 'new') {
            $accent = '#1a7f37';
            $background = '#f7fff9';
        }

        $html = \html_writer::start_div('coursedateshiftpro-timeline');
        $html .= \html_writer::tag('h4', s($title), ['class' => 'coursedateshiftpro-timeline__title']);
        $html .= \html_writer::start_tag('div', [
            'class' => 'cdsp-timeline-scroll coursedateshiftpro-timeline__scroll',
        ]);
        $html .= \html_writer::tag('div', '', ['class' => 'coursedateshiftpro-timeline__line']);
        foreach ($items as $item) {
            $spacer = max(0, (int)($item['_spacer'] ?? 0));
            $html .= \html_writer::start_div('coursedateshiftpro-timeline__entry', ['style' => 'margin-top:' . $spacer . 'px;']);
            $html .= \html_writer::tag('span', '', [
                'class' => 'coursedateshiftpro-timeline__dot',
                'style' => 'background:' . $accent . ';',
            ]);
            $html .= \html_writer::tag('div', s($item['label']), ['class' => 'coursedateshiftpro-timeline__item']);
            foreach (($item['entries'] ?? []) as $entry) {
                $datevalue = (int)($entry[$field] ?? 0);
                $html .= \html_writer::start_div('coursedateshiftpro-timeline__field');
                $html .= \html_writer::tag('div', s($entry['fieldlabel'] ?? ''), ['class' => 'coursedateshiftpro-timeline__field-label']);
                $html .= \html_writer::tag('div', self::format_preview_date($datevalue), ['class' => 'coursedateshiftpro-timeline__field-date']);
                $html .= \html_writer::end_div();
            }
            $html .= \html_writer::end_div();
        }
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Formats preview dates while handling empty values cleanly.
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_preview_date(int $timestamp): string {
        if ($timestamp <= 0) {
            return s(get_string('notset', 'local_coursedateshiftpro'));
        }

        return userdate($timestamp);
    }

    /**
     * Formats table dates in a shorter Moodle-friendly format.
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_compact_preview_date(int $timestamp): string {
        if ($timestamp <= 0) {
            return s(get_string('notset', 'local_coursedateshiftpro'));
        }

        return userdate($timestamp, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Renders an editable datetime-local input for recommended dates.
     *
     * @param string $key
     * @param int $timestamp
     * @return string
     */
    public static function render_manual_date_input(array $item, int $timestamp): string {
        $value = '';
        if ($timestamp > 0) {
            $value = date('Y-m-d\TH:i', $timestamp);
        }

        $recordsignature = implode(':', [
            (string)($item['category'] ?? ''),
            (string)($item['table'] ?? ''),
            (int)($item['recordid'] ?? 0),
        ]);

        return \html_writer::empty_tag('input', [
            'type' => 'datetime-local',
            'name' => 'manualdates[' . $item['key'] . ']',
            'value' => $value,
            'data-item-key' => (string)$item['key'],
            'data-field' => (string)($item['field'] ?? ''),
            'data-record-signature' => $recordsignature,
            'style' => 'padding:6px 8px;border:1px solid #d0d7de;border-radius:6px;min-width:190px;',
        ]);
    }

    /**
     * Builds aligned timeline items with synchronized spacing.
     *
     * @param array $preview
     * @return array
     */
    public static function prepare_timeline_groups(array $preview): array {
        $sourceitems = array_values($preview['autoschedule']['items'] ?? $preview['items'] ?? []);
        $grouped = [];
        foreach ($sourceitems as $item) {
            $signature = self::get_row_group_signature($item);
            if (!isset($grouped[$signature])) {
                $grouped[$signature] = [
                    'label' => $item['label'] ?? '',
                    'entries' => [],
                ];
            }

            $grouped[$signature]['entries'][] = [
                'fieldlabel' => $item['fieldlabel'] ?? '',
                'current' => (int)($item['current'] ?? 0),
                'new' => (int)($item['baselinenew'] ?? $item['new'] ?? 0),
                'recommendednew' => (int)($item['recommendednew'] ?? $item['new'] ?? 0),
            ];
        }

        $items = array_values($grouped);
        usort($items, static function(array $a, array $b): int {
            $left = self::timeline_group_sort_value($a);
            $right = self::timeline_group_sort_value($b);
            return $left <=> $right;
        });

        $lasttimestamp = 0;
        foreach ($items as $index => $item) {
            $current = self::timeline_group_sort_value($item);
            $spacer = 0;
            if ($index > 0 && $current > 0 && $lasttimestamp > 0) {
                $gapdays = (int)floor(($current - $lasttimestamp) / DAYSECS);
                if ($gapdays > 1) {
                    $spacer = min(42, ($gapdays - 1) * 4);
                }
            }

            $items[$index]['_spacer'] = $spacer;
            if ($current > 0) {
                $lasttimestamp = $current;
            }
        }

        return $items;
    }

    /**
     * Returns the timestamp used to sort and space timeline items.
     *
     * @param array $item
     * @return int
     */
    public static function timeline_group_sort_value(array $item): int {
        $timestamps = [];
        foreach (($item['entries'] ?? []) as $entry) {
            $recommended = (int)($entry['recommendednew'] ?? 0);
            $shifted = (int)($entry['new'] ?? 0);
            $current = (int)($entry['current'] ?? 0);
            if ($recommended > 0) {
                $timestamps[] = $recommended;
            } else if ($shifted > 0) {
                $timestamps[] = $shifted;
            } else if ($current > 0) {
                $timestamps[] = $current;
            }
        }

        if (empty($timestamps)) {
            return 0;
        }

        return min($timestamps);
    }

    /**
     * Renders validation messages for the preview.
     *
     * @param array $validations
     * @param array $autoschedule
     * @return string
     */
    public static function render_validations(array $validations, array $autoschedule = []): string {
        $html = '';
        if (empty($validations['errors'])) {
            return $html;
        }

        $html .= \html_writer::start_tag('details', [
            'style' => 'margin:0 0 14px;padding:12px 14px;border-left:4px solid #b42318;background:#fff5f5;color:#7f1d1d;border-radius:8px;',
        ]);
        $html .= \html_writer::tag(
            'summary',
            s(get_string('validationserrorstitle', 'local_coursedateshiftpro')) . ' (' . count($validations['errors']) . ')',
            ['style' => 'margin:0 0 8px;cursor:pointer;font-weight:700;']
        );
        $html .= \html_writer::alist(array_map('s', $validations['errors']));
        $html .= \html_writer::end_tag('details');

        return $html;
    }

    /**
     * Returns a short label that explains the origin of one row in the preview table.
     *
     * @param array $item
     * @param array $recommendeditem
     * @param array $advisories
     * @return string
     */
    public static function get_item_status_label(array $item, array $recommendeditem, array $advisories = []): string {
        if (self::advisories_have_level($advisories, 'error')) {
            return s(get_string('previewstatusconflict', 'local_coursedateshiftpro'));
        }

        if (!empty($item['autoproposedonly']) || (int)($item['current'] ?? 0) <= 0) {
            return s(get_string('previewstatussuggested', 'local_coursedateshiftpro'));
        }

        if (self::has_reviewable_recommendation($item, $recommendeditem, $advisories)) {
            return s(get_string('previewstatusrecommended', 'local_coursedateshiftpro'));
        }

        return s(get_string('previewstatusdetected', 'local_coursedateshiftpro'));
    }

    /**
     * Returns whether one item still has a visible recommendation worth reviewing.
     *
     * @param array $item
     * @param array $recommendeditem
     * @param array $advisories
     * @return bool
     */
    protected static function has_reviewable_recommendation(array $item, array $recommendeditem, array $advisories = []): bool {
        if ((int)($recommendeditem['new'] ?? 0) === (int)($item['new'] ?? 0)) {
            return false;
        }

        $reason = (string)($recommendeditem['suggestionreason'] ?? $item['suggestionreason'] ?? '');
        if ($reason !== '') {
            return true;
        }

        return !empty($advisories);
    }

    /**
     * Renders the integrated review table with filters and row-level advisories.
     *
     * @param array $preview
     * @param array $selectedlookup
     * @param bool $postapplystate
     * @return string
     */
    public static function render_review_table(array $preview, array $selectedlookup, bool $postapplystate): string {
        $fieldoptions = [];
        $statusoptions = [];
        $groups = self::build_review_groups($preview);
        foreach ($groups as $group) {
            foreach ($group['fields'] as $fieldlabel) {
                $fieldoptions[$fieldlabel] = $fieldlabel;
            }
            $statusoptions[$group['status']] = $group['status'];
        }

        asort($fieldoptions);
        asort($statusoptions);

        $html = \html_writer::start_div('cdsp-review-table-wrap');
        $html .= self::render_review_controls($fieldoptions, $statusoptions);
        $html .= \html_writer::start_tag('div', ['class' => 'table-responsive']);
        $html .= \html_writer::start_tag('table', [
            'class' => 'admintable generaltable',
            'id' => 'cdsp-review-table',
            'style' => 'width:100%;margin:0;',
        ]);
        $html .= \html_writer::start_tag('thead');
        $html .= \html_writer::start_tag('tr');
        foreach ([
            get_string('previewweekcolumn', 'local_coursedateshiftpro'),
            get_string('previewitem', 'local_coursedateshiftpro'),
            get_string('previewstatuscolumn', 'local_coursedateshiftpro'),
            get_string('previewfield', 'local_coursedateshiftpro'),
            get_string('previewadvisoriescolumn', 'local_coursedateshiftpro'),
            get_string('previewcurrentcolumn', 'local_coursedateshiftpro'),
            get_string('previewnewcolumn', 'local_coursedateshiftpro'),
            get_string('previewrecommendedcolumn', 'local_coursedateshiftpro'),
            get_string('actions'),
        ] as $index => $heading) {
            $classes = [];
            if (in_array($index, [4, 5], true)) {
                $classes[] = 'cdsp-advanced-only-cell';
            }
            $html .= \html_writer::tag('th', s($heading), [
                'scope' => 'col',
                'class' => implode(' ', $classes),
                'style' => 'white-space:nowrap;padding:12px 14px;background:#f8f9fa;border-bottom:1px solid #d8dee4;font-weight:700;color:#1f2937;',
            ]);
        }
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::end_tag('thead');
        $html .= \html_writer::start_tag('tbody');
        foreach ($groups as $index => $group) {
            $rowstyle = '';
            if ($group['haserror']) {
                $rowstyle = 'background:#fff1f2;';
            } else if ($group['hasautoproposedonly']) {
                $rowstyle = 'background:#f5f9ff;';
            } else if ($group['hasrecommendedchange']) {
                $rowstyle = 'background:#fffaf1;';
            } else if (!$group['hasadvisories']) {
                $rowstyle = 'background:#f7fcf8;';
            }

            $groupid = 'cdsp-group-' . $index;
            $itemtext = s($group['label']);
            if (!empty($group['itemurl'])) {
                $itemtext = \html_writer::link($group['itemurl'], $itemtext, [
                    'style' => 'color:#0f6cbf;font-weight:600;text-decoration:none;',
                    'target' => '_blank',
                    'rel' => 'noopener',
                ]);
            }

            $html .= \html_writer::start_tag('tr', [
                'class' => 'cdsp-review-group-main',
                'data-group-id' => $groupid,
                'data-fields' => s(implode('||', $group['fields'])),
                'data-status' => s($group['status']),
                'data-weeknumber' => (int)$group['weeknumber'],
                'data-courseorder' => (int)$group['courseorder'],
                'data-currentts' => (int)$group['currentts'],
                'data-newts' => (int)$group['newts'],
                'data-recommendedts' => (int)$group['recommendedts'],
                'style' => $rowstyle . 'border-bottom:1px solid #e5e7eb;',
            ]);
            $html .= \html_writer::tag('td', self::render_week_badge((int)$group['weeknumber']), ['class' => 'cdsp-review-week']);
            $html .= \html_writer::tag('td', $itemtext, ['class' => 'cdsp-review-item']);
            $html .= \html_writer::tag('td', self::render_status_badge($group['status']));
            $html .= \html_writer::tag('td', s(implode(', ', array_slice($group['fields'], 0, 3))) . (count($group['fields']) > 3 ? ' +' . (count($group['fields']) - 3) : ''));
            $html .= \html_writer::tag('td', self::render_advisories_cell($group['summaryadvisories']));
            $html .= \html_writer::tag('td', self::format_compact_preview_date((int)$group['currentts']), ['class' => 'cdsp-date-compact cdsp-advanced-only-cell']);
            $html .= \html_writer::tag('td', self::format_compact_preview_date((int)$group['newts']), ['class' => 'cdsp-date-compact cdsp-advanced-only-cell']);
            $html .= \html_writer::tag('td', self::format_compact_preview_date((int)$group['recommendedts']), ['class' => 'cdsp-date-compact']);
            $togglebutton = \html_writer::tag('button',
                \html_writer::tag('span', '', ['class' => 'icon fa fa-chevron-down fa-fw', 'aria-hidden' => 'true']) .
                \html_writer::tag('span', s(get_string('show')), ['class' => 'accesshide']),
                [
                'type' => 'button',
                'class' => 'cdsp-review-toggle btn btn-icon',
                'data-target' => $groupid,
                'aria-expanded' => 'false',
                'title' => get_string('show'),
                'style' => 'padding:6px 8px;min-width:36px;',
            ]);
            $html .= \html_writer::tag('td', $togglebutton, ['class' => 'text-nowrap']);
            $html .= \html_writer::end_tag('tr');

            $html .= \html_writer::start_tag('tr', [
                'class' => 'cdsp-review-group-detail',
                'data-parent-id' => $groupid,
                'style' => 'display:none;background:#fbfdff;',
            ]);
            $detailhtml = self::render_group_detail_table($group, $selectedlookup, $postapplystate);
            $html .= \html_writer::tag('td', $detailhtml, [
                'colspan' => '9',
                'style' => 'padding:0 14px 14px 14px;border-bottom:1px solid #e5e7eb;',
            ]);
            $html .= \html_writer::end_tag('tr');
        }
        $html .= \html_writer::end_tag('tbody');
        $html .= \html_writer::end_tag('table');
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Groups review items by activity/resource for the expandable review table.
     *
     * @param array $preview
     * @return array
     */
    protected static function build_review_groups(array $preview): array {
        $groups = [];
        foreach ($preview['items'] as $item) {
            $signature = self::get_row_group_signature($item);
            $effectiveitem = self::get_effective_display_item($preview, $item);
            $recommendeditem = self::get_recommended_display_item($preview, $item);
            $advisories = $preview['itemadvisories'][$item['key']] ?? [];
            $status = trim(strip_tags(self::get_item_status_label($item, $recommendeditem, $advisories)));

            if (!isset($groups[$signature])) {
                $groups[$signature] = [
                    'label' => (string)($item['label'] ?? ''),
                    'itemurl' => (string)($item['itemurl'] ?? ''),
                    'weeknumber' => self::get_week_number((int)($effectiveitem['new'] ?? 0), (int)($preview['newstartdate'] ?? 0)),
                    'courseorder' => (int)($item['courseorder'] ?? 999999),
                    'currentts' => PHP_INT_MAX,
                    'newts' => PHP_INT_MAX,
                    'recommendedts' => PHP_INT_MAX,
                    'status' => $status,
                    'statusrank' => self::get_status_rank($status),
                    'haserror' => false,
                    'hasadvisories' => false,
                    'hasrecommendedchange' => false,
                    'hasautoproposedonly' => false,
                    'fields' => [],
                    'summaryadvisories' => [],
                    'items' => [],
                ];
            }

            $groups[$signature]['fields'][$item['fieldlabel']] = $item['fieldlabel'];
            $groups[$signature]['items'][] = [
                'item' => $item,
                'effectiveitem' => $effectiveitem,
                'recommendeditem' => $recommendeditem,
                'advisories' => $advisories,
                'status' => $status,
            ];
            $groups[$signature]['haserror'] = $groups[$signature]['haserror'] || self::advisories_have_level($advisories, 'error');
            $groups[$signature]['hasadvisories'] = $groups[$signature]['hasadvisories'] || !empty($advisories);
            $groups[$signature]['hasrecommendedchange'] = $groups[$signature]['hasrecommendedchange'] ||
                self::has_reviewable_recommendation($item, $recommendeditem, $advisories);
            $groups[$signature]['hasautoproposedonly'] = $groups[$signature]['hasautoproposedonly'] || !empty($item['autoproposedonly']);
            $groups[$signature]['statusrank'] = min($groups[$signature]['statusrank'], self::get_status_rank($status));
            $groups[$signature]['status'] = self::get_status_from_rank($groups[$signature]['statusrank']);
            $groups[$signature]['currentts'] = self::min_non_zero($groups[$signature]['currentts'], (int)($item['current'] ?? 0));
            $groups[$signature]['newts'] = self::min_non_zero($groups[$signature]['newts'], (int)($effectiveitem['new'] ?? 0));
            $groups[$signature]['recommendedts'] = self::min_non_zero($groups[$signature]['recommendedts'], (int)($recommendeditem['new'] ?? 0));

            foreach ($advisories as $advisory) {
                $text = (string)($advisory['text'] ?? '');
                if ($text === '' || isset($groups[$signature]['summaryadvisories'][$text])) {
                    continue;
                }
                if (count($groups[$signature]['summaryadvisories']) >= 3) {
                    continue;
                }
                $groups[$signature]['summaryadvisories'][$text] = $advisory;
            }
        }

        foreach ($groups as $key => $group) {
            $groups[$key]['fields'] = array_values($group['fields']);
            $groups[$key]['summaryadvisories'] = array_values($group['summaryadvisories']);
            if ($groups[$key]['currentts'] === PHP_INT_MAX) {
                $groups[$key]['currentts'] = 0;
            }
            if ($groups[$key]['newts'] === PHP_INT_MAX) {
                $groups[$key]['newts'] = 0;
            }
            if ($groups[$key]['recommendedts'] === PHP_INT_MAX) {
                $groups[$key]['recommendedts'] = 0;
            }
            if (empty($groups[$key]['weeknumber'])) {
                $groups[$key]['weeknumber'] = self::get_week_number((int)$groups[$key]['newts'], (int)($preview['newstartdate'] ?? 0));
            }
        }

        uasort($groups, static function(array $left, array $right): int {
            if ((int)$left['weeknumber'] !== (int)$right['weeknumber']) {
                return (int)$left['weeknumber'] <=> (int)$right['weeknumber'];
            }
            if ((int)$left['newts'] !== (int)$right['newts']) {
                return (int)$left['newts'] <=> (int)$right['newts'];
            }
            if ((int)$left['recommendedts'] !== (int)$right['recommendedts']) {
                return (int)$left['recommendedts'] <=> (int)$right['recommendedts'];
            }
            if ((int)$left['courseorder'] !== (int)$right['courseorder']) {
                return (int)$left['courseorder'] <=> (int)$right['courseorder'];
            }
            return strcmp((string)$left['label'], (string)$right['label']);
        });

        return array_values($groups);
    }

    /**
     * Renders the field-level detail table for one review group.
     *
     * @param array $group
     * @param array $selectedlookup
     * @param bool $postapplystate
     * @return string
     */
    protected static function render_group_detail_table(array $group, array $selectedlookup, bool $postapplystate): string {
        $html = \html_writer::start_tag('table', [
            'class' => 'cdsp-review-detail-table',
        ]);
        $html .= \html_writer::start_tag('thead');
        $html .= \html_writer::start_tag('tr');
        foreach ([
            get_string('previewfield', 'local_coursedateshiftpro'),
            get_string('previewstatuscolumn', 'local_coursedateshiftpro'),
            get_string('previewadvisoriescolumn', 'local_coursedateshiftpro'),
            get_string('previewcurrentcolumn', 'local_coursedateshiftpro'),
            get_string('previewnewcolumn', 'local_coursedateshiftpro'),
            get_string('previewrecommendedcolumn', 'local_coursedateshiftpro'),
        ] as $index => $heading) {
            $classes = [];
            if (in_array($index, [3, 4], true)) {
                $classes[] = 'cdsp-advanced-only-cell';
            }
            $html .= \html_writer::tag('th', s($heading), [
                'scope' => 'col',
                'class' => implode(' ', $classes),
            ]);
        }
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::end_tag('thead');
        $html .= \html_writer::start_tag('tbody');

        foreach ($group['items'] as $row) {
            $item = $row['item'];
            $effectiveitem = $row['effectiveitem'];
            $recommendeditem = $row['recommendeditem'];
            $advisories = $row['advisories'];
            $status = $row['status'];
            $recommendedts = (int)($recommendeditem['new'] ?? 0);
            $suggestionreason = self::render_suggestion_reason($item, $recommendeditem);
            $html .= \html_writer::start_tag('tr', [
                'style' => 'border-bottom:1px solid #eef2f6;',
            ]);
            $fieldcontent = '';
            if (!$postapplystate) {
                $fieldcontent .= '<input type="checkbox" name="selectedkeys[]" value="' . s($item['key']) . '"' .
                    (isset($selectedlookup[$item['key']]) ? ' checked' : '') . '> ';
            }
            $fieldcontent .= s($item['fieldlabel']);
            if ($suggestionreason !== '') {
                $fieldcontent .= \html_writer::tag('div', $suggestionreason, ['class' => 'cdsp-suggestion-reason']);
            }
            $html .= \html_writer::tag('td', $fieldcontent);
            $html .= \html_writer::tag('td', self::render_status_badge($status));
            $html .= \html_writer::tag('td', self::render_advisories_cell($advisories));
            $html .= \html_writer::tag('td', self::format_compact_preview_date((int)($item['current'] ?? 0)), ['class' => 'cdsp-date-compact cdsp-advanced-only-cell']);
            $html .= \html_writer::tag('td', self::format_compact_preview_date((int)($effectiveitem['new'] ?? 0)), ['class' => 'cdsp-date-compact cdsp-advanced-only-cell']);
            if (!$postapplystate) {
                $html .= \html_writer::tag('td', self::render_manual_date_input($item, $recommendedts));
            } else {
                $html .= \html_writer::tag('td', self::format_compact_preview_date($recommendedts), ['class' => 'cdsp-date-compact']);
            }
            $html .= \html_writer::end_tag('tr');
        }

        $html .= \html_writer::end_tag('tbody');
        $html .= \html_writer::end_tag('table');

        return $html;
    }

    /**
     * Renders a short explanation for why a suggested date exists.
     *
     * @param array $item
     * @param array $recommendeditem
     * @return string
     */
    protected static function render_suggestion_reason(array $item, array $recommendeditem): string {
        $reason = (string)($recommendeditem['suggestionreason'] ?? $item['suggestionreason'] ?? '');
        if ($reason === '') {
            return '';
        }

        if ($reason === 'missingdate') {
            return s(get_string('suggestionreason_missingdate', 'local_coursedateshiftpro'));
        }

        if ($reason === 'balance') {
            return s(get_string('suggestionreason_balance', 'local_coursedateshiftpro'));
        }

        if (strpos($reason, 'linked:') === 0) {
            $sourcefield = substr($reason, 7);
            return s(get_string('suggestionreason_linked', 'local_coursedateshiftpro', self::humanise_field($sourcefield)));
        }

        return '';
    }

    /**
     * Renders a compact week badge for the review table.
     *
     * @param int $weeknumber
     * @return string
     */
    protected static function render_week_badge(int $weeknumber): string {
        if ($weeknumber <= 0) {
            return s(get_string('notset', 'local_coursedateshiftpro'));
        }

        return \html_writer::tag(
            'span',
            s(get_string('weeklyloadweeklabel', 'local_coursedateshiftpro', $weeknumber)),
            ['class' => 'badge rounded-pill cdsp-week-badge']
        );
    }

    /**
     * Renders a small weekly marker for scheduled item details.
     *
     * @param string $marker
     * @return string
     */
    protected static function render_week_item_marker(string $marker): string {
        if ($marker === 'start') {
            return \html_writer::tag('span',
                \html_writer::tag('span', '', ['class' => 'icon fa fa-play fa-fw', 'aria-hidden' => 'true']) .
                \html_writer::tag('span', s(get_string('weeklyloadmarker_start', 'local_coursedateshiftpro')), ['class' => 'accesshide']),
                ['class' => 'cdsp-week-item-marker cdsp-week-item-marker--start', 'title' => get_string('weeklyloadmarker_start', 'local_coursedateshiftpro')]
            ) . ' ';
        }

        if ($marker === 'end') {
            return \html_writer::tag('span',
                \html_writer::tag('span', '', ['class' => 'icon fa fa-flag-checkered fa-fw', 'aria-hidden' => 'true']) .
                \html_writer::tag('span', s(get_string('weeklyloadmarker_end', 'local_coursedateshiftpro')), ['class' => 'accesshide']),
                ['class' => 'cdsp-week-item-marker cdsp-week-item-marker--end', 'title' => get_string('weeklyloadmarker_end', 'local_coursedateshiftpro')]
            ) . ' ';
        }

        if ($marker === 'single') {
            return \html_writer::tag('span',
                \html_writer::tag('span', '', ['class' => 'icon fa fa-circle fa-fw', 'aria-hidden' => 'true']) .
                \html_writer::tag('span', s(get_string('weeklyloadmarker_single', 'local_coursedateshiftpro')), ['class' => 'accesshide']),
                ['class' => 'cdsp-week-item-marker cdsp-week-item-marker--single', 'title' => get_string('weeklyloadmarker_single', 'local_coursedateshiftpro')]
            ) . ' ';
        }

        return '';
    }

    /**
     * Returns the course-relative week number for one timestamp.
     *
     * @param int $timestamp
     * @param int $coursestart
     * @return int
     */
    protected static function get_week_number(int $timestamp, int $coursestart): int {
        if ($timestamp <= 0 || $coursestart <= 0) {
            return 0;
        }

        if ($timestamp <= $coursestart) {
            return 1;
        }

        return (int)floor(($timestamp - $coursestart) / WEEKSECS) + 1;
    }

    /**
     * Returns the severity rank for one row status.
     *
     * @param string $status
     * @return int
     */
    protected static function get_status_rank(string $status): int {
        if ($status === trim(get_string('previewstatusconflict', 'local_coursedateshiftpro'))) {
            return 0;
        }
        if ($status === trim(get_string('previewstatusrecommended', 'local_coursedateshiftpro'))) {
            return 1;
        }
        if ($status === trim(get_string('previewstatussuggested', 'local_coursedateshiftpro'))) {
            return 2;
        }
        return 3;
    }

    /**
     * Returns a status label from its rank.
     *
     * @param int $rank
     * @return string
     */
    protected static function get_status_from_rank(int $rank): string {
        if ($rank <= 0) {
            return trim(get_string('previewstatusconflict', 'local_coursedateshiftpro'));
        }
        if ($rank === 1) {
            return trim(get_string('previewstatusrecommended', 'local_coursedateshiftpro'));
        }
        if ($rank === 2) {
            return trim(get_string('previewstatussuggested', 'local_coursedateshiftpro'));
        }
        return trim(get_string('previewstatusdetected', 'local_coursedateshiftpro'));
    }

    /**
     * Returns the smallest non-zero timestamp.
     *
     * @param int $left
     * @param int $right
     * @return int
     */
    protected static function min_non_zero(int $left, int $right): int {
        if ($right <= 0) {
            return $left;
        }
        if ($left <= 0 || $left === PHP_INT_MAX) {
            return $right;
        }
        return min($left, $right);
    }

    /**
     * Renders filter and sort controls for the review table.
     *
     * @param array $fieldoptions
     * @param array $statusoptions
     * @return string
     */
    protected static function render_review_controls(array $fieldoptions, array $statusoptions): string {
        $html = \html_writer::start_div('cdsp-review-controls cdsp-advanced-only');
        $html .= self::render_select_control('cdsp-filter-field', get_string('reviewfilterfield', 'local_coursedateshiftpro'), get_string('reviewfilterall', 'local_coursedateshiftpro'), $fieldoptions);
        $html .= self::render_select_control('cdsp-filter-status', get_string('reviewfilterstatus', 'local_coursedateshiftpro'), get_string('reviewfilterall', 'local_coursedateshiftpro'), $statusoptions);
        $html .= self::render_select_control('cdsp-sort-by', get_string('reviewsortby', 'local_coursedateshiftpro'), '', [
            'courseorder' => get_string('reviewsortcourseorder', 'local_coursedateshiftpro'),
            'currentts' => get_string('reviewsortcurrentdate', 'local_coursedateshiftpro'),
            'newts' => get_string('reviewsortnewdate', 'local_coursedateshiftpro'),
            'recommendedts' => get_string('reviewsortrecommendeddate', 'local_coursedateshiftpro'),
        ], 'recommendedts');
        $html .= \html_writer::tag('button', s(get_string('reviewresetfilters', 'local_coursedateshiftpro')), [
            'type' => 'button',
            'id' => 'cdsp-review-reset',
            'class' => 'btn btn-secondary align-self-end',
        ]);
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Renders one select control.
     *
     * @param string $id
     * @param string $label
     * @param string $placeholder
     * @param array $options
     * @param string $selected
     * @return string
     */
    protected static function render_select_control(string $id, string $label, string $placeholder, array $options, string $selected = ''): string {
        $html = \html_writer::start_tag('label', ['class' => 'cdsp-review-control']);
        $html .= \html_writer::tag('span', s($label), ['class' => 'cdsp-review-control__label']);
        $html .= \html_writer::start_tag('select', [
            'id' => $id,
            'class' => 'custom-select',
        ]);
        if ($placeholder !== '') {
            $html .= \html_writer::tag('option', s($placeholder), ['value' => '']);
        }
        foreach ($options as $value => $text) {
            $attrs = ['value' => (string)$value];
            if ((string)$value === $selected) {
                $attrs['selected'] = 'selected';
            }
            $html .= \html_writer::tag('option', s($text), $attrs);
        }
        $html .= \html_writer::end_tag('select');
        $html .= \html_writer::end_tag('label');

        return $html;
    }

    /**
     * Renders advisory badges for one table row.
     *
     * @param array $advisories
     * @return string
     */
    protected static function render_advisories_cell(array $advisories): string {
        if (empty($advisories)) {
            return \html_writer::tag('span', s(get_string('reviewnoadvisories', 'local_coursedateshiftpro')), [
                'style' => 'display:inline-block;padding:7px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#ecfdf3;color:#166534;border:1px solid #86efac;',
            ]);
        }

        $html = '';
        foreach ($advisories as $advisory) {
            $level = $advisory['level'] ?? 'info';
            $styles = 'display:inline-block;margin:0 6px 6px 0;padding:7px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent;';
            if ($level === 'error') {
                $styles .= 'background:#fff1f2;color:#b42318;border-color:#fda4af;box-shadow:0 0 0 1px rgba(180,35,24,.05);';
            } else if ($level === 'warning') {
                $styles .= 'background:#fff1f2;color:#b42318;border-color:#fda4af;box-shadow:0 0 0 1px rgba(180,35,24,.05);';
            } else if ($level === 'notice') {
                $styles .= 'background:#fffbeb;color:#b45309;border-color:#fcd34d;box-shadow:0 0 0 1px rgba(180,83,9,.05);';
            } else {
                $styles .= 'background:#eff6ff;color:#1d4ed8;border-color:#93c5fd;box-shadow:0 0 0 1px rgba(29,78,216,.05);';
            }
            $html .= \html_writer::tag('span', s($advisory['text'] ?? ''), ['style' => $styles]);
        }

        return $html;
    }

    /**
     * Renders the status as a visual badge.
     *
     * @param string $statusplain
     * @return string
     */
    protected static function render_status_badge(string $statusplain): string {
        $styles = 'display:inline-block;padding:7px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent;';
        if ($statusplain === trim(get_string('previewstatusconflict', 'local_coursedateshiftpro'))) {
            $styles .= 'background:#fff1f2;color:#b42318;border-color:#fda4af;';
        } else if ($statusplain === trim(get_string('previewstatussuggested', 'local_coursedateshiftpro'))) {
            $styles .= 'background:#eff6ff;color:#1d4ed8;border-color:#93c5fd;';
        } else if ($statusplain === trim(get_string('previewstatusrecommended', 'local_coursedateshiftpro'))) {
            $styles .= 'background:#fff7ed;color:#b45309;border-color:#fdba74;';
        } else {
            $styles .= 'background:#ecfdf3;color:#166534;border-color:#86efac;';
        }

        return \html_writer::tag('span', s($statusplain), ['style' => $styles]);
    }

    /**
     * Returns the item currently shown as recommended for the row.
     *
     * @param array $preview
     * @param array $item
     * @return array
     */
    protected static function get_recommended_display_item(array $preview, array $item): array {
        if (!empty($preview['autoschedule']['enabled']) && !empty($preview['autoschedule']['available'])) {
            return $preview['autoschedule']['items'][$item['key']] ?? $item;
        }

        return $preview['manualitems'][$item['key']] ?? $item;
    }

    /**
     * Returns the item currently shown as the effective new date for apply-time behavior.
     *
     * @param array $preview
     * @param array $item
     * @return array
     */
    protected static function get_effective_display_item(array $preview, array $item): array {
        return $preview['effectiveitems'][$item['key']] ?? $item;
    }

    /**
     * Returns the grouping signature for one table row.
     *
     * @param array $item
     * @return string
     */
    protected static function get_row_group_signature(array $item): string {
        return implode(':', [
            (string)($item['category'] ?? ''),
            (string)($item['table'] ?? ''),
            (int)($item['recordid'] ?? 0),
            (string)($item['label'] ?? ''),
        ]);
    }

    /**
     * Returns whether the row advisories contain a specific level.
     *
     * @param array $advisories
     * @param string $level
     * @return bool
     */
    protected static function advisories_have_level(array $advisories, string $level): bool {
        foreach ($advisories as $advisory) {
            if (($advisory['level'] ?? '') === $level) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts original and new course end dates from the preview payload.
     *
     * @param array $preview
     * @return array
     */
    protected static function extract_course_end_dates(array $preview): array {
        foreach ($preview['items'] as $item) {
            if (($item['category'] ?? '') !== 'courseenddate' || ($item['field'] ?? '') !== 'enddate') {
                continue;
            }

            return [
                'current' => (int)($item['current'] ?? 0),
                'new' => (int)($item['new'] ?? 0),
            ];
        }

        return [
            'current' => 0,
            'new' => 0,
        ];
    }
}
