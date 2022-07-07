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

declare(strict_types=1);

namespace tool_custompage\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;
use core_reportbuilder\local\entities\user;
use tool_custompage\permission;
use tool_custompage\local\helpers\audience as audience_helper;
use tool_custompage\local\models\{page, audience};
use tool_tenant\tenancy;

/**
 * Report to list users matching audience of a page
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class access extends system_report {

    /**
     * Initialise the report
     */
    protected function initialise(): void {
        $userentity = new user();
        $userentityalias = $userentity->get_table_alias('user');
        $this->set_main_table('user', $userentityalias);
        $this->add_entity($userentity);

        $pageid = $this->get_parameter('pageid', 0, PARAM_INT);
        $page = new page($pageid);
        // Determine which users are allowed to view the page based on it's audiences.
        [$wheres, $params] = self::get_users_by_audience_sql($pageid, $userentityalias);
        if (!empty($wheres)) {
            $allwheres = '(' . implode(') OR (', $wheres) . ')';
        } else {
            $allwheres = '1=0';
        }

        $this->add_base_condition_sql("($allwheres)", $params);

        // If custom page isn't global or user can't create global page we need to limit the report
        // to show only users in current tenant.
        if (!$page->get('global') || !permission::can_create_global_page()) {
            $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$userentityalias}.id"));
        }

        $this->add_column_from_entity('user:fullnamewithpicturelink');
        $this->add_filter_from_entity('user:fullname');

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);

        $this->set_downloadable(false);
    }

    /**
     * Ensure we can view the report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $pageid = $this->get_parameter('pageid', 0, PARAM_INT);

        return permission::can_preview_page(new page($pageid));
    }

    /**
     * Find users who can access this page based on the audience
     *
     * @param int $pageid
     * @param string $usertablealias
     * @return array
     */
    protected static function get_users_by_audience_sql(int $pageid, string $usertablealias): array {
        $audiences = audience::get_records(['pageid' => $pageid]);

        return audience_helper::user_audience_sql($audiences, $usertablealias);
    }
}
