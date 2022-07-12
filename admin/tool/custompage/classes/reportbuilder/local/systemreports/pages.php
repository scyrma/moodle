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

use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use core_reportbuilder\system_report;
use core_reportbuilder\local\filters\{date, select, text};
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{action, column, filter};
use tool_custompage\permission;
use tool_custompage\local\helpers\audience;
use tool_custompage\local\models\page;
use tool_custompage\output\{name_editable, weight_editable};
use tool_tenant\tenancy;
use tool_wp\reportbuilder\local\entities\user;

/**
 * System report to define available pages
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class pages extends system_report {

    /** @var string The name of our internal entity. */
    private const PAGE_ENTITY_NAME = 'page';

    /**
     * Initialise report
     */
    public function initialise(): void {
        $this->set_main_table(page::TABLE, 'p');

        // Show all pages in the current tenant, plus any global page user can access.
        $paramtenantid = database::generate_param_name();
        $pageparams = [$paramtenantid => tenancy::get_tenant_id()];

        if (permission::can_create_global_page()) {
            $pageselect = "(p.tenantid = :{$paramtenantid} OR p.global = 1)";
        } else {
            // User cannot create global pages, so show them all tenant page plus any global page they are in the audience of.
            [$pageaudienceselect, $pageaudienceparams] = audience::user_pages_list_sql('p');
            $pageselect = "(
                (p.tenantid = :{$paramtenantid} AND p.global = 0) OR
                (p.global = 1 AND {$pageaudienceselect})
            )";
            $pageparams = array_merge($pageparams, $pageaudienceparams);
        }

        $this->add_base_condition_sql($pageselect, $pageparams);

        // Required for actions/callbacks.
        $this->add_base_fields('p.id, p.name, p.usercreated, p.tenantid, p.global');

        // Define entity.
        $this->annotate_entity(self::PAGE_ENTITY_NAME, new lang_string('page', 'tool_custompage'));

        // Join user entity on the user who created the page.
        $userentity = new user();
        $usertablealias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$usertablealias} ON {$usertablealias}.id = p.usercreated"));

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     */
    public function can_view(): bool {
        return permission::can_view_pages_list();
    }

    /**
     * Define report columns
     */
    public function add_columns(): void {
        $tablealias = $this->get_main_table_alias();

        // Page name.
        $this->add_column((new column(
            'name',
            new lang_string('name'),
            self::PAGE_ENTITY_NAME
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.name, {$tablealias}.id, {$tablealias}.usercreated, {$tablealias}.tenantid,
                {$tablealias}.global")
            ->set_is_sortable(true, ["{$tablealias}.name"])
            ->add_callback(function(string $value, stdClass $page): string {
                global $PAGE;

                $editable = new name_editable(new page(0, $page));
                $result = $editable->render($PAGE->get_renderer('core'));

                if ($page->global) {
                    $result .= html_writer::span(get_string('globalpage', 'tool_custompage'), 'badge badge-pill badge-secondary');
                }

                return $result;
            })
        );

        // Time created.
        $this->add_column((new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            self::PAGE_ENTITY_NAME
        ))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'])
        );

        // User created.
        $this->add_column_from_entity('user:fullname');

        // Weight.
        $this->add_column((new column(
            'weight',
            new lang_string('weight', 'tool_custompage'),
            self::PAGE_ENTITY_NAME
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$tablealias}.weight, {$tablealias}.id, {$tablealias}.usercreated, {$tablealias}.tenantid,
                {$tablealias}.global")
            ->set_is_sortable(true)
            ->add_callback(function(int $value, stdClass $page): string {
                global $PAGE;

                $editable = new weight_editable(new page(0, $page));
                return $editable->render($PAGE->get_renderer('core'));
            })
        );

        $this->set_initial_sort_column(self::PAGE_ENTITY_NAME . ':timecreated', SORT_DESC);
    }

    /**
     * Define report filters
     */
    public function add_filters(): void {
        $tablealias = $this->get_main_table_alias();

        // Page type (global/tenant).
        $this->add_filter((new filter(
            select::class,
            'pagetype',
            new lang_string('pagetype', 'tool_custompage'),
            self::PAGE_ENTITY_NAME
        ))
            ->set_field_sql("{$tablealias}.global")
            ->set_options([
                1 => new lang_string('globalpage', 'tool_custompage'),
                0 => new lang_string('tenantpage', 'tool_custompage'),
            ])
            ->set_limited_operators([
                select::ANY_VALUE,
                select::EQUAL_TO,
            ])
            // The filter is only available to those users who can create global pages.
            ->set_is_available(permission::can_create_global_page())
        );

        // Name.
        $this->add_filter((new filter(
            text::class,
            'name',
            new lang_string('name'),
            self::PAGE_ENTITY_NAME
        ))
            ->set_field_sql("{$tablealias}.name")
        );

        // Time created.
        $this->add_filter((new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            self::PAGE_ENTITY_NAME
        ))
            ->set_field_sql("{$tablealias}.timecreated")
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ])
        );

        // User created.
        $this->add_filter_from_entity('user:fullname')
            ->set_header(new lang_string('user'));
    }

    /**
     * Define report actions
     */
    protected function add_actions() {

        // Edit details.
        $this->add_action((new action(
            new moodle_url('/admin/tool/custompage/manage.php', ['id' => ':id']),
            new pix_icon('t/edit', ''),
            [],
            false,
            new lang_string('edit')
        ))
            ->add_callback(static function(stdClass $data): bool {
                return permission::can_edit_page(new page(0, $data));
            })
        );

        // Duplicate.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/copy', ''),
            ['data-action' => 'page-duplicate', 'data-page-id' => ':id', 'data-page-name' => ':name'],
            false,
            new lang_string('duplicate')
        ))
            ->add_callback(static function(stdClass $data): bool {

                // Ensure data name attribute is properly formatted.
                $page = new page(0, $data);
                $data->name = $page->get_formatted_name();

                return permission::can_create_page() && permission::can_edit_page($page);
            })
        );

        // Duplicate (to global).
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/globe', '', 'tool_custompage'),
            ['data-action' => 'page-duplicate', 'data-page-id' => ':id', 'data-page-name' => ':name', 'data-page-global' => 1],
            false,
            new lang_string('duplicatepageglobal', 'tool_custompage')
        ))
            ->add_callback(static function(stdClass $data): bool {

                // Ensure data name attribute is properly formatted.
                $page = new page(0, $data);
                $data->name = $page->get_formatted_name();

                return !$page->get('global') && permission::can_edit_page($page) && permission::can_create_global_page();
            })
        );

        // Duplicate (to tenant).
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/down', ''),
            ['data-action' => 'page-duplicate', 'data-page-id' => ':id', 'data-page-name' => ':name', 'data-page-global' => 0],
            false,
            new lang_string('duplicatepagetenant', 'tool_custompage')
        ))
            ->add_callback(static function(stdClass $data): bool {

                // Ensure data name attribute is properly formatted.
                $page = new page(0, $data);
                $data->name = $page->get_formatted_name();

                return $page->get('global') && permission::can_preview_page($page);
            })
        );

        // Delete.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/delete', ''),
            ['data-action' => 'page-delete', 'data-page-id' => ':id', 'data-page-name' => ':name'],
            false,
            new lang_string('delete')
        ))
            ->add_callback(static function(stdClass $data): bool {

                // Ensure data name attribute is properly formatted.
                $page = new page(0, $data);
                $data->name = $page->get_formatted_name();

                return permission::can_edit_page($page);
            })
        );
    }
}
