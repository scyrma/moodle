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
 * Web services
 *
 * @package     tool_wp
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * tool_wp external function
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        $fields = get_all_user_name_fields(true, 'u');

        $extrasearchfields = array();
        if (!empty($CFG->showuseridentity) && has_capability('moodle/site:viewuseridentity', $context)) {
            $extrasearchfields = explode(',', $CFG->showuseridentity);
        }
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
                return (object)['id' => $record->id, 'fullname' => fullname($record, $viewfullnames), 'email' => $record->email];
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
        global $PAGE, $OUTPUT;
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
            /** @var \tool_wp\output\tab $tab */
            $tab = new $tabclass($data);
            $tab->require_access();
            $PAGE->start_collecting_javascript_requirements();

            $content = $tab->export_for_template($PAGE->get_renderer('core'));
            $jsfooter = $PAGE->requires->get_end_code();
            return [
                'template' => $tab->get_template(),
                'content' => json_encode($content),
                'javascript' => $jsfooter
            ];
        }
        // For security reason we don't throw exception "class does not exist" but rather an access exception.
        throw new moodle_exception('nopermissiontab', 'tool_wp');
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
}