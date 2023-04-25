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
 * Class dashbaord_manager.
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use context;
use stdClass;

/**
 * Methods for managing the tenants dashboards
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class dashboard_manager {
    /**
     * Returns dashboard subpage name
     *
     * @param int $tenantid
     * @return string
     */
    public static function generate_tenant_page_name(int $tenantid): string {
        return 'tenant-' . $tenantid;
    }


    /**
     * Returns tenant dashboard page
     *
     * @param int $tenantid
     * @return stdClass|null
     */
    public static function get_tenant_dashboard_page(int $tenantid = 0): ?stdClass {
        global $DB;

        $tenantid = $tenantid ?: tenancy::get_tenant_id();

        $pagename = self::generate_tenant_page_name($tenantid);
        if (!$page = $DB->get_record('my_pages', ['userid' => null, 'private' => 1, 'name' => $pagename])) {
            $page = self::create_tenant_dashboard_page($tenantid);
        }
        return $page;
    }

    /**
     * Creates tenant dashboard page
     *
     * @param int $tenantid
     * @return stdClass
     */
    private static function create_tenant_dashboard_page(int $tenantid = 0): stdClass {
        global $DB;

        $tenantid = $tenantid ?: tenancy::get_tenant_id();

        // Create the tenant dashboard page record.
        $page = new stdClass();
        $page->userid = null;
        $page->name = self::generate_tenant_page_name($tenantid);
        $page->private = 1;
        $page->sortorder = 0;
        $page->id = $DB->insert_record('my_pages', $page);

        // Copy all the site default dashboard blocks.
        $systempage = $DB->get_record('my_pages', ['userid' => null, 'name' => '__default', 'private' => 1], '*', MUST_EXIST);
        $systemcontext = \context_system::instance();
        self::copy_block_instances($systempage, $page, $systemcontext, $systemcontext);

        return $page;
    }

    /**
     * Deletes tenant dashboard page
     *
     * @param int $tenantid
     */
    public static function delete_tenant_dashboard_page(int $tenantid = 0): void {
        global $DB;

        $tenantid = $tenantid ?: tenancy::get_tenant_id();

        $pagename = self::generate_tenant_page_name($tenantid);
        $conditions = ['userid' => null, 'private' => 1, 'name' => $pagename];

        // Delete the block instances.
        $params = ['private' => 1, 'systemcontextlevel' => CONTEXT_SYSTEM, 'pagetypepattern' => 'my-index',
            'pagename' => $pagename, 'empty' => ''];

        $sql = "SELECT bi.id
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid AND ctx.contextlevel = :systemcontextlevel
                  JOIN {my_pages} p ON " . $DB->sql_concat(':empty', 'p.id') . " = bi.subpagepattern
                 WHERE bi.pagetypepattern = :pagetypepattern
                   AND p.private = :private
                   AND p.name = :pagename";
        $blockids = $DB->get_fieldset_sql($sql, $params);
        if (!empty($blockids)) {
            blocks_delete_instances($blockids);
        }

        // Delete the page.
        $DB->delete_records('my_pages', $conditions);
    }

    /**
     * Links a tenant dashboard to the default site dashboard
     *
     * @param int $tenantid
     */
    public static function link_tenant_dashboard(int $tenantid = 0): void {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        // Update 'dashboardlinked' value for the tenant.
        (new \tool_tenant\manager())->update_tenant($tenantid, (object)['dashboardlinked' => 1]);
        // Delete tenant dashboard page if dashboard is now linked.
        self::delete_tenant_dashboard_page($tenantid);
    }


    /**
     * Un-links a tenant dashboard from the default site dashboard
     *
     * @param int $tenantid
     */
    public static function unlink_tenant_dashboard(int $tenantid = 0): void {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        // Update 'dashboardlinked' value for the tenant.
        (new \tool_tenant\manager())->update_tenant($tenantid, (object)['dashboardlinked' => 0]);
        // Create tenant dashboard page if dashboard is now unlinked.
        self::create_tenant_dashboard_page($tenantid);
    }

    /**
     * This copies a system default page to the current user if the tenant dashboard is linked
     * or copies a tenant default dashboard if not.
     *
     * @param int $userid
     * @return false|mixed|stdClass
     *
     */
    public static function copy_dashboard_page(int $userid) {
        global $DB;

        if ($userdashbaord = $DB->get_record('my_pages', ['userid' => $userid, 'name' => '__default', 'private' => 1])) {
            return $userdashbaord;
        }

        $tenantid = tenancy::get_actual_tenant_id();

        if (!empty(tenancy::get_tenants()[$tenantid]->dashboardlinked)) {
            // If tenant dashboard is linked then we fallback to my_copy_page.
            return false;
        }

        // Get the tenant dashboard page.
        $tenantpage = self::get_tenant_dashboard_page($tenantid);

        // Create user page record.
        $page = new stdClass();
        $page->userid = $userid;
        $page->name = '__default';
        $page->private = 1;
        $page->sortorder = 0;
        $page->id = $DB->insert_record('my_pages', $page);

        // Copy all the blocks.
        $systemcontext = \context_system::instance();
        $usercontext = \context_user::instance($userid);
        self::copy_block_instances($tenantpage, $page, $systemcontext, $usercontext);

        return $page;
    }

    /**
     * Copy all dashboard blocks from one page to another.
     *
     * Copied from {@see my_copy_page()}
     *
     * @param stdClass $frompage page object from where blocks will be copied
     * @param stdClass $topage page object to where blocks will be copied
     * @param context $fromcontext from page parent context
     * @param context $tocontext to page parent context
     */
    private static function copy_block_instances(stdClass $frompage, stdClass $topage,
                                                 context $fromcontext, context $tocontext): void {
        global $DB;

        // TODO: Check if the current DB index for 'block_instances' table is used, if not create an MDL to change it.
        $blockinstances = $DB->get_records('block_instances', ['parentcontextid' => $fromcontext->id,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $frompage->id]);
        $newblockinstanceids = [];
        foreach ($blockinstances as $instance) {
            $originalid = $instance->id;
            unset($instance->id);
            $instance->parentcontextid = $tocontext->id;
            $instance->subpagepattern = $topage->id;
            $instance->timecreated = time();
            $instance->timemodified = $instance->timecreated;
            $instance->id = $DB->insert_record('block_instances', $instance);
            $newblockinstanceids[$originalid] = $instance->id;
            $blockcontext = \context_block::instance($instance->id);  // Just creates the context record.
            $block = block_instance($instance->blockname, $instance);
            if (!$block->instance_copy($originalid)) {
                debugging("Unable to copy block-specific data for original block instance: $originalid
                to new block instance: $instance->id", DEBUG_DEVELOPER);
            }
        }

        // Clone block position overrides.
        if ($blockpositions = $DB->get_records('block_positions',
            ['subpage' => $frompage->id, 'pagetype' => 'my-index', 'contextid' => $fromcontext->id])) {
            foreach ($blockpositions as &$positions) {
                $positions->subpage = $topage->id;
                $positions->contextid = $tocontext->id;
                if (array_key_exists($positions->blockinstanceid, $newblockinstanceids)) {
                    // For block instances that were defined on the default dashboard and copied to the user dashboard
                    // use the new blockinstanceid.
                    $positions->blockinstanceid = $newblockinstanceids[$positions->blockinstanceid];
                }
                unset($positions->id);
            }
            $DB->insert_records('block_positions', $blockpositions);
        }
    }

    /**
     * Resets the page customisations for all users in a tenant.
     *
     * Copied from {@see my_reset_page_for_all_users()}
     *
     * @param array $users array of user ids
     * @return void
     */
    public static function reset_dashboard_page_for_users(array $users): void {
        global $DB;

        // This may take a while. Raise the execution time limit.
        \core_php_time_limit::raise();

        $chunks = array_chunk($users, 20);

        foreach ($chunks as $userchunk) {
            list($infragment, $inparams) = $DB->get_in_or_equal($userchunk,  SQL_PARAMS_NAMED);
            // Find all the user pages and all block instances in them.
            $sql = "SELECT bi.id
                  FROM {my_pages} p
                  JOIN {context} ctx ON ctx.instanceid = p.userid AND ctx.contextlevel = :usercontextlevel
                  JOIN {block_instances} bi ON bi.parentcontextid = ctx.id
                   AND bi.pagetypepattern = :pagetypepattern
                   AND (bi.subpagepattern IS NULL OR bi.subpagepattern = " . $DB->sql_concat(':empty', 'p.id') . ")
                 WHERE p.private = :private
                   AND p.userid $infragment";

            $params = array_merge([
                'private' => 1,
                'usercontextlevel' => CONTEXT_USER,
                'pagetypepattern' => 'my-index',
                'empty' => '',
            ], $inparams);
            $blockids = $DB->get_fieldset_sql($sql, $params);

            // Wrap the SQL queries in a transaction.
            $transaction = $DB->start_delegated_transaction();

            // Delete the block instances.
            if (!empty($blockids)) {
                blocks_delete_instances($blockids);
            }

            // Finally delete the pages.
            $DB->delete_records_select(
                'my_pages',
                "userid $infragment AND private = :private",
                array_merge(['private' => 1], $inparams)
            );

            // We should be good to go now.
            $transaction->allow_commit();
        }

        // Trigger dashboard has been reset event.
        $eventparams = array(
            'context' => \context_system::instance(),
            'other' => array(
                'private' => 1,
                'pagetype' => 'my-index',
            ),
        );
        $event = \core\event\dashboards_reset::create($eventparams);
        $event->trigger();
    }

    /**
     * Modifies the output for default system dashboard page.
     *
     * @return string
     */
    public static function default_system_dashboard(): string {
        global $PAGE, $SESSION;

        // Load amd module.
        $PAGE->requires->js_call_amd('tool_tenant/manage_dashboard', 'init');

        // In order to avoid add duplicate notifications because these are added when page is in STATE_BEFORE_HEADER and each
        // block process (process_url_actions()) reload this page we need to check that element doesn't exist in
        // $SESSION->notifications.
        $message = get_string('editingsitedashboard', 'tool_tenant');

        if (!isset($SESSION->notifications) || !in_array($message, array_column($SESSION->notifications, 'message'))) {
            \core\notification::add($message, \core\output\notification::NOTIFY_INFO);
        }

        if (!permission::can_edit_all_tenant_dashboards()) {
            return '';
        }

        // Display a button to link all tenants.
        $options = ['class' => 'btn btn-outline-secondary mr-2', 'data-action' => 'link-all'];
        $button = \html_writer::tag('button', get_string('linkalltenants', 'tool_tenant'), $options);
        // Display a button to reset dashboard for all users in linked tenants.
        $options = ['class' => 'btn btn-outline-secondary mr-2', 'data-action' => 'reset-linked-dashboard'];
        $button .= \html_writer::tag('button', get_string('resetlinkeddashboard', 'tool_tenant'), $options);

        return $button;
    }

    /**
     * Returns the IDs of users (who have dashboard page) in tenants that are linked to the default site dashboard.
     *
     * @return int[]
     */
    public static function get_users_in_linked_tenants(): array {
        global $DB, $CFG;

        $tenantcondition = "mp.userid IN (SELECT tu.userid FROM {tool_tenant_user} tu
                        JOIN {tool_tenant} tt on tu.tenantid = tt.id
                        WHERE tt.dashboardlinked = :linked)";

        // Add all default tenant users if default tenant is linked.
        if (tenancy::get_tenants()[tenancy::get_default_tenant_id()]->dashboardlinked) {
            $tenantcondition = "( $tenantcondition OR mp.userid NOT IN (SELECT userid FROM {tool_tenant_user}))";
        }

        $sql = "SELECT DISTINCT(userid) FROM {my_pages} mp
                JOIN {user} u ON u.id = mp.userid AND u.deleted = 0
                    WHERE $tenantcondition
                    AND mp.userid IS NOT NULL
                    AND mp.private = :private
                    AND mp.userid <> :siteguest";
        return $DB->get_fieldset_sql($sql, ['linked' => 1, 'private' => 1, 'siteguest' => (int)$CFG->siteguest]);
    }

    /**
     * Link all tenant dashboards
     *
     * @param bool $resetuserdashboards If all user dashboards in affected tenants need to be reset.
     */
    public static function link_all_tenant_dashboards(bool $resetuserdashboards = false): void {
        global $DB;

        $users = [];
        // Get all tenant dashboards that are not linked to the Default site Dashboard.
        $unlinkedtenants = $DB->get_records(tenant::TABLE, ['dashboardlinked' => 0]);

        foreach ($unlinkedtenants as $tenant) {
            self::link_tenant_dashboard($tenant->id);
            if ($resetuserdashboards) {
                // Update list of users to reset the dashboard.
                $tenantcondition = tenancy::get_users_subquery(false, true, 'mp.userid', $tenant->id);
                $sql = "SELECT DISTINCT(userid) FROM {my_pages} mp
                    WHERE $tenantcondition mp.userid IS NOT NULL AND mp.private = :private";
                $users = array_merge($users, $DB->get_fieldset_sql($sql, ['private' => 1]));
            }
        }
        if ($resetuserdashboards && !empty($users)) {
            // Reset tenants users dashboards.
            self::reset_dashboard_page_for_users($users);
        }
    }

    /**
     * Additional check for when a user can edit a block (i.e. tenant admin can edit their own dashboard).
     *
     * Executed from {@see moodle_block::user_can_edit()}
     *
     * @param \block_base $block
     * @return bool
     */
    public static function hook_can_edit_block(\block_base $block): bool {
        global $DB;
        // If this looks like a tenant dashboard and user can edit the own tenant's dashboard - check that they
        // can edit this particular dashboard.
        $couldbetenantdashboard = $block->instance->pagetypepattern === 'my-index' && (int)$block->instance->subpagepattern
            && $block->page->context->contextlevel == CONTEXT_SYSTEM;
        if ($couldbetenantdashboard && permission::can_edit_tenant_dashboard_blocks(tenancy::get_actual_tenant_id())) {
            // Cheap pre-check passed, now make a DB query to retrieve the actual tenant id for this dashboard.
            $tenantdashboard = $DB->get_field('my_pages', 'name', ['id' => (int)$block->instance->subpagepattern]);
            if ($tenantdashboard && preg_match('/^tenant-([\d]+)$/', $tenantdashboard, $matches)) {
                return permission::can_edit_tenant_dashboard_blocks((int)$matches[1]);
            }
        }
        return false;
    }

    /**
     * Returns tenant dashboard if enabled
     *
     * @param int $userid
     * @param int $private
     * @return stdClass|void|null
     * @throws \dml_exception
     */
    public static function show_tenant_dashboard_if_enabled($userid = 0, $private = MY_PAGE_PRIVATE) {
        global $DB;

        if ($private != MY_PAGE_PRIVATE) {
            // There are no tenant-level defaults for the profile page.
            return null;
        }

        $tenantid = tenancy::get_actual_tenant_id($userid);
        $dashboardlinked = (int) $DB->get_field('tool_tenant', 'dashboardlinked', ['id' => $tenantid]);

        if ($dashboardlinked === 0) {
            return self::get_tenant_dashboard_page();
        }
    }
}
