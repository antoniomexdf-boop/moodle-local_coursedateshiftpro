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
 * PDF export for stored executions in local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/pdflib.php');

use local_coursedateshiftpro\local\date_shifter;

// phpcs:disable moodle.Files.LineLength

/**
 * Returns a local path to the configured compact logo when available.
 *
 * @return string
 */
function local_coursedateshiftpro_get_pdf_logo_path(): string {
    global $CFG;

    $fs = get_file_storage();
    $systemcontext = context_system::instance();

    foreach (['logo', 'logocompact'] as $settingname) {
        $files = $fs->get_area_files($systemcontext->id, 'core_admin', $settingname, 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $extension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
            $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $settingname . '_logo.' . ($extension ?: 'png');
            $file->copy_content_to($tmppath);
            if (is_readable($tmppath)) {
                return $tmppath;
            }
        }

        $settingvalue = (string)get_config('core_admin', $settingname);
        if ($settingvalue === '') {
            continue;
        }

        $filename = basename($settingvalue);
        if ($filename === '') {
            continue;
        }

        $patterns = [
            $CFG->dataroot . '/localcache/core_admin/*/' . $settingname . '/*/' . $filename,
            $CFG->dataroot . '/localcache/core_admin/*/' . $settingname . '/*/*/' . $filename,
        ];
        $candidates = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $match) {
                if (is_readable($match)) {
                    $candidates[] = $match;
                }
            }
        }
    }

    if (empty($candidates)) {
        return '';
    }

    usort($candidates, static function (string $left, string $right): int {
        return filemtime($right) <=> filemtime($left);
    });

    return $candidates[0];
}

/**
 * Builds a per-item week marker map for PDF rendering.
 *
 * @param array $lines
 * @return array
 */
function local_coursedateshiftpro_get_pdf_line_markers(array $lines): array {
    $ranges = [];
    foreach ($lines as $index => $line) {
        $label = trim((string)($line['label'] ?? ''));
        $weeknumber = (int)($line['weeknumber'] ?? 0);
        if ($label === '' || $weeknumber <= 0) {
            continue;
        }

        if (!isset($ranges[$label])) {
            $ranges[$label] = [
                'first' => $weeknumber,
                'last' => $weeknumber,
                'rows' => [],
            ];
        }

        $ranges[$label]['first'] = min($ranges[$label]['first'], $weeknumber);
        $ranges[$label]['last'] = max($ranges[$label]['last'], $weeknumber);
        $ranges[$label]['rows'][] = $index;
    }

    $markers = [];
    foreach ($ranges as $range) {
        foreach ($range['rows'] as $rowindex) {
            if ($range['first'] === $range['last']) {
                $markers[$rowindex] = ['symbol' => 'ONE', 'color' => '#047857'];
                continue;
            }

            $currentweek = (int)($lines[$rowindex]['weeknumber'] ?? 0);
            if ($currentweek === $range['first']) {
                $markers[$rowindex] = ['symbol' => 'START', 'color' => '#0f6cbf'];
            } else if ($currentweek === $range['last']) {
                $markers[$rowindex] = ['symbol' => 'END', 'color' => '#b45309'];
            }
        }
    }

    return $markers;
}

require_login();
require_capability('local/coursedateshiftpro:manage', context_system::instance());
require_sesskey();

$executionid = required_param('executionid', PARAM_INT);
$execution = date_shifter::get_history_execution($executionid);
$summary = $execution->summary ?? [];
$lines = $summary['report']['lines'] ?? [];
$blocks = !empty($summary['blocks']) && is_array($summary['blocks']) ?
    implode(', ', $summary['blocks']) :
    get_string('notset', 'local_coursedateshiftpro');
$site = get_site();
$sitename = format_string($site->fullname ?? '');
$logosource = local_coursedateshiftpro_get_pdf_logo_path();
$linemarkers = local_coursedateshiftpro_get_pdf_line_markers($lines);

$pdf = new pdf();
$pdf->SetCreator('Moodle');
$pdf->SetAuthor(fullname($USER));
$pdf->SetTitle(get_string('historyreporttitle', 'local_coursedateshiftpro') . ' CDSP-' . $executionid);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$left = 15;
$right = 195;
$topliney = 15;
$bottomliney = 42;
$logox = 20;
$logoy = 19;
$logow = 20;
$logoh = 20;
$textx = 48;
$texty = 20;

$pdf->SetLineWidth(0.6);
$pdf->Line($left, $topliney, $right, $topliney);
$pdf->Line($left, $bottomliney, $right, $bottomliney);

if ($logosource !== '' && is_readable($logosource)) {
    $pdf->Image($logosource, $logox, $logoy, $logow, $logoh, '', '', '', false, 300);
}

$pdf->SetXY($textx, $texty);
$pdf->SetFont('times', 'B', 24);
$pdf->Cell(0, 8, $sitename, 0, 1, 'C');
$pdf->SetX($textx);
$pdf->SetFont('times', '', 17);
$pdf->Cell(0, 8, get_string('historyreporttitle', 'local_coursedateshiftpro'), 0, 1, 'C');
$pdf->SetY(48);

