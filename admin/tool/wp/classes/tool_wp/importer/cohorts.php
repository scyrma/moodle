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
 * Cohorts importer
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * Importer class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohorts extends importer_base {

    /** @var string */
    public const IMPORT_CONTENT = 'import_content';
    /** @var string */
    public const IMPORT_USERS = 'import_users';
    /** @var string Element for selecting what to import. */
    public const IMPORT_INSTANCES = 'import_instances';
    /** @var int Element for import all cohorts. */
    public const IMPORT_INSTANCES_ALL = 'all';
    /** @var int Element for import manually selected cohorts. */
    public const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to import. */
    public const IMPORT_SELECT_COHORTS = 'select_cohorts';
    /** @var string Element for selecting course category. */
    public const IMPORT_SELECT_CATEGORY = 'select_category';
    /** @var string */
    public const IMPORT_CONTEXT = 'import_context';
    /** @var string */
    public const IMPORT_CONTEXT_SYSTEM = 'import_context_system';
    /** @var string */
    public const IMPORT_CONTEXT_CATEGORY = 'import_context_category';

    /**
     * Importer format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('cohorts', 'cohort');
    }

    /**
     * Does this importer "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Importer icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/workplace-file', 'theme')->out(false);
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) &&
            (has_capability('moodle/cohort:manage', \context_system::instance()) ||
                \core_course_category::has_capability_on_any('moodle/cohort:manage'));
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities, potential errors and potential notices by calling
     * $this->register_entity()
     * $this->register_potential_errors()
     * $this->register_potential_notices()
     */
    protected function initialise() {
        $this->register_entity('cohort', [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['url'] = (new \moodle_url('/cohort/edit.php', ['id' => $id]))->out();
                return get_string('importlogsuccesscohort', 'tool_wp',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                return get_string('importlogfailedcohort', 'tool_wp', (object)$importerdetails);
            },
            self::ENTITY_NAMEPLURAL => get_string('cohorts', 'cohort'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from programs and tenants import.
                if ($settings[self::IMPORT_CONTEXT] != self::IMPORT_CONTEXT_SYSTEM &&
                    $settings[self::IMPORT_CONTEXT] != self::IMPORT_CONTEXT_CATEGORY) {
                    throw new \coding_exception('Import cohort context is missing');
                }

                $defaults = [
                    self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_USERS => 1,
                    self::IMPORT_SELECT_CATEGORY => 0,
                    self::IMPORT_CONTEXT => self::IMPORT_CONTEXT_SYSTEM,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::IMPORT_SELECT_COHORTS => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return format_string($logdetails['name']);
            },
        ]);

        $this->register_entity('cohort_members', [
            self::ENTITY_DEPENDENCIES => ['cohort', 'user'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['name'] = format_string($importerdetails['cohortname']);
                return get_string('importlogsuccesscohortallocations', 'tool_wp',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                $a = (object)[
                    'originaluserfullname' => $importerdetails['originaluserfullname'],
                    'name' => format_string($importerdetails['cohortname']),
                ];
                return get_string('errorcouldnotallocatecohort', 'tool_wp', $a);
            },
            self::ENTITY_NAMEPLURAL => get_string('cohortmembers', 'tool_wp'),
        ]);

        $this->register_potential_error('cohort', 'idnumberconflict',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('errorcohortsameidnumber', 'tool_wp',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorcohortsameidnumber', 'tool_wp'),
                self::ERROR_CONFLICTSOLUTION => function(array $settings, bool $forform) {
                    if ($settings['action'] === 'increment') {
                        return get_string('conflictidnumber', 'tool_wp');
                    }
                    return null;
                },
            ]);

        $this->register_potential_notice('cohort', 'idnumberchanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('idnumberchanged', 'tool_wp', $a);
                }
            ]);
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;

        $data = $this->get_import_settings();
        $settings = [];

        $settingsstr = get_string('cohortdetails', 'tool_wp');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        $str = get_string('cohortmembers', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::IMPORT_USERS])];

        // Show destination category name.
        if ($data[self::IMPORT_CONTEXT] === self::IMPORT_CONTEXT_CATEGORY) {
            if (!empty($data[self::IMPORT_SELECT_CATEGORY])) {
                $category = \core_course_category::get($data[self::IMPORT_SELECT_CATEGORY], IGNORE_MISSING);
                $categoryname = $category ? $category->get_formatted_name() : '';
            }
            $str = get_string('selectedcoursecategory', 'tool_wp', $categoryname);
            $settings[] = ['name' => $str, 'value' => !empty($data[self::IMPORT_SELECT_CATEGORY])];
        }

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Add settings to the import form (Step 3. Options, "what to import")
     *
     * To retrive QuickForm:
     * $mform = $form->get_quick_form()
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'headercontent', get_string('content', 'tool_wp'));
        $mform->setExpanded('headercontent');

        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, get_string('cohortdetails', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_CONTENT, 'cohortdetails', 'tool_wp');
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        $mform->addElement('advcheckbox', self::IMPORT_USERS, get_string('cohortmembers', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_USERS, 'cohortmembers', 'tool_wp');
        $mform->setDefault(self::IMPORT_USERS, 1);

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectall = get_string('selectallcohortsinthisfile', 'tool_wp');
        $selectcourses = get_string('selectmanually', 'tool_wp');
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectall, self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectcourses, self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        $cohortsinfile = array_map('format_string',
            $this->get_entities_in_workplace_export_file('cohort')->get_menu('name'));
        \core_collator::asort($cohortsinfile);

        // Cohorts picker.
        $mform->addElement('autocomplete', self::IMPORT_SELECT_COHORTS, get_string('cohorts', 'cohort'),
            $cohortsinfile, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_COHORTS, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_COHORTS, self::IMPORT_INSTANCES, 'noteq', self::IMPORT_INSTANCES_SELECTED);

        $mform->addElement('header', 'destination', get_string('context', 'role'));
        $mform->setExpanded('destination');

        $systemcontextstr = get_string('importallsystemcontext', 'tool_wp');
        $categorycontextstr = get_string('importallselectedcategory', 'tool_wp');

        // Import all in system context. Should be available only when user can manage context system cohorts.
        if (has_capability('moodle/cohort:manage', \context_system::instance())) {
            $mform->addElement('radio', self::IMPORT_CONTEXT, null, $systemcontextstr, self::IMPORT_CONTEXT_SYSTEM);
        }

        // Import all in the selected category.
        $mform->addElement('radio', self::IMPORT_CONTEXT, null, $categorycontextstr, self::IMPORT_CONTEXT_CATEGORY);

        // Destination category picker. If tenant is specified only show categories for this tenant.
        $tenantid = $this->get_import_tenant_id();
        if (!$tenantid) {
            $categories = ['' => ''] + \core_course_category::make_categories_list('moodle/cohort:manage');
        } else {
            $categories = self::make_tenant_categories_list($tenantid);
        }

        $mform->addElement('select', self::IMPORT_SELECT_CATEGORY, get_string('selectcoursecategory', 'tool_wp'), $categories)
            ->setHiddenLabel(true);
        if ($tenantid && ($tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid)) {
            $mform->setDefault(self::IMPORT_SELECT_CATEGORY, $tenantcategory);
        }
        $mform->setDefault(self::IMPORT_CONTEXT, self::IMPORT_CONTEXT_CATEGORY);
        $mform->hideIf(self::IMPORT_SELECT_CATEGORY, self::IMPORT_CONTEXT, 'noteq', self::IMPORT_CONTEXT_CATEGORY);

        // Manage course categories link. This static element needs a group to be able to use hideIf method.
        // TODO Remove group after MDL-66251 is fixed.
        $group = [];
        $manageurl = new \moodle_url('/course/management.php');
        $html = \html_writer::link($manageurl, get_string('managecoursecategories', 'tool_wp'));
        $group[] = $mform->createElement('static', 'managecategories', '', $html);
        $mform->addGroup($group, 'selectcatgroup', '', '', false);
        $mform->hideIf('selectcatgroup', self::IMPORT_CONTEXT, 'noteq', self::IMPORT_CONTEXT_CATEGORY);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Returns the list of available tenant categories for this user
     *
     * @param int $tenantid
     * @return array List of strings with categories and subcategories
     */
    public static function make_tenant_categories_list(int $tenantid): array {
        $tenant = new tenant($tenantid);
        $categoryid = $tenant->get('categoryid');
        if (!$categoryid) {
            return [];
        }
        $tenantcategory = \core_course_category::get($categoryid);
        $allcategories = \core_course_category::make_categories_list('moodle/cohort:manage');
        $categories[$tenantcategory->id] = $tenantcategory->get_formatted_name();
        foreach ($tenantcategory->get_children() as $category) {
            $categories[$category->id] = $allcategories[$category->id];
        }

        return $categories;
    }

    /**
     * Summary of entities included in the workplace file (human-readable), displayed in the "Step 2 General settings"
     *
     * Returns array of strings, where each string will be displayed as a separate 'static' element
     * in the form.
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        $overview = [];
        if ($count = $this->get_entities_in_workplace_export_file('cohort')->count()) {
            $overview[] = get_string('cohorts', 'cohort') .
                get_string('entitiescountpostfix', 'tool_wp', $count);
        }
        if ($count = $this->get_entities_in_workplace_export_file('cohort_members')->count()) {
            $overview[] = get_string('cohortmembers', 'tool_wp') .
                get_string('entitiescountpostfix', 'tool_wp', $count);
        }
        return $overview;
    }

    /**
     * Perform some extra moodle validation. Called from $form->add_validation_callback();.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if (empty($data[self::IMPORT_INSTANCES])) {
            $errors[self::IMPORT_INSTANCES] = get_string('required');
        } else if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED
            && empty($data[self::IMPORT_SELECT_COHORTS])) {
            $errors[self::IMPORT_SELECT_COHORTS] = get_string('selectatleastonecohort', 'tool_wp');
        }

        // Check that user can manage cohorts on the selected destination course category.
        if ($data[self::IMPORT_CONTEXT] == self::IMPORT_SELECT_CATEGORY && (empty($data[self::IMPORT_SELECT_CATEGORY]) ||
            !($coursecategoryctx = \context_coursecat::instance($data[self::IMPORT_SELECT_CATEGORY], IGNORE_MISSING)) ||
            !has_capability('moodle/cohort:manage', $coursecategoryctx))) {
            $errors[self::IMPORT_SELECT_CATEGORY] = get_string('nopermissioncategoryimport', 'tool_wp');
        }

        return $errors;
    }

    /**
     * Perform the import
     *
     * @param string $entity
     * @return void
     */
    public function perform_import(string $entity): void {
        if ($entity === 'cohort') {
            $this->import_cohorts();
        }
    }

    /**
     * Import cohorts
     */
    protected function import_cohorts(): void {
        $settingimporttype = $this->get_import_setting(self::IMPORT_INSTANCES);

        if ($settingimporttype === null) {
            return;
        }

        // Check if we are importing all cohorts in the file or custom ones set on the import form.
        $filter = ($settingimporttype === self::IMPORT_INSTANCES_ALL) ?
            [] : $this->get_import_setting(self::IMPORT_SELECT_COHORTS);
        $cohorts = $this->get_entities_in_workplace_export_file('cohort', static function(array $entity) use ($filter) {
            return (empty($filter) || in_array($entity['id'], $filter));
        });

        foreach ($cohorts as $cohort) {
            $cohort
                ->set_validation_callback(function($data, wp_imported_entity $caller) {
                    $this->add_details_to_log(['name' => $data['name'], 'originalidnumber' => $data['idnumber'],
                        'originalname' => $data['name']]);

                    // Make sure idnumber is unique.
                    if (!empty($data['idnumber']) && $this->idnumber_taken($data['idnumber'])) {
                        $this->add_error_to_log('idnumberconflict');
                    }
                })
                ->set_import_callback(function($data, wp_imported_entity $entity) {
                    if ($this->idnumber_taken($data['idnumber'])) {
                        $data['idnumber'] = $this->get_new_idnumber($data, $entity);
                    }

                    $importcontext = $this->get_import_setting(self::IMPORT_CONTEXT);
                    if ($importcontext == self::IMPORT_CONTEXT_SYSTEM) {
                        $data['contextid'] = \context_system::instance()->id;
                    } else if ($importcontext == self::IMPORT_CONTEXT_CATEGORY) {
                        $categoryid = $this->get_import_setting(self::IMPORT_SELECT_CATEGORY) ?:
                            key(\core_course_category::make_categories_list('moodle/cohort:manage'));
                        $data['contextid'] = \context_coursecat::instance($categoryid)->id;
                    }

                    return cohort_add_cohort((object)$data);
                })
                ->import($this);

            if (!$this->is_collecting_errors() && !$cohort->get_new_id()) {
                continue;
            }

            // Check if cohort members need to be imported.
            if ($this->get_import_setting(self::IMPORT_USERS)) {
                $filter = static function(array $entity) use ($cohort): bool {
                    return $entity['cohortid'] == $cohort->get_original_id();
                };

                $cohortmembers = $this->get_entities_in_workplace_export_file('cohort_members', $filter);
                foreach ($cohortmembers as $cohortmember) {
                    $cohortmember
                        ->add_mapping('cohortid', 'cohort')
                        ->add_mapping('userid', 'user')
                        ->set_validation_callback(function ($data, wp_imported_entity $entity) use ($cohort) {
                            $this->add_details_to_log([
                                'cohortid' => $data['cohortid'],
                                'cohortname' => $cohort->get_raw_field('name'),
                                'userid' => $data['userid'],
                                'userfullname' => fullname(\core_user::get_user($data['userid'])),
                                'originaluserfullname' => $data['userfullname'],
                            ]);
                        })
                        ->set_import_callback(function ($data, wp_imported_entity $entity) {
                            global $DB;

                            cohort_add_member($data['cohortid'], $data['userid']);

                            return $DB->get_field('cohort_members', 'id', [
                                'cohortid' => $data['cohortid'],
                                'userid' => $data['userid'],
                            ]);
                        })->import($this);
                }
            }
        }
    }

    /**
     * Check if another cohort exists with the same idnumber
     *
     * @param string $idnumber
     * @return bool
     */
    private function idnumber_taken(string $idnumber): bool {
        global $DB;
        return $DB->record_exists('cohort', ['idnumber' => $idnumber]);
    }

    /**
     * Make sure that idnumber is unique, otherwise append a number to the end
     *
     * @param array $record
     * @param wp_imported_entity $caller
     * @return mixed|string
     */
    protected function get_new_idnumber(array $record, wp_imported_entity $caller) {
        $lookup = function($value) {
            return $this->idnumber_taken($value);
        };
        $useincrement = $this->get_conflict_resolution_setting('cohort', 'idnumberconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['idnumber'], $lookup, $useincrement);

        if ($newvalue !== $record['idnumber']) {
            $this->add_notice_to_log('idnumberchanged', ['from' => $record['idnumber'], 'to' => $newvalue]);
        }

        return $newvalue;
    }

    /**
     * Add the importer error to the conflict resolution form (Step 5. Conflicts)
     *
     * To retrieve QuickForm:
     * $mform = $form->get_quick_form();
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode code of an error that this importer raises using $this->add_error_to_log()
     * @param array $detailsarray array of arrays: for each occurrence of error stores the details that were logged
     *     in add_details_to_log()
     * @param bool $addskipaction  can be used by overridding methods when calling parent
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity,
                                         string $errorcode, array $detailsarray, bool $addskipaction = true): void {

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray, true);
        if ($errorcode === 'idnumberconflict') {
            $mform = $form->get_quick_form();
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution('cohort', $errorcode, ['action' => 'increment']), 'increment');
            $mform->setDefault($key, 'increment');
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }
}
