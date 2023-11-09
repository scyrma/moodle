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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_tenant\local;
use moodle_page;
use moodle_url;
use stdClass;

/**
 * Callbacks for the block_rbreport plugin that can be installed on LMS or Workplace
 *
 * @package     tool_tenant
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class block_rbreport {

    /**
     * Extra SQL for core reports if this block is used in Moodle Workplace.
     *
     * @param string $pagetype
     * @param string|null $subpage
     * @param moodle_url $pageurl
     * @return array [$sql, $params]
     */
    public static function get_extra_sql_for_core_reports(string $pagetype, ?string $subpage, moodle_url $pageurl): array {
        if (\core_component::get_component_directory('tool_tenant')) {
            [$tsql, $tparams] = self::get_tenant_sql('r', $pagetype, $subpage, $pageurl);
        } else {
            $tsql = '1=1';
            $tparams = [];
        }
        return [$tsql, $tparams];
    }

    /**
     * Returns user available reports of a tenant (tool_reportbuilder)
     *
     * @param int $tenantid
     * @return string[]
     */
    protected static function get_tenant_reports_tool(int $tenantid): array {
        global $DB;

        [$select, $selectparams] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql(
            'tenantid',
            'shared=1',
            $tenantid
        );

        if (!\tool_reportbuilder\permission::can_view_any()) {
            $allowedreports = \tool_reportbuilder\local\helpers\audience::user_reports_list();
            if (empty($allowedreports)) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($allowedreports, SQL_PARAMS_NAMED);
            $select .= " AND id $insql";
            $selectparams = array_merge($selectparams, $inparams);
        }

        $sql = "SELECT id, name FROM {tool_reportbuilder}
                    WHERE type = :type AND $select
                    ORDER BY name, id";
        $selectparams['type'] = \tool_reportbuilder\constants::TYPE_DATASOURCE;
        $reports = $DB->get_records_sql_menu($sql, $selectparams);
        return $reports;
    }

    /**
     * SQL to filter reports based on the page tenant
     *
     * @param string $reporttablealias
     * @param string $pagetype
     * @param string|null $subpage
     * @param moodle_url $pageurl
     * @return array
     */
    protected static function get_tenant_sql(string $reporttablealias, string $pagetype, ?string $subpage,
            moodle_url $pageurl): array {
        global $DB;
        $sharedspaceid = \tool_tenant\sharedspace::get_shared_space_id();
        $mypage = $DB->get_record('my_pages', ['id' => (int) $subpage]);
        if ($pagetype == 'my-index' && $pageurl->compare(new \moodle_url('/my/indexsys.php'), URL_MATCH_BASE)) {
            if (!\tool_tenant\tenancy::is_site_multi_tenant()) {
                return ["1=1", []];
            }
            if (!$sharedspaceid) {
                return ["1=0", []];
            }
            $tenantid = $sharedspaceid;
        } else if (!empty($mypage) && preg_match('/^tenant-([0-9]+)$/', $mypage->name, $matches, PREG_UNMATCHED_AS_NULL)) {
            $tenantid = (int) $matches[1];
        } else {
            $tenantid = \tool_tenant\tenancy::get_actual_tenant_id();
        }

        $sql = "{$reporttablealias}.component=:component";
        $params = ['component' => 'tool_tenant'];
        [$tsql, $tparams] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql(
            "{$reporttablealias}.itemid",
            "{$reporttablealias}.area = 'shared'",
            $tenantid
        );
        return ["$sql AND $tsql", $params + $tparams];
    }

    /**
     * Returns user available shared reports (tool_reportbuilder)
     *
     * @return string[]
     */
    protected static function get_shared_reports_tool(): array {
        $sharedspaceid = \tool_tenant\sharedspace::get_shared_space_id();

        // If shared space isn't enabled then there are no shared reports.
        if (!$sharedspaceid) {
            return [];
        }

        return self::get_tenant_reports_tool($sharedspaceid);
    }

    /**
     * Get current report (tool_reportbuilder)
     *
     * @param stdClass|null $config block config
     * @return \tool_reportbuilder\report_base|null
     */
    public static function fetch_report(?stdClass $config): ?\tool_reportbuilder\report_base {
        if (!\core_component::get_component_directory('tool_reportbuilder')) {
            return null;
        }
        if ($reportid = $config->report ?? 0) {
            $parameters = isset($config->pagesize) ? ['defaultpagesize' => (int) $config->pagesize] : [];
            try {
                $report = \tool_reportbuilder\manager::get_report($reportid, $parameters);
                if (\tool_reportbuilder\permission::can_view($report)) {
                    return $report;
                }
            } catch (\moodle_exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Check if the tool_reportbuilder report was converted to core_reportbuilder
     *
     * @param stdClass|null $config block config
     * @return int id of converted report or 0
     */
    public static function get_converted_report_id(?stdClass $config): int {
        return !empty($config->report) ?
            (int)get_config('tool_reportbuilder', 'converted-'.((int)$config->report)) : 0;
    }

    /**
     * Display report (tool_reportbuilder)
     *
     * @param \tool_reportbuilder\report_base $report
     * @param moodle_page $page
     * @return array [$text, $footer]
     */
    public static function display_report($report, moodle_page $page): array {
        if (!\core_component::get_component_directory('tool_reportbuilder')) {
            return ['', ''];
        }
        $outputpage = new \tool_reportbuilder\output\report_view($report, false);
        $output = $page->get_renderer('tool_reportbuilder');
        $text = $output->render($outputpage);
        $fullreporturl = new moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $report->get_id()]);
        $footer = \html_writer::link($fullreporturl, get_string('gotofullreport', 'block_rbreport'));
        return [$text, $footer];
    }

    /**
     * List of available reports (tool_reportbuilder)
     *
     * @param string $pagetype
     * @param string|null $subpage
     * @param moodle_url $pageurl
     * @return string[]
     */
    public static function get_report_options_tool(string $pagetype, ?string $subpage, moodle_url $pageurl): array {
        global $DB;
        if (!\core_component::get_component_directory('tool_reportbuilder')) {
            return [];
        }

        $mypage = $DB->get_record('my_pages', ['id' => (int)$subpage]);

        if ($pagetype == 'my-index' && $pageurl->compare(new \moodle_url('/my/indexsys.php'), URL_MATCH_BASE)) {
            return self::get_shared_reports_tool();
        } else if (!empty($mypage) && preg_match('/^tenant-([0-9]+)$/', $mypage->name, $matches, PREG_UNMATCHED_AS_NULL)) {
            return self::get_tenant_reports_tool((int)$matches[1]);
        } else {
            return self::get_tenant_reports_tool(\tool_tenant\tenancy::get_actual_tenant_id());
        }
    }
}
