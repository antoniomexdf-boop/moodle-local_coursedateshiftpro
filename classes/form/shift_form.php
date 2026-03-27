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
 * Form definition for local_coursedateshiftpro.
 *
 * @package   local_coursedateshiftpro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursedateshiftpro\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Admin form for the Pro demo version.
 */
class shift_form extends \moodleform {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        $courseoptions = [0 => get_string('selectcourse', 'local_coursedateshiftpro')] + $courseoptions;
        $selectedcourse = $this->_customdata['selectedcourse'] ?? null;
        $selectedcourseid = !empty($selectedcourse->id) ? (int)$selectedcourse->id : 0;
        $newstartdate = $this->_customdata['newstartdate'] ?? 0;
        $filters = $this->_customdata['filters'] ?? [];
        $courseloaded = !empty($this->_customdata['courseloaded']);

        $mform->addElement('header', 'general', get_string('pageheading', 'local_coursedateshiftpro'));
        $mform->addElement(
            'autocomplete',
            'courseid',
            get_string('courseselector', 'local_coursedateshiftpro'),
            $courseoptions,
            ['noselectionstring' => get_string('selectcourse', 'local_coursedateshiftpro')]
        );
        $mform->setDefault('courseid', $selectedcourseid);
        $mform->addRule('courseid', get_string('errorcoursemissing', 'local_coursedateshiftpro'), 'required', null, 'client');

        if (!$courseloaded) {
            $buttonarray = [];
            $buttonarray[] = &$mform->createElement('submit', 'loadcourse', get_string('loadcourse', 'local_coursedateshiftpro'));
            $mform->addGroup($buttonarray, 'actions', '', [' '], false);
            return;
        }

        $currentstartdate = 0;
        $currentstartlabel = '-';
        if (!empty($selectedcourse)) {
            $currentstartdate = (int)$selectedcourse->startdate;
            if ($currentstartdate > 0) {
                $currentstartlabel = userdate($currentstartdate);
            }
        }

        $mform->addElement(
            'static',
            'currentstartdatelabel',
            get_string('currentstartdate', 'local_coursedateshiftpro'),
            $currentstartlabel
        );
        $mform->addElement('hidden', 'currentstartdate', $currentstartdate);
        $mform->setType('currentstartdate', PARAM_INT);

        $mform->addElement('date_time_selector', 'newstartdate', get_string('newstartdate', 'local_coursedateshiftpro'));
        $mform->setType('newstartdate', PARAM_INT);
        $mform->setDefault('newstartdate', !empty($newstartdate) ? $newstartdate : ($currentstartdate ?: time()));

        if (!empty($selectedcourse)) {
            $mform->addElement('hidden', 'filtersinitialised', 1);
            $mform->setType('filtersinitialised', PARAM_INT);
            $mform->addElement('header', 'filtersheader', get_string('filterstitle', 'local_coursedateshiftpro'));
            foreach ($this->get_filter_labels() as $name => $label) {
                $mform->addElement('advcheckbox', 'filters[' . $name . ']', $label);
                $mform->setDefault('filters[' . $name . ']', !empty($filters[$name]) ? 1 : 0);
            }
        }

        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'previewdates', get_string('previewdates', 'local_coursedateshiftpro'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancelstep', get_string('cancelstep', 'local_coursedateshiftpro'));
        $mform->addGroup($buttonarray, 'actions', '', [' '], false);
    }

    /**
     * Validates the submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['courseid'])) {
            $errors['courseid'] = get_string('errorcoursemissing', 'local_coursedateshiftpro');
        }

        if (!empty($data['previewdates'])) {
            if (empty($data['currentstartdate'])) {
                $errors['courseid'] = get_string('errorcurrentstartmissing', 'local_coursedateshiftpro');
            }

            if (empty($data['newstartdate'])) {
                $errors['newstartdate'] = get_string('errornewstartmissing', 'local_coursedateshiftpro');
            }
        }

        return $errors;
    }

    /**
     * Returns labels for the top-level filters.
     *
     * @return array
     */
    private function get_filter_labels(): array {
        return [
            'courseenddate' => get_string('filtercourseenddate', 'local_coursedateshiftpro'),
            'activities' => get_string('filteractivities', 'local_coursedateshiftpro'),
            'sections' => get_string('filtersections', 'local_coursedateshiftpro'),
            'restrictions' => get_string('filterrestrictions', 'local_coursedateshiftpro'),
            'overrides' => get_string('filteroverrides', 'local_coursedateshiftpro'),
            'completionexpected' => get_string('filtercompletionexpected', 'local_coursedateshiftpro'),
        ];
    }

}
