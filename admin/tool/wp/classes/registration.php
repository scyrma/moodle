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
 * Class registration
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class registration - functions related to moodle.net registration
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class registration {

    /**
     * Returns the name of the moodle product
     * @return string
     */
    protected static function get_moodle_product() {
        return 'workplace:moodlecloud';
    }

    /**
     * This is the exact copy of the function sitedata::get_all_data_types() from local_hub plugin!
     *
     * It is used to validate that we don't send to moodle.net more attributes than it can accept.
     */
    public static function get_all_data_types() {
        return [
            'wpactiveusers' => [
                'type' => PARAM_INT,
                'description' => 'Number of unique users who logged in last month',
            ],
            'wpparticipantnumberaverage' => [
                'type' => PARAM_FLOAT,
                'description' => 'Average number of active participants last month',
            ],
            'wpplugins' => [
                'type' => PARAM_RAW,
                'description' => 'List of installed and enabled plugins, number of instances where applicable',
            ],
            'wptenants' => [
                'type' => PARAM_INT,
                'description' => 'Number of tenants',
            ],
            'wpprograms' => [
                'type' => PARAM_INT,
                'description' => 'Number of programs',
            ],
            'wpcertifications' => [
                'type' => PARAM_INT,
                'description' => 'Number of certifications',
            ],
            'wpdepartmentframeworks' => [
                'type' => PARAM_INT,
                'description' => 'Number of department frameworks',
            ],
            'wpdepartments' => [
                'type' => PARAM_INT,
                'description' => 'Number of departments',
            ],
            'wppositionframeworks' => [
                'type' => PARAM_INT,
                'description' => 'Number of position frameworks',
            ],
            'wppositions' => [
                'type' => PARAM_INT,
                'description' => 'Number of positions',
            ],
            'wpjobs' => [
                'type' => PARAM_INT,
                'description' => 'Number of jobs',
            ],
            'wpdynamicrules' => [
                'type' => PARAM_INT,
                'description' => 'Number of dynamic rules',
            ],
            'wpcertificates' => [
                'type' => PARAM_INT,
                'description' => 'Number of certificates',
            ],
            'wpcertificatesissues' => [
                'type' => PARAM_INT,
                'description' => 'Number of issued certificates',
            ],
            'wpreports' => [
                'type' => PARAM_INT,
                'description' => 'Number of custom reports',
            ],
            'wpdatastorerecords' => [
                'type' => PARAM_INT,
                'description' => 'Number of records in datastore',
            ],
            'wpproductionstate' => [
                'type' => PARAM_BOOL,
                'description' => 'Production state',
            ],
        ];
    }

    /**
     * Allows to hook into core\hub\registration::get_stats_summary()
     *
     * Can be called as:
     * component_class_callback('tool_wp\\registration', 'site_info', [$siteinfo, true]);
     *
     * @uses \tool_wp\registration::stats()
     * @uses \tool_tenant\registration::stats()
     * @uses \tool_program\registration::stats()
     * @uses \tool_certification\registration::stats()
     * @uses \tool_organisation\registration::stats()
     * @uses \tool_dynamicrule\registration::stats()
     * @uses \tool_reportbuilder\registration::stats()
     * @uses \tool_datastore\registration::stats()
     *
     * @param array $siteinfo
     * @param bool $usestrings return data in human readable form to be displayed on the "Registration" page
     */
    public static function site_info(&$siteinfo, $usestrings = false) {
        // Insert "moodleproduct" after the "moodlerelease".
        $offset = array_search('moodlerelease', array_keys($siteinfo));
        $offset = $offset === false ? 0 : $offset + 1;
        $value = self::get_moodle_product();
        if ($usestrings) {
            $value = get_string('reg_moodleproduct', 'tool_wp', $value);
        }
        $siteinfo = array_slice($siteinfo, 0, $offset, true) +
            ['moodleproduct' => $value] +
            array_slice($siteinfo, $offset, null, true);

        // For other workplace plugins add their information.
        foreach (['tool_wp', 'tool_tenant', 'tool_program', 'tool_certification', 'tool_organisation', 'tool_dynamicrule',
                     'tool_reportbuilder', 'tool_datastore'] as $plugin) {
            $newsiteinfo = component_class_callback($plugin . '\\registration', 'stats', [$usestrings]);

            if (is_array($newsiteinfo) && !empty($newsiteinfo)) {
                foreach ($newsiteinfo as $key => $value) {
                    if (!array_key_exists($key, self::get_all_data_types())) {
                        debugging('The registration key '. s($key) . ' is not valid and will be ignored', DEBUG_DEVELOPER);
                        continue;
                    }
                    if ($usestrings) {
                        $type = self::get_all_data_types()[$key]['type'] ?? PARAM_RAW;
                        $displayvalue = $type === PARAM_BOOL ? get_string($value ? 'yes' : 'no') : $value;
                        $newsiteinfo[$key] = get_string('reg_' . $key, $plugin, $displayvalue);
                    }
                }
                $siteinfo += $newsiteinfo;
            }
        }

        if (!empty($siteinfo['wpplugins']) && !$usestrings) {
            // Move plugins info to the end.
            $wpplugins = $siteinfo['wpplugins'];
            unset($siteinfo['wpplugins']);
            $siteinfo['wpplugins'] = $wpplugins;
        }
    }

    /**
     * Implementation of callback 'stats' called from tool_wp\\registration::site_info
     *
     * Can be called as:
     * component_class_callback('tool_wp\\registration', 'stats', [true]);
     *
     * @param bool $usestrings return data in human readable form to be displayed on the "Registration" page
     * @return array
     */
    public static function stats($usestrings = false) {
        global $CFG;
        $stats = [
            'wpactiveusers' => self::get_active_users(),
            'wpparticipantnumberaverage' => self::get_participants_number_average(),
            'wpplugins' => self::get_plugins($usestrings),
            'wpproductionstate' => !empty($CFG->workplaceproductionstate),
        ];
        $stats += self::get_tool_certificate_stats();

        return $stats;
    }

    /**
     * Returns tool_certificate stats.
     *
     * @return array
     */
    private static function get_tool_certificate_stats(): array {
        global $DB;
        if (\core_component::get_component_directory('tool_certificate')) {
            return [
                'wpcertificates' => $DB->count_records('tool_certificate_templates', []),
                'wpcertificatesissues' => $DB->count_records('tool_certificate_issues', []),
            ];
        }
        return [];
    }

    /**
     * Number of unique users who logged in last month
     * @return int
     */
    protected static function get_active_users() {
        global $DB;
        return $DB->count_records_select('user', 'deleted = ? AND lastlogin > ?', [0, time() - DAYSECS * 30]);
    }

    /**
     * Average number of active participants last month
     * @return int
     */
    protected static function get_participants_number_average() {
        global $DB, $SITE;

        // Count total of enrolments for visible course (except front page).
        $sql = 'SELECT COUNT(*) FROM (
        SELECT DISTINCT ue.userid, e.courseid
        FROM {user_enrolments} ue, {enrol} e, {course} c, {user} u
        WHERE ue.enrolid = e.id
            AND e.courseid <> :siteid
            AND c.id = e.courseid
            AND c.visible = 1
            AND u.id = ue.userid
            AND u.lastlogin > :lastlogin
            ) total';
        $params = array('siteid' => $SITE->id, 'lastlogin' => time() - DAYSECS * 30);
        $enrolmenttotal = $DB->count_records_sql($sql, $params);

        // Count total of visible courses (minus front page).
        $coursetotal = $DB->count_records('course', array('visible' => 1));
        $coursetotal = $coursetotal - 1;

        // Average of enrolment.
        if (empty($coursetotal)) {
            $participantaverage = 0;
        } else {
            $participantaverage = $enrolmenttotal / $coursetotal;
        }

        return $participantaverage;
    }

    /**
     * List of installed and enabled plugins, number of instances where applicable
     *
     * @param bool $fordisplay
     * @return string
     */
    public static function get_plugins($fordisplay = false) {
        $pm = \core_plugin_manager::instance();
        $instances = self::count_plugin_instances();
        $plugins = [];
        foreach (\core_component::get_plugin_types() as $plugintype => $unused) {
            $enabled = $pm->get_enabled_plugins($plugintype);
            $all = array_keys(\core_component::get_plugin_list($plugintype));
            $standard = \core_plugin_manager::standard_plugins_list($plugintype) ?: [];
            $addons = array_diff($all, $standard);
            $deleted = array_diff($standard, $all);
            // In order to minimise the passed data we do not pass standard plugins that are either disabled or
            // when plugin type does not support enabling/disabling.
            // + : additional plugin.
            // - : deleted standard plugin.
            // ! : plugin disabled.
            $plugins[$plugintype] = [];
            foreach ($all as $pluginname) {
                $count = array_key_exists($plugintype, $instances) ?
                    (isset($instances[$plugintype][$pluginname]) ? $instances[$plugintype][$pluginname] : 0) :
                    null;
                $isenabled = is_array($enabled) ? in_array($pluginname, $enabled) : null;
                $isstandard = in_array($pluginname, $standard);
                $isaddon = in_array($pluginname, $addons);

                if ($isaddon) {
                    $pluginname = '+' . $pluginname;
                }
                if ($isenabled !== true && $isstandard && !$count) {
                    continue;
                }
                if ($isenabled === false) {
                    $pluginname = '!' . $pluginname;
                }
                if ($count !== null) {
                    $pluginname .= '(' . $count . ')';
                }
                $plugins[$plugintype][] = $pluginname;
            }
            foreach ($deleted as $pluginname) {
                $plugins[$plugintype][] = '-' . $pluginname;
            }
            if (empty($plugins[$plugintype])) {
                unset($plugins[$plugintype]);
            }
        }

        $v = json_encode($plugins);
        if ($fordisplay) {
            $v = str_replace('"', '', $v);
            $v1 = substr($v, 0, strpos($v, ',', 20) + 1);
            $v2 = substr($v, strlen($v1));
            $v = $v1 .
                \html_writer::span($v2, 'collapse', ['id' => 'viewpluginsdetails']) . ' ' .
                \html_writer::tag('a', get_string('showmore', 'form').'&raquo;',
                    ['data-toggle' => 'collapse', 'data-target' => '#viewpluginsdetails', 'href' => '#']);
        }
        return $v;
    }

    /**
     * Calculate plugin instances (for some types)
     *
     * @return array
     */
    protected static function count_plugin_instances(): array {
        global $DB, $CFG;
        static $instances = null;
        if ($instances !== null) {
            return $instances;
        }
        $instances = [];

        $instances['block'] = $DB->get_records_sql_menu(
            'SELECT blockname, count(*) AS cnt FROM {block_instances} GROUP BY blockname', []);
        $instances['auth'] = $DB->get_records_sql_menu(
            'SELECT auth, count(*) AS cnt FROM {user} WHERE deleted = 0 GROUP BY auth', []);
        $instances['enrol'] = $DB->get_records_sql_menu(
            'SELECT e.enrol, count(ue.id) AS cnt
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON ue.enrolid = e.id
                  GROUP BY e.enrol', []);
        $instances['format'] = $DB->get_records_sql_menu(
            'SELECT format, count(*) AS cnt FROM {course} GROUP BY format', []);
        $instances['customfield'] = $DB->get_records_sql_menu(
            'SELECT type, count(*) AS cnt FROM {customfield_field} GROUP BY type', []);
        $instances['qtype'] = $DB->get_records_sql_menu(
            'SELECT qtype, count(*) AS cnt FROM {question} GROUP BY qtype', []);
        $instances['repository'] = $DB->get_records_sql_menu(
            'SELECT r.type, count(ri.id) AS cnt
                  FROM {repository_instances} ri
                  JOIN {repository} r ON ri.typeid = r.id
                  GROUP BY r.type', []);
        try {
            $instances['certificateelement'] = $DB->get_records_sql_menu(
                'SELECT element, count(*) AS cnt FROM {tool_certificate_elements} GROUP BY element', []);
        } catch (\dml_exception $e) {
            // Do not fail if table does not exist.
            null;
        }

        // Modules.
        $instances['mod'] = [];
        foreach (\core_component::get_plugin_list('mod') as $pluginname => $dir) {
            try {
                $instances['mod'][$pluginname] = $DB->count_records_select($pluginname, "course<>0");
            } catch (\dml_exception $e) {
                // Do not fail if the plugin table does not exist.
                null;
            }
        }

        // Themes.
        $themescnt1 = $DB->get_records_sql_menu(
            'SELECT theme, count(*) AS cnt FROM {course} GROUP BY theme', []);
        $themescnt2 = $DB->get_records_sql_menu(
            'SELECT theme, count(*) AS cnt FROM {course_categories} GROUP BY theme', []);
        $themescnt3 = $DB->get_records_sql_menu(
            'SELECT theme, count(*) AS cnt FROM {user} GROUP BY theme', []);
        $themescnt4 = $DB->get_records_sql_menu(
            'SELECT theme, count(*) AS cnt FROM {cohort} GROUP BY theme', []);
        $instances['theme'] = [];
        foreach (\core_component::get_plugin_list('theme') as $pluginname => $dir) {
            $instances['theme'][$pluginname] = (int)($CFG->theme === $pluginname) +
                (!empty($themescnt1[$pluginname]) ? $themescnt1[$pluginname] : 0) +
                (!empty($themescnt2[$pluginname]) ? $themescnt2[$pluginname] : 0) +
                (!empty($themescnt3[$pluginname]) ? $themescnt3[$pluginname] : 0) +
                (!empty($themescnt4[$pluginname]) ? $themescnt4[$pluginname] : 0);
        }

        // TODO: other instances that can be potentially calculated:
        // assignsubmission, assignfeedback, filter, qbehavior, cachestore, datafield, editor (in user pref),
        // availability (needs json parsing), message (in user pref), profilefield, gradingform .

        return $instances;
    }

    /**
     * Returns encrypted quick site information
     *
     * @return array encrypted info, envelope key, initialization vector
     */
    public static function get_quick_stats(): array {
        global $CFG;
        $d = str_repeat('-', 5);
        $key = "${d}BEGIN PUBLIC KEY${d}\nMFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAOSm4VUeVf++ogMwzYtXIwjX3f//tck/\n".
            "ayzsfJfyRSj3oVSZ/ElasFU4ayb/SvpG4UhdmdyP/z6okdU9YDvCy+ECAwEAAQ==\n${d}END PUBLIC KEY$d\n";
        $ekeys = [];
        $plugin = \core_plugin_manager::instance()->get_plugins_of_type('tool')['wp'];
        $data = [
            'lmsversion' => $CFG->version,
            'product' => self::get_moodle_product(),
            'productionstate' => !empty($CFG->workplaceproductionstate),
            'wpversion' => $plugin->versiondb,
            'wprelease' => $plugin->release ?? '',
        ];
        $sealed = '';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES256'));
        openssl_seal(json_encode($data), $sealed, $ekeys, [$key], 'AES256', $iv);
        return [$sealed, $ekeys[0], $iv];
    }
}
