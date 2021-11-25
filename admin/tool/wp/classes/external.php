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
 * Web services
 *
 * @package     tool_wp
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_tenant\tenancy;
use tool_wp\local\exportimport\helper as exportimport_helper;
use tool_wp\local\exportimport\import_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * tool_wp external function
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_external extends external_api {

    /**
     * Parameters for the users selector WS.
     * @return external_function_parameters
     */
    public static function potential_users_selector_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
            'component' => new external_value(PARAM_COMPONENT, 'Caller component', VALUE_REQUIRED),
            'area' => new external_value(PARAM_ALPHANUMEXT, 'Component area', VALUE_REQUIRED),
            'itemid' => new external_value(PARAM_INT, 'Item id', VALUE_REQUIRED),
        ]);
    }

    /**
     * User selector.
     *
     * @param string $search
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return array
     */
    public static function potential_users_selector(string $search, string $component, string $area, int $itemid): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::potential_users_selector_parameters(),
            ['search' => $search, 'component' => $component, 'area' => $area, 'itemid' => $itemid]);
        $search = $params['search'];
        $component = $params['component'];
        $area = $params['area'];
        $itemid = $params['itemid'];

        $context = context_system::instance();
        self::validate_context($context);

        $queryparts = component_callback($component, 'potential_users_selector', [$area, $itemid]);
        if (!is_array($queryparts) || count($queryparts) != 3) {
            // It is expected that the caller component (or plugin) implements a callback that validates user
            // access to the user search and also returns the array [$join, $where, $params] to be used as a subquery.
            // Typical implementation will be to check capability and return \tool_wp\tenancy::get_users_sql('u') .
            throw new coding_exception('User selector is not supported by the plugin');
        }

        list($join, $where, $params) = $queryparts;

        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        // TODO Does not support custom user profile fields (MDL-70456).
        $extrasearchfields = \core_user\fields::get_identity_fields($context, false);
        if (in_array('email', $extrasearchfields)) {
            $fields .= ', u.email';
        } else {
            $fields .= ', null AS email';
        }

        list($wheresql, $whereparams) = users_search_sql($search, 'u', true, $extrasearchfields);
        $query = "SELECT u.id, $fields
            FROM {user} u $join
            WHERE ($where) AND $wheresql";
        $params += $whereparams;

        list($sortsql, $sortparams) = users_order_by_sql('u', $search, $context);
        $query .= " ORDER BY {$sortsql}";
        $params += $sortparams;

        $result = $DB->get_records_sql($query, $params);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
        if ($result) {
            $result = array_map(function($record) use ($viewfullnames) {
                return (object)[
                    'id' => $record->id,
                    'fullname' => fullname($record, $viewfullnames),
                    'email' => clean_param($record->email, core_user::get_property_type('email')),
                ];
            }, $result);
        }
        return $result;
    }

    /**
     * Return for User selector.
     * @return external_multiple_structure
     */
    public static function potential_users_selector_returns(): external_multiple_structure {
        global $CFG;
        require_once($CFG->dirroot . '/user/externallib.php');
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(core_user::get_property_type('id'),
                'ID of the user'),
            'fullname' => new external_value(core_user::get_property_type('firstname'),
                'The fullname of the user'),
            'email' => new external_value(core_user::get_property_type('email'),
                'An email address', VALUE_OPTIONAL),
        ]));
    }

    /**
     * Parameters for the get_tab_content WS.
     * @return external_function_parameters
     */
    public static function get_tab_content_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tab' => new external_value(PARAM_RAW_TRIMMED, 'Tab class', VALUE_REQUIRED),
            'jsondata' => new external_value(PARAM_RAW, 'Json-encoded data', VALUE_REQUIRED),
        ]);
    }

    /**
     * Tab content
     *
     * @param string $tabclass class of the tab
     * @param string $jsondata
     * @return array
     */
    public static function get_tab_content(string $tabclass, string $jsondata): array {
        global $PAGE, $OUTPUT, $DB;
        $params = self::validate_parameters(self::get_tab_content_parameters(),
            ['tab' => $tabclass, 'jsondata' => $jsondata]);
        $tabclass = $params['tab'];
        $data = @json_decode($params['jsondata'], true);

        $context = context_system::instance();
        self::validate_context($context);

        // Hack alert: Set a default URL to stop the annoying debug.
        $PAGE->set_url('/');
        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();

        if (class_exists($tabclass) && is_subclass_of($tabclass, \tool_wp\output\tab::class)) {
            $reads = $DB->perf_get_reads();
            $writes = $DB->perf_get_writes();

            /** @var \tool_wp\output\tab $tab */
            $tab = new $tabclass($data);
            $tab->require_access();
            $PAGE->start_collecting_javascript_requirements();

            $content = $tab->export_for_template($PAGE->get_renderer('core'));
            $jsfooter = $PAGE->requires->get_end_code();
            return [
                'template' => $tab->get_template(),
                'content' => json_encode($content),
                'javascript' => $jsfooter,
                'perffooter' => self::get_performance_footer($reads, $writes)
            ];
        }
        // For security reason we don't throw exception "class does not exist" but rather an access exception.
        throw new moodle_exception('nopermissiontab', 'tool_wp');
    }

    /**
     * Performance information of the tab
     *
     * @param int $initialreads to subtract from the number of db reads
     * @param int $initialwrites to subtract from the number of db writes
     * @return string
     */
    protected static function get_performance_footer(int $initialreads, int $initialwrites): string {
        global $CFG, $DB, $OUTPUT;

        // Provide some performance info if required.
        $performanceinfo = '';
        if (defined('MDL_PERF') || (!empty($CFG->perfdebug) and $CFG->perfdebug > 7)) {
            $perf = get_performance_info();
            if (defined('MDL_PERFTOFOOT') || debugging() || $CFG->perfdebug > 7) {
                $a = (object)['reads' => $DB->perf_get_reads() - $initialreads,
                    'writes' => $DB->perf_get_writes() - $initialwrites];
                $title = get_string('performanceinfo', 'tool_wp', $a);
                if ($a->reads + $a->writes > 30) {
                    $title .= $OUTPUT->pix_icon('i/caution', '');
                }
                $performanceinfo = html_writer::tag('details',
                    html_writer::tag('summary', $title) . $perf['html'],
                    ['class' => 'wptabperformance']);
            }
        }

        return $performanceinfo;
    }

    /**
     * Return for get_tab_content
     * @return external_single_structure
     */
    public static function get_tab_content_returns() {
        return new external_single_structure([
            'template' => new external_value(PARAM_PATH, 'Template name'),
            'content' => new external_value(PARAM_RAW, 'JSON-encoded data for template'),
            'javascript' => new external_value(PARAM_RAW, 'JavaScript fragment'),
            'perffooter' => new external_value(PARAM_RAW, 'Performance footer', VALUE_OPTIONAL, ''),
        ]);
    }

    /**
     * Parameters for modal_form
     * @return external_function_parameters
     */
    public static function modal_form_parameters(): external_function_parameters {
        return new external_function_parameters([
            'form' => new external_value(PARAM_RAW_TRIMMED, 'Form class', VALUE_REQUIRED),
            'formdata' => new external_value(PARAM_RAW, 'url-encoded form data', VALUE_REQUIRED),
        ]);
    }

    /**
     * Submit a form from a modal dialogue.
     *
     * @deprecated since Moodle 3.11, use core modules, see https://docs.moodle.org/dev/Modal_and_AJAX_forms
     *
     * @param string $formclass
     * @param string $formdatastr
     * @return array
     */
    public static function modal_form(string $formclass, string $formdatastr): array {
        global $PAGE, $OUTPUT;
        $params = self::validate_parameters(self::modal_form_parameters(), [
            'form' => $formclass,
            'formdata' => $formdatastr,
        ]);
        $formclass = $params['form'];
        parse_str($params['formdata'], $formdata);

        if (!class_exists($formclass) || !is_subclass_of($formclass, \tool_wp\modal_form::class)) {
            // For security reason we don't throw exception "class does not exist" but rather an access exception.
            throw new \moodle_exception('nopermissionform', 'tool_wp');
        }

        /** @var \tool_wp\modal_form $form */
        $form = new $formclass(null, null, 'post', '', [], true, $formdata, true);
        $form->set_data_for_modal();
        if (!$form->is_cancelled() && $form->is_submitted() && $form->is_validated()) {
            // Form was properly submitted, process and return results of processing.
            // Note, when form is submitted we do not return the form html because it will not be correct,
            // for example, if the element was created as a result of form submission the "id" in the form will be still zero.
            // If the caller needs to re-render the form after submission it has to send a new request.
            return ['submitted' => true, 'data' => json_encode($form->process($form->get_data()))];
        }

        // Render actual form.
        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();
        $data = $form->render();
        $jsfooter = $PAGE->requires->get_end_code();
        $output = ['submitted' => false, 'html' => $data, 'javascript' => $jsfooter];
        return $output;
    }

    /**
     * Return for modal_form
     * @return external_single_structure
     */
    public static function modal_form_returns() {
        return new external_single_structure(
            array(
                'submitted' => new external_value(PARAM_BOOL, 'If form was submitted and validated'),
                'data' => new external_value(PARAM_RAW, 'JSON-encoded return data from form processing method', VALUE_OPTIONAL),
                'html' => new external_value(PARAM_RAW, 'HTML fragment of the form', VALUE_OPTIONAL),
                'javascript' => new external_value(PARAM_RAW, 'JavaScript fragment of the form', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * Mark the tool_wp_modal_form web service as deprecated.
     *
     * @return  bool
     */
    public static function modal_form_is_deprecated() {
        return true;
    }

    /**
     * Parameters for delete_export
     *
     * @return external_function_parameters
     */
    public static function delete_export_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Deletes an export
     *
     * @param int $id
     * @return bool
     */
    public static function delete_export(int $id): bool {
        // Parameter validation.
        $params = self::validate_parameters(self::delete_export_parameters(), [
            'id' => $id,
        ]);
        $id = $params['id'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        \tool_wp\permission::require_can_delete_export($id);

        return exportimport_helper::delete_export($id);
    }

    /**
     * Return for delete_export.
     *
     * @return external_value
     */
    public static function delete_export_returns(): external_value {
        return new external_value(PARAM_BOOL);
    }

    /**
     * Parameters for delete_import
     *
     * @return external_function_parameters
     */
    public static function delete_import_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Deletes an import
     *
     * @param int $id
     * @return bool
     */
    public static function delete_import(int $id): bool {
        // Parameter validation.
        $params = self::validate_parameters(self::delete_import_parameters(), [
            'id' => $id,
        ]);
        $id = $params['id'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        \tool_wp\permission::require_can_delete_import($id);

        return exportimport_helper::delete_import($id);
    }

    /**
     * Return for delete_import.
     *
     * @return external_value
     */
    public static function delete_import_returns(): external_value {
        return new external_value(PARAM_BOOL);
    }

    /**
     * Parameters for export
     *
     * @return external_function_parameters
     */
    public static function export_parameters(): external_function_parameters {
        return new external_function_parameters([
            'exportid' => new external_value(PARAM_INT, 'For existing export - its id', VALUE_DEFAULT, 0),
            'newexportparams' => new external_value(PARAM_RAW, 'Configuration for new exports, JSON-encoded',
                VALUE_DEFAULT, null, true),
        ]);
    }

    /**
     * Displays configuration forms, schedules export and displays information about export
     *
     * @param int $exportid
     * @param string|null $newexportparams
     */
    public static function export(?int $exportid, ?string $newexportparams) {
        global $PAGE, $OUTPUT;
        // Parameter validation.
        $params =
            self::validate_parameters(self::export_parameters(), [
                'exportid' => $exportid,
                'newexportparams' => $newexportparams
            ]);
        $exportid = $params['exportid'] ?: 0;
        $newexportparams = $exportid ? [] : (@json_decode($params['newexportparams'], true) ?? []);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        \tool_wp\permission::require_can_use_export_import();
        if ($exportid) {
            \tool_wp\permission::require_can_view_export($exportid);
        }

        // Hack alert: Set a default URL to stop the annoying debug.
        $PAGE->set_url('/');
        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();
        $obj = new \tool_wp\local\exportimport\export($exportid, $newexportparams);
        $content = $obj->export_for_template($PAGE->get_renderer('core'));
        $jsfooter = $PAGE->requires->get_end_code();
        return [
            'javascript' => $jsfooter,
            'content' => json_encode($content),
            'url' => $exportid ? exportimport_helper::export_url($exportid)->out(false) : null,
        ];
    }

    /**
     * Return for export.
     *
     * @return external_single_structure
     */
    public static function export_returns() {
        return new external_single_structure(
            array(
                'content' => new external_value(PARAM_RAW, 'JSON-encoded data for template', VALUE_OPTIONAL),
                'javascript' => new external_value(PARAM_RAW, 'Collected JS', VALUE_OPTIONAL),
                'url' => new external_value(PARAM_LOCALURL, 'URL to set in the location bar', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * Parameters for import
     *
     * @return external_function_parameters
     */
    public static function import_parameters(): external_function_parameters {
        return new external_function_parameters([
            'importid' => new external_value(PARAM_INT, 'For existing import - its id', VALUE_DEFAULT, 0),
            'newimportparams' => new external_value(PARAM_RAW, 'Configuration for new imports, JSON-encoded',
                VALUE_DEFAULT, null, true),
        ]);
    }

    /**
     * Displays configuration forms, schedules import and displays information about import
     *
     * @param int $importid
     * @param string|null $newimportparams
     * @return array
     */
    public static function import(int $importid, ?string $newimportparams) {
        global $PAGE, $OUTPUT;
        // Parameter validation.
        $params =
            self::validate_parameters(self::import_parameters(), [
                'importid' => $importid,
                'newimportparams' => $newimportparams
            ]);
        $importid = $params['importid'] ?: 0;
        $newimportparams = (@json_decode($params['newimportparams'], true) ?? []);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        \tool_wp\permission::require_can_use_export_import();
        if ($importid) {
            $importmanager = new import_manager($importid);
            \tool_wp\permission::require_can_view_import($importid);
        } else {
            $importmanager = null;
        }

        // Hack alert: Set a default URL to stop the annoying debug.
        $PAGE->set_url('/');
        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();
        $obj = new \tool_wp\local\exportimport\import($importmanager, $newimportparams);
        $content = $obj->export_for_template($PAGE->get_renderer('core'));
        $jsfooter = $PAGE->requires->get_end_code();
        return [
            'javascript' => $jsfooter,
            'content' => json_encode($content),
            'url' => $importid ? exportimport_helper::import_url($importid)->out(false) : null
        ];
    }

    /**
     * Return for import.
     *
     * @return external_single_structure
     */
    public static function import_returns() {
        return new external_single_structure(
            array(
                'content' => new external_value(PARAM_RAW, 'JSON-encoded data for template', VALUE_OPTIONAL),
                'javascript' => new external_value(PARAM_RAW, 'Collected JS', VALUE_OPTIONAL),
                'url' => new external_value(PARAM_LOCALURL, 'URL to set in the location bar', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * Parameters for get_export_status.
     *
     * @return external_function_parameters
     */
    public static function get_export_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'exportid' => new external_value(PARAM_INT, 'For existing export - its id')
        ]);
    }

    /**
     * Get export status and progress.
     *
     * @param int $exportid
     * @return array
     */
    public static function get_export_status(int $exportid) {
        // Parameter validation.
        $params = self::validate_parameters(self::get_export_status_parameters(), [
            'exportid' => $exportid
        ]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        \tool_wp\permission::require_can_view_export($params['exportid']);

        $exportmanager = new \tool_wp\local\exportimport\export_manager($params['exportid']);

        return [
            'status' => $exportmanager->get_export_status(),
            'statusstr' => \tool_wp\local\exportimport\helper::format_export_import_status($exportmanager->get_export_status()),
            'progress' => $exportmanager->get_export_progress()
        ];
    }

    /**
     * Return for get_export_status.
     *
     * @return external_single_structure
     */
    public static function get_export_status_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, 'Export status.'),
            'statusstr' => new external_value(PARAM_TEXT, 'Export status string'),
            'progress' => new external_value(PARAM_INT, 'Export progress in percentage.')
        ]);
    }

    /**
     * Parameters for get_import_status.
     *
     * @return external_function_parameters
     */
    public static function get_import_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'importid' => new external_value(PARAM_INT, 'For existing import - its id')
        ]);
    }

    /**
     * Get import status and progress.
     *
     * @param int $importid
     * @return array
     */
    public static function get_import_status(int $importid) {
        // Parameter validation.
        $params = self::validate_parameters(self::get_import_status_parameters(), [
            'importid' => $importid
        ]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        \tool_wp\permission::require_can_view_import($params['importid']);

        $importmanager = new import_manager($params['importid']);

        return [
            'status' => $importmanager->get_import_status(),
            'statusstr' => \tool_wp\local\exportimport\helper::format_export_import_status($importmanager->get_import_status()),
            'progress' => $importmanager->get_import_progress()
        ];
    }

    /**
     * Return for get_import_status.
     *
     * @return external_single_structure
     */
    public static function get_import_status_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, 'Import status code.'),
            'statusstr' => new external_value(PARAM_TEXT, 'Import status string'),
            'progress' => new external_value(PARAM_INT, 'Import progress in percentage.')
        ]);
    }
}
