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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * tool_wp data generator.
 *
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\forms\import_base_form;

defined('MOODLE_INTERNAL') || die();

/**
 * tool_wp data generator class.
 *
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_generator extends component_generator_base {

    /**
     * Performs an export with specified options
     *
     * @param string $exporterclass
     * @param array $options
     * @param bool $expecterrors do not throw exception if export fails
     * @return int export id
     * @throws coding_exception
     */
    public function perform_export(string $exporterclass, array $options = [], bool $expecterrors = false): int {
        $options['exporter'] = $exporterclass;
        $exportid = \tool_wp\local\exportimport\export_manager::schedule_export($options, false);
        $exportmanager = new export_manager($exportid);
        $exportmanager->perform_export();
        $errors = $exportmanager->get_export_errors();
        if ($expecterrors && !$errors) {
            throw new coding_exception('Errors were expected but export succeded');
        }
        if (!$expecterrors && $errors) {
            $messages = array_column($exportmanager->get_export_errors(), 'message');
            throw new coding_exception('Export produced unexpected errors: ' . join(', ', $messages));
        }
        return $exportid;
    }

    /**
     * Prepares and performs an import with specified options
     *
     * @param int $exportid
     * @param array $options
     * @return int
     */
    public function perform_import_from_export_id(int $exportid, array $options = []): int {
        $importid = $this->prepare_import_from_export_id($exportid, $options);
        return $this->perform_import($importid);
    }

    /**
     * Prepares the import from the export id
     *
     * This function can be used in tests that want to check/set settings and check conflicts
     * before actually performing the import
     *
     * @param int $exportid
     * @param array $options
     * @return int
     */
    public function prepare_import_from_export_id(int $exportid, array $options = []): int {
        $importerkeys = ['entrypoint' => 1, 'entrypointid' => 1];
        $generalsettingskeys = ['importer' => 1, 'tenantid' => 1];
        $importmanager = import_manager::create_import_from_exportfile(
            array_intersect_key($options, $importerkeys), $exportid);

        if ($generalsettings = array_intersect_key($options, $generalsettingskeys)) {
            $importmanager->save_general_settings($generalsettings);
        }
        if ($settings = array_diff_key($options, $importerkeys, $generalsettingskeys)) {
            // Some tests want to make sure that settings are not set after calling this method.
            // Only set settings if they are present.
            $importmanager->save_settings($settings);
        }
        return $importmanager->get_import_id();
    }

    /**
     * Perform import that was already prepared
     *
     * @param int $importid
     * @return int
     */
    public function perform_import(int $importid): int {
        $importmanager = new import_manager($importid);

        // Make sure that settings are set even if there are no settings.
        $importmanager->save_settings($importmanager->get_settings());

        // Raise a warning about conflicts if the conflict resolution was not set (like UI would do).
        if ($importmanager->has_conflicts() && !$importmanager->has_conflicts_settings()) {
            throw new coding_exception('Not all conflicts have settings');
        }

        // Schedule and perform the import.
        $importmanager->schedule_import(false);
        (new import_manager($importid))->perform_import();
        return $importid;
    }

    /**
     * Perform import from a file on disk
     *
     * @param string $filepath
     * @param array $options
     * @return int
     * @throws coding_exception
     */
    public function perform_import_from_file(string $filepath, array $options = []): int {
        $importid = $this->prepare_import_from_file($filepath, $options);
        return $this->perform_import($importid);
    }

    /**
     * Prepares an import from a file on disk
     *
     * @param string $filepath
     * @param array $options
     * @return int
     */
    public function prepare_import_from_file(string $filepath, array $options = []): int {
        global $USER;
        $importerkeys = ['entrypoint' => 1, 'entrypointid' => 1];
        $generalsettingskeys = ['importer' => 1, 'tenantid' => 1];
        // Add file to user draft file area.
        $fs = get_file_storage();
        $itemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs->create_file_from_pathname(['component' => 'user', 'filearea' => 'draft',
            'contextid' => $usercontext->id, 'itemid' => $itemid, 'filepath' => '/',
            'filename' => basename($filepath)], $filepath);
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_draftfile(
            array_intersect_key($options, $importerkeys), $itemid);
        $importid = $importmanager->get_import_id();
        if ($generalsettings = array_intersect_key($options, $generalsettingskeys)) {
            $importmanager->save_general_settings($generalsettings);
        }
        if ($settings = array_diff_key($options, $importerkeys, $generalsettingskeys)) {
            // Some tests want to make sure that settings are not set after calling this method.
            // Only set settings if they are present.
            $importmanager->save_settings($settings);
        }
        return $importid;
    }

    /**
     * Method to test mapping independently from any import process
     *
     * @param string $entity entity to locate ('user', 'course', 'tool_program', etc)
     * @param array $identifier identifier, for example ['idnumber' => 'IDNUMBER']
     * @return array array of [$locatedid, $notices, $errors, $isvalidated]
     */
    public function locate_mapping(string $entity, array $identifier): array {
        $importmanager = new \tool_wp\local\exportimport\import_manager();
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $logpersistent->set('data', ['entityname' => 'dummy']);
        $importmanager->set_current_import_detail_persistent($logpersistent);
        $locatedid = $importmanager->locate_mapping($entity, $identifier);
        $result = [
            $locatedid,
            $logpersistent->get_formatted_notices(null),
            $logpersistent->get_formatted_errors(null),
            $logpersistent->is_validated(),
        ];
        return $result;
    }

    /**
     * Method to test mapping applying conflict resolution independently from any import process
     *
     * Note that this method may require current user to be set and have sufficient privileges,
     * for example to create missing entities
     *
     * @param string $entity entity to locate ('user', 'course', 'tool_program', etc)
     * @param array $identifier identifier, for example ['idnumber' => 'IDNUMBER']
     * @param array $settings conflict resolution settings without full names, for example ['action' => 'create']
     * @return array array of [$locatedid, $notices, $errors, $conflictsreview, $isvalidated]
     */
    public function locate_mapping_with_conflict_resolution(string $entity, array $identifier, array $settings = []): array {
        $filepath = make_request_directory() . '/dummy.zip';
        file_put_contents($filepath, '');

        $options = [];
        foreach ($settings as $key => $value) {
            $options[\tool_wp\local\exportimport\helper::get_setting_name_for_conflict_form($entity, $key)] = $value;
        }
        $importid = $this->prepare_import_from_file($filepath, $options);

        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $logpersistent->set('data', ['entityname' => 'dummy']);
        $importmanager->set_current_import_detail_persistent($logpersistent);

        // Set value of protected property $importmanager->allowconflictresolution to true
        // so we can test the mapper conflict resolution separately from importing any actual entity.
        $imreflection = new \ReflectionClass(import_manager::class);
        $allowconflictresolution = $imreflection->getProperty('allowconflictresolution');
        $allowconflictresolution->setAccessible(true);
        $allowconflictresolution->setValue($importmanager, true);

        $locatedid = $importmanager->locate_mapping($entity, $identifier);
        $result = [
            $locatedid,
            $logpersistent->get_formatted_notices(null),
            $logpersistent->get_formatted_errors(null),
            $importmanager->get_conflicts_review($options, null),
            $logpersistent->is_validated(),
        ];
        return $result;
    }

    /**
     * Returns logs about the performed import
     *
     * @param int $importid
     * @return array array of arrays where each element has keys: 'detail' (string), 'errors' (array of strings) and
     *     'notices' (array of strings)
     */
    public function get_import_logs(int $importid) {
        $importmanager = new import_manager($importid);
        return $importmanager->get_import_logs();

    }

    /**
     * Returns conflicts review for the performed import
     *
     * @param int $importid
     * @return array
     */
    public function get_import_conflict_review(int $importid): array {
        $importmanager = new import_manager($importid);
        return $importmanager->get_importer()->get_conflicts_review();
    }

    /**
     * Tests import form submission
     *
     * Examples:
     *   $form = $wpgenerator->submit_import_form($importid, 2, []);
     *
     *   if (!$form->is_validated()) {
     *       $form->get_quick_form()->getElementError($elementname);
     *       $form->get_quick_form()->_errors
     *   } else {
     *       $form->process($form->get_data())
     *   }
     *
     * @param int $importid
     * @param int $step
     * @param array $submitteddata
     * @return import_base_form
     * @throws coding_exception
     */
    public function submit_import_form(int $importid, int $step, array $submitteddata = []): import_base_form {
        global $USER;
        /** @var import_base_form $formclass */
        $formclass = null;
        if ($step == 2) {
            $formclass = \tool_wp\local\exportimport\forms\import_general_form::class;
        } else if ($step == 3) {
            $formclass = \tool_wp\local\exportimport\forms\import_settings_form::class;
        } else if ($step == 4) {
            $formclass = \tool_wp\local\exportimport\forms\import_conflict_form::class;
        } else if ($step == 5) {
            $formclass = \tool_wp\local\exportimport\forms\import_review_form::class;
        } else {
            throw new coding_exception('Form step must be between 2 and 5');
        }
        $submitteddata['importid'] = $importid;

        // Now mock the form submission.
        $formidentifier = str_replace('\\', '_', $formclass);
        $submitteddata['_qf__' . $formidentifier] = 1;
        $oldignoresesskey = $USER->ignoresesskey ?? null;
        $USER->ignoresesskey = true;
        /** @var import_base_form $form */
        $form = new $formclass(null, null, 'post', '', [], true, $submitteddata, true);
        $USER->ignoresesskey = $oldignoresesskey;

        $form->set_data_for_modal();
        return $form;
    }
}
