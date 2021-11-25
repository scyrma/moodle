<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class course
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Allows to map courses during export and search for existing courses during import
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course extends export_import_mapper_base {

    /**
     * Initialises mapper and registers all potential notices
     */
    protected function initialise(): void {
        $this->register_potential_error(self::NOTFOUND, [
            self::ERROR_CONFLICTHEADER => get_string('errorcoursesdonotexist', 'tool_wp'),
            self::ERROR_LOG => function(array $identifier) {
                return get_string('mappingerrorcoursenotfound', 'tool_wp', $this->get_identifier_for_display($identifier, true));
            },
            self::ERROR_CONFLICTSOLUTION => function(array $values, bool $forform) {
                if ($values['action'] === 'create') {
                    if ($forform) {
                        return get_string('importconflictcreatecourse', 'tool_wp');
                    } else {
                        $category = \core_course_category::get($values['catid']);
                        return get_string('importconflictcreatecourseincategory', 'tool_wp', $category->get_formatted_name());
                    }
                }
                return null;
            },
            self::ERROR_IDENTIFIER => static function(array $identifier, bool $usequotes = false) {
                return self::display_identifier_idnumber_name($identifier, 'idnumber', 'shortname', $usequotes);
            },
        ]);

        $this->register_potential_notice('idnumbermapping', [
            self::NOTICE_LOG => function(array $identifier, array $additionaldata) {
                $url = course_get_url($additionaldata['courseid']);
                $a = (object)[
                    'shortname' => s($identifier['shortname']),
                    'idnumber' => s($identifier['idnumber']),
                    'courseurl' => $url->out()
                ];
                return get_string('mappingnoticecourseidnumber', 'tool_wp', $a);
            }
        ]);

        $this->register_potential_notice('coursecreated', [
            self::NOTICE_LOG => function(array $identifier, array $additionaldata) {
                $url = course_get_url($additionaldata['courseid']);
                $a = (object)[
                    'fullname' => s($additionaldata['fullname']),
                    'courseurl' => $url->out()
                ];
                return get_string('mappingnoticecoursecreated', 'tool_wp', $a);
            }
        ]);

    }

    /**
     * Returns the array of properties of an entity that can be used to find this entity during import
     *
     * This function is used when the entity itself is not included in the export
     *
     * @param int $id
     * @return array|null
     */
    public function get_mapping_data_for_workplace_export(int $id): ?array {
        global $DB;
        $obj = $DB->get_record('course', ['id' => $id], 'id, shortname, fullname, idnumber');
        return $obj ? (array)$obj : null;
    }

    /**
     * Allows to locate the existing entity that is available to the current user by default identifier
     *
     * @param string $identifier the default identifier used by the entity (normally shortname/idnumber/name)
     * @param int $tenantid strictly inside the given tenant (for entities that can be inside tenants)
     * @return int|null the id of the entity or null if not found or not available
     */
    public function locate_mapping_default(string $identifier, ?int $tenantid = null): ?int {
        if ($courseid = $this->locate_mapping(['shortname' => $identifier])) {
            return $courseid;
        }

        if ($courseid = $this->locate_mapping(['idnumber' => $identifier])) {
            return $courseid;
        }

        return null;
    }

    /**
     * Allows to locate the existing entity that is available to the current user
     *
     * @param array $identifier array or known entity's attributes, for example:
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID', 'tenantid' => 1]
     * @return int|null
     */
    public function locate_mapping(array $identifier): ?int {
        global $DB;
        if (!empty($identifier['shortname'])) {
            $course = $DB->get_record('course', ['shortname' => $identifier['shortname']]);
            if ($course && \core_course_category::can_view_course_info($course)) {
                return $course->id;
            }
        }
        if (!empty($identifier['idnumber'])) {
            $course = $DB->get_record('course', ['idnumber' => $identifier['idnumber']]);
            if ($course && \core_course_category::can_view_course_info($course)) {
                if (!empty($identifier['shortname'])) {
                    $this->log_mapping_notice('idnumbermapping', $identifier, ['courseid' => $course->id]);
                }
                return $course->id;
            }
        }

        return $this->create_missing_if_needed($identifier);
    }

    /**
     * When conflict resolution settings allow and position does not exist, create a new one
     *
     * @param array $identifier
     * @return int|null
     */
    protected function create_missing_if_needed(array $identifier): ?int {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        if ($this->get_conflict_resolution_setting('action', 'skip') !== 'create') {
            return null;
        }
        if (empty($identifier['shortname'])) {
            // Can not create a course if shortname is missing.
            return null;
        }
        $catid = $this->get_conflict_resolution_setting('catid');
        if (!($cats = \core_course_category::make_categories_list('moodle/course:create'))
                || !array_key_exists($catid, $cats)) {
            return null;
        }
        if ($this->can_fully_apply_conflict_resolutions()) {
            $data = array_intersect_key($identifier, ['shortname' => 1, 'fullname' => 1, 'idnumber' => 1]);
            // TODO: should shortname conflicts be resolved before changing fullname to match it?
            if (!array_key_exists('fullname', $data)) {
                $data['fullname'] = $data['shortname'];
            }
            // There can be shortname conflict if the course exists but is not visible to the user.
            $data['shortname'] = $this->find_unique_course_shortname($identifier['shortname']);
            $data['category'] = $catid;
            $data['visible'] = 1;
            $data['format'] = get_config('moodlecourse', 'format');
            $course = create_course((object)$data);
            $this->log_mapping_notice('coursecreated', $identifier,
                ['courseid' => $course->id, 'fullname' => $data['fullname']]);
            return $course->id;
        } else {
            return -1;
        }
    }

    /**
     * Increment the course shortname until we find a unique value.
     *
     * @param string $shortname
     * @return string|null
     */
    protected function find_unique_course_shortname(string $shortname) {
        return helper::find_unique_value_for_field($shortname, function($value) {
            global $DB;
            return $DB->record_exists('course', ['shortname' => $value]);
        });
    }

    /**
     * Add form elements to the conflict resolution form
     *
     * Use $this->get_conflict_form_element_name() to generate name for the form elements
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param array $importedentities list of entities that can not be imported because of this error (array of strings)
     * @param array $identifiers array of identifiers of the entities that can not be found (array or arrays),
     *     mapper may decide to list them in the conflict resolution form
     * @param bool $addskipaction  can be used by overridding methods when calling parent
     */
    public function add_to_conflict_form(import_conflict_form $form, array $importedentities,
                                         array $identifiers, bool $addskipaction = true): void {

        parent::add_to_conflict_form($form, $importedentities, $identifiers);

        // Option to create empty courses.
        if ($cats = \core_course_category::make_categories_list('moodle/course:create')) {
            $mform = $form->get_quick_form();
            $key = $this->get_conflict_form_element_name();
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution(['action' => 'create']), 'create');
            $key2 = $this->get_conflict_form_element_name('catid');
            $mform->addElement('select', $key2, get_string('importconflictincategory', 'tool_wp'), $cats);
            $mform->hideIf($key2, $key, 'ne', 'create');
            if ($tenantid = $this->get_import_tenant_id()) {
                $mform->setDefault($key2, tenancy::get_tenants()[$tenantid]->categoryid);
            }
        }
    }

}