$html = '';
$html .= '<table cellpadding="3" cellspacing="0" border="1" style="font-size:8px;">';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportid', 'local_coursedateshiftpro')) .
    '</strong></td><td>CDSP-' . (int)$execution->id . '</td></tr>';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportgenerated', 'local_coursedateshiftpro')) .
    '</strong></td><td>' . s(userdate(time())) . '</td></tr>';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportcourse', 'local_coursedateshiftpro')) .
    '</strong></td><td>' . s(format_string($execution->coursename)) . '</td></tr>';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportuser', 'local_coursedateshiftpro')) .
    '</strong></td><td>' . s((string)$execution->actorname) . '</td></tr>';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportstatus', 'local_coursedateshiftpro')) .
    '</strong></td><td>' . s((string)$execution->statuslabel) . '</td></tr>';
$html .= '<tr><td><strong>' . s(get_string('historyreportdates', 'local_coursedateshiftpro')) . '</strong></td><td>' .
    s(userdate((int)$execution->oldstartdate)) . ' -> ' . s(userdate((int)$execution->newstartdate)) . '</td></tr>';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportshift', 'local_coursedateshiftpro')) .
    '</strong></td><td>' . s(format_time(abs((int)$execution->delta))) . '</td></tr>';
$html .= '<tr><td><strong>' .
    s(get_string('historyreportblocks', 'local_coursedateshiftpro')) .
    '</strong></td><td>' . s($blocks) . '</td></tr>';
$html .= '</table>';
$html .= '<br>';

if (empty($lines)) {
    $html .= '<p>' . s(get_string('historyreportempty', 'local_coursedateshiftpro')) . '</p>';
} else {
    $html .= '<table cellpadding="3" cellspacing="0" border="1" style="font-size:8px;">';
    $html .= '<thead><tr style="font-weight:bold;background-color:#f3f4f6;">';
    $html .= '<th>' . s(get_string('historyreportweekcolumn', 'local_coursedateshiftpro')) . '</th>';
    $html .= '<th>' . s(get_string('historyreportmarkercolumn', 'local_coursedateshiftpro')) . '</th>';
    $html .= '<th>' . s(get_string('historyreportitemcolumn', 'local_coursedateshiftpro')) . '</th>';
    $html .= '<th>' . s(get_string('historyreportfieldcolumn', 'local_coursedateshiftpro')) . '</th>';
    $html .= '<th>' . s(get_string('historyreportoldcolumn', 'local_coursedateshiftpro')) . '</th>';
    $html .= '<th>' . s(get_string('historyreportnewcolumn', 'local_coursedateshiftpro')) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($lines as $index => $line) {
        $weeklabel = !empty($line['weeknumber']) ?
            get_string('weeklyloadweeklabel', 'local_coursedateshiftpro', (int)$line['weeknumber']) :
            get_string('notset', 'local_coursedateshiftpro');
        $oldvalue = !empty($line['oldvalue']) ? userdate((int)$line['oldvalue']) : get_string('notset', 'local_coursedateshiftpro');
        $newvalue = !empty($line['newvalue']) ? userdate((int)$line['newvalue']) : get_string('notset', 'local_coursedateshiftpro');
        $markerhtml = '';
        if (!empty($linemarkers[$index])) {
            $markerhtml = '<span style="color:' . $linemarkers[$index]['color'] . ';font-weight:bold;font-size:8px;">' .
                s($linemarkers[$index]['symbol']) .
                '</span> ';
        }
        $html .= '<tr>';
        $html .= '<td>' . s($weeklabel) . '</td>';
        $html .= '<td style="text-align:center;">' . $markerhtml . '</td>';
        $html .= '<td>' . s((string)($line['label'] ?? '')) . '</td>';
        $html .= '<td>' . s((string)($line['fieldlabel'] ?? '')) . '</td>';
        $html .= '<td>' . s($oldvalue) . '</td>';
        $html .= '<td>' . s($newvalue) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<br><div style="font-size:8px;color:#475569;">';
    $html .= '<span style="color:#0f6cbf;font-weight:bold;">START</span> ' .
        s(get_string('weeklyloadmarker_start', 'local_coursedateshiftpro')) . ' &nbsp; ';
    $html .= '<span style="color:#b45309;font-weight:bold;">END</span> ' .
        s(get_string('weeklyloadmarker_end', 'local_coursedateshiftpro')) . ' &nbsp; ';
    $html .= '<span style="color:#047857;font-weight:bold;">ONE</span> ' .
        s(get_string('weeklyloadmarker_single', 'local_coursedateshiftpro'));
    $html .= '</div>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$filename = clean_filename('coursedateshiftpro_CDSP_' . (int)$execution->id . '.pdf');
$pdf->Output($filename, 'D');
exit;
