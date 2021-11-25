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
 * Plugin callbacks.
 *
 * @package     tool_wp
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get icon mapping for font-awesome.
 */
function tool_wp_get_fontawesome_icon_map() {
    return [
        'tool_wp:angle-left' => 'fa-angle-left',
        'tool_wp:angle-right' => 'fa-angle-right',
        'tool_wp:angle-down' => 'fa-angle-down',
        'tool_wp:angle-up' => 'fa-angle-up',
        'tool_wp:archive' => 'fa-archive',
        'tool_wp:arrow-circle-left' => 'fa-arrow-circle-left',
        'tool_wp:bar-chart' => 'fa-bar-chart',
        'tool_wp:bookmark' => 'fa-bookmark',
        'tool_wp:calendar-o' => 'fa-calendar-o',
        'tool_wp:check-circle-o' => 'fa-check-circle-o',
        'tool_wp:exclamation-triangle' => 'fa-exclamation-triangle',
        'tool_wp:folder-open-o' => 'fa-folder-open-o',
        'tool_wp:hourglass-end' => 'fa-hourglass-end',
        'tool_wp:info-circle' => 'fa-info-circle',
        'tool_wp:paper-plane-o' => 'fa-paper-plane-o',
        'tool_wp:play-circle' => 'fa-play-circle',
        'tool_wp:plus-circle' => 'fa-plus-circle',
        'tool_wp:restorearchived' => 'fa-repeat',
        'tool_wp:sort-amount-asc' => 'fa-sort-amount-asc',
        'tool_wp:sort-amount-desc' => 'fa-sort-amount-desc',
        'tool_wp:step-backward' => 'fa-step-backward',
        'tool_wp:toggle-off' => 'fa-toggle-off',
        'tool_wp:toggle-on' => 'fa-toggle-on',
        'tool_wp:upload' => 'fa-upload',
        'tool_wp:userpreferences' => 'fa-sliders',
    ];
}

/**
 * Perform additional webservice validation to ensure the User-Agent doesn't match the standard Moodle app
 *
 * @param stdClass $function
 * @param array $params
 * @return false|mixed
 * @throws moodle_exception
 */
function tool_wp_override_webservice_execution(stdClass $function, array $params) {
    global $USER;

    $result = false;

    if (WS_SERVER) {
        $useragent = core_useragent::get_user_agent_string();

        if (preg_match('/MoodleMobile$/', $useragent)) {
            throw new moodle_exception('invaliddevice', 'tool_wp');
        }
    }

    // Add overrides to tool_mobile_get_config call.
    if ($function->name === 'tool_mobile_get_config') {
        $manager = new \tool_tenant\manager();

        // Call the original function.
        $result = call_user_func_array([$function->classname, $function->methodname], $params);

        // Add custom tenant CSS to tool_mobile_get_config call.
        $tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);
        $tenant = new \tool_tenant\tenant($tenantid);
        $cssconfig = $tenant->get('cssconfig');
        $cssitems = $cssconfig ? json_decode($cssconfig) : (object)[];

        foreach ($cssitems as $key => $value) {
            if (strpos($key, 'mform_isexpanded') !== 0) {
                $result['settings'][] = [
                    'name' => 'wp_tool_tenant_config_' . $key,
                    'value' => $value,
                ];
            }
        }

        if ($faviconurl = $manager->get_favicon($tenantid)) {
            $result['settings'][] = [
                'name' => 'wp_tool_tenant_config_favicon',
                'value' => $faviconurl->out(false),
            ];
        }

        if ($url = $manager->get_tenant_file_url($tenantid, 'headerlogo')) {
            $result['settings'][] = [
                'name' => 'wp_tool_tenant_config_headerlogo',
                'value' => $url,
            ];
        }

        if ($url = $manager->get_tenant_file_url($tenantid, 'loginlogo')) {
            $result['settings'][] = [
                'name' => 'wp_tool_tenant_config_loginlogo',
                'value' => $url,
            ];
        }

        if ($url = $manager->get_tenant_file_url($tenantid, 'loginbackground')) {
            $result['settings'][] = [
                'name' => 'wp_tool_tenant_config_loginbackground',
                'value' => $url,
            ];
        }

        // Add theme workplace settings to tool_mobile_get_config call.
        $result['settings'][] = [
            'name' => 'theme_workplace_dashboardlearning',
            'value' => get_config('theme_workplace', 'dashboardlearning'),
        ];
        $result['settings'][] = [
            'name' => 'theme_workplace_dashboardteams',
            'value' => get_config('theme_workplace', 'dashboardteams'),
        ];
        $result['settings'][] = [
            'name' => 'tool_wp_myoverviewdisplayonmobileonly',
            'value' => get_config('tool_wp', 'myoverviewdisplayonmobileonly'),
        ];
        $result['settings'][] = [
            'name' => 'theme_workplace_hideprogramcourses',
            'value' => get_config('theme_workplace', 'hideprogramcourses'),
        ];
        $result['settings'][] = [
            'name' => 'workplace_productionstate',
            'value' => get_config('moodle', 'workplaceproductionstate'),
        ];
    }

    return $result;
}

/**
 * Callback for the tool_wp_potential_users_selector web service.
 *
 * @param string $area
 * @param int $itemid
 * @return array
 */
function tool_wp_potential_users_selector(string $area, int $itemid): array {
    global $CFG;

    if (strcmp($area, 'users') !== 0) {
        return [];
    }

    if ($itemid == 0) {
        if (!\tool_tenant\permission::can_switch_tenant()) {
            return ['', '1=2', []];
        }

        return ['', 'id != :guest AND deleted = 0', ['guest' => $CFG->siteguest]];
    } else {
        $tenant = new \tool_tenant\tenant($itemid);
        if (!\tool_tenant\permission::can_browse_users($tenant->get('id'))) {
            return ['', '1=2', []];
        }

        return \tool_tenant\tenancy::get_users_sql('u', $tenant->get('id'));
    }
}

/**
 * Callback executed from setup.php, available from Moodle 3.8 in core
 */
function tool_wp_after_config() {
    global $CFG;
    if (during_initial_install() || isset($CFG->upgraderunning)) {
        return;
    }

    // Complete installation. This can only be executed after installation/upgrade process finished.
    if (!empty($CFG->tool_wp_installed)) {
        \tool_wp\install_hook::execute();
        unset_config('tool_wp_installed');
    }
}

/**
 * Serve the embedded files.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function tool_wp_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $PAGE;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    $PAGE->set_context($context);
    $itemid = array_shift($args);

    if ($filearea === 'export' && $itemid) {
        require_login();
        if (!\tool_wp\permission::can_view_export($itemid)) {
            return false;
        }
        $file = \tool_wp\local\exportimport\helper::get_export_file($itemid);
    } else if ($filearea === 'import' && $itemid) {
        require_login();
        if (!\tool_wp\permission::can_view_import($itemid)) {
            return false;
        }
        $file = \tool_wp\local\exportimport\helper::get_import_file($itemid);
    } else if ($filearea === 'wp') {
        $data = \tool_wp\registration::get_quick_stats();
        send_temp_file(base64_encode(join(':::', $data)), 'wp.txt', true);
    } else {
        return false;
    }

    if (!$file) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Add a "non-production state"/"site not registered" footer
 *
 * @return string
 */
function tool_wp_standard_footer_html() {
    global $OUTPUT;
    $data = ['wp_production_status' => tool_wp\workplace::get_production_state_context()];
    return $OUTPUT->render_from_template('tool_wp/site_status_disclaimer', $data);
}
