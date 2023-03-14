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

namespace tool_custompage\local\helpers;

use cache;
use core_component;
use core_collator;
use core_plugin_manager;
use core_reportbuilder\local\helpers\database;
use tool_custompage\external\page_audience_cards_exporter;
use tool_custompage\local\audience\base;
use tool_custompage\local\models\audience as model;
use tool_custompage\local\models\page;
use tool_tenant\tenancy;

/**
 * Custom pages audience helper class
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience {

    /**
     * Return audience instances for a given page
     *
     * @param int $pageid
     * @return array[]
     */
    public static function get_audiences_for_page(int $pageid): array {
        $instances = array_map(static function(model $record): ?base {
            return base::instance(0, $record->to_record());
        }, model::get_records(['pageid' => $pageid], 'id'));

        $audienceinstances = [];

        $showormessage = false;
        foreach (array_filter($instances) as $instance) {
            $persistent = $instance->get_persistent();
            $canedit = $instance->user_can_edit();

            // If user cannot view or edit this audience, the description will be hidden.
            $instacedescription = $canedit ? $instance->get_description() : get_string('audiencewarning', 'tool_custompage');

            $audienceinstances[] = [
                'instanceid' => $persistent->get('id'),
                'description' => $instacedescription,
                'heading' => $instance->get_name(),
                'headingeditable' => $instance->get_name(),
                'canedit' => $canedit,
                'candelete' => $canedit,
                'showormessage' => $showormessage,
            ];

            $showormessage = true;
        }

        return $audienceinstances;
    }

    /**
     * Return appropriate list of where clauses and params for given audiences
     *
     * @param model[] $audiences
     * @param string $usertablealias
     * @return array[] [$wheres, $params]
     */
    public static function user_audience_sql(array $audiences, string $usertablealias = 'u'): array {
        $wheres = $params = [];

        foreach ($audiences as $audience) {
            if ($instance = base::instance(0, $audience->to_record())) {
                $instancetablealias = database::generate_alias();
                [$instancejoin, $instancewhere, $instanceparams] = $instance->get_sql($instancetablealias);

                $wheres[] = "{$usertablealias}.id IN (
                    SELECT {$instancetablealias}.id
                      FROM {user} {$instancetablealias}
                           {$instancejoin}
                     WHERE {$instancewhere}
                     )";
                $params += $instanceparams;
            }
        }

        return [$wheres, $params];
    }

    /**
     * Get all available audience types for export
     *
     * @param bool $global
     * @return array
     *
     * @deprecated since Moodle 4.1 - please do not use this function any more, {@see page_audience_cards_exporter}
     */
    public static function get_all_audiences_menu_types(bool $global): array {
        debugging('The function ' . __FUNCTION__ . '() is deprecated, please do not use it any more. ' .
            'See \'page_audience_cards_exporter\' class for replacement', DEBUG_DEVELOPER);

        $menucards = [];

        // Iterate over all audience types.
        $audiences = core_component::get_component_classes_in_namespace(null, 'tool_custompage\\audience');
        $audiencekeyindex = 0;
        foreach ($audiences as $class => $path) {
            if (is_subclass_of($class, base::class)) {
                $audience = $class::instance();
                if (!$audience->user_can_add($global)) {
                    continue;
                }

                // The name of each card will be the component the audience belongs to.
                [$component] = explode('\\', $class);
                if ($plugininfo = core_plugin_manager::instance()->get_plugin_info($component)) {
                    $componentname = $plugininfo->displayname;
                } else {
                    $componentname = get_string('site');
                }

                // New menu card per component.
                if (!array_key_exists($componentname, $menucards)) {
                    $menucards[$componentname] = [
                        'name' => $componentname,
                        'key' => 'index' . ++$audiencekeyindex,
                        'items' => [],
                    ];
                }

                // Append menu card item per audience.
                $menucards[$componentname]['items'][] = [
                    'name' => $audience->get_name(),
                    'identifier' => get_class($audience),
                    'title' => get_string('addaudience', 'core_reportbuilder', $audience->get_name()),
                    'action' => 'add-audience',
                    'disabled' => !$audience->is_available(),
                ];
            }
        }

        // Order items in each menu card alphabetically.
        array_walk($menucards, static function(array &$menucard): void {
            core_collator::asort_array_of_arrays_by_key($menucard['items'], 'name');
            $menucard['items'] = array_values($menucard['items']);
        });

        return array_values($menucards);
    }

    /**
     * Returns list of pages that the specified user can access. Note this is potentially very expensive
     * to calculate if a site has lots of pages, with lots of audiences, so we cache the result.
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return int[] Array of page IDs
     */
    public static function get_allowed_pages(?int $userid = null): array {
        global $USER, $DB;

        $userid = $userid ?: (int) $USER->id;
        if (isguestuser($userid)) {
            return [];
        }

        // Prepare cache, if we previously stored the user allowed pages then return that.
        $userallowedpagescache = cache::make('tool_custompage', 'user_allowed_pages');
        $userallowedpages = $userallowedpagescache->get($userid);
        if ($userallowedpages !== false) {
            return $userallowedpages;
        }

        $allowedpages = [];
        $pageaudiences = [];

        // Retrieve all audiences and group them by page for convenience.
        $audiences = model::get_records();
        foreach ($audiences as $audience) {
            $pageaudiences[$audience->get('pageid')][] = $audience;
        }

        foreach ($pageaudiences as $pageid => $audiences) {

            // Generate audience SQL based on those for the current page.
            [$wheres, $params] = self::user_audience_sql($audiences);
            $allwheres = implode(' OR ', $wheres);

            $paramuserid = database::generate_param_name();
            $params[$paramuserid] = $userid;

            $sql = "SELECT DISTINCT(u.id)
                      FROM {user} u
                     WHERE ({$allwheres})
                       AND u.id = :{$paramuserid}";

            // If we have a matching record, user can view the page.
            if ($DB->record_exists_sql($sql, $params)) {
                $allowedpages[] = $pageid;
            }
        }

        // Store user allowed pages in cache.
        $userallowedpagescache->set($userid, $allowedpages);

        return $allowedpages;
    }

    /**
     * Purge the audience cache of allowed pages.
     */
    public static function purge_caches(): void {
        cache::make('tool_custompage', 'user_allowed_pages')->purge();
    }

    /**
     * Generate SQL select clause and params for selecting pages specified user can access
     *
     * @param string $custompagetablealias
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return array
     */
    public static function user_pages_list_sql(string $custompagetablealias, ?int $userid = null): array {
        global $DB;

        $allowedpages = self::get_allowed_pages($userid);

        if (empty($allowedpages)) {
            return ['1=0', []];
        }

        // Get all sql audiences.
        $prefix = database::generate_param_name() . '_';
        [$select, $params] = $DB->get_in_or_equal($allowedpages, SQL_PARAMS_NAMED, $prefix);
        $sql = "{$custompagetablealias}.id {$select}";

        return [$sql, $params];
    }

    /**
     * Return list of page persistents specified user can access
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return page[]
     */
    public static function user_pages_list(?int $userid = null): array {
        global $DB;

        $pages = [];

        // Tenant clause, show pages in the current tenant and any global page.
        $paramtenantid = database::generate_param_name();
        $tenantselect = "(cp.tenantid = :{$paramtenantid} OR cp.global = 1)";
        $tenantparam = [$paramtenantid => tenancy::get_tenant_id($userid)];

        [$select, $params] = self::user_pages_list_sql('cp', $userid);
        $sql = "SELECT cp.*
                  FROM {tool_custompage} cp
                 WHERE {$select}
                   AND {$tenantselect}
              ORDER BY cp.weight, cp.name, cp.id";
        $records = $DB->get_records_sql($sql, array_merge($params, $tenantparam));

        foreach ($records as $record) {
            $pages[] = new page(0, $record);
        }

        return $pages;
    }
}
