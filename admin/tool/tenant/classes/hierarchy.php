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

/**
 * Class hierarchy
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use core_reportbuilder\local\helpers\database;

/**
 * Class hierarchy
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class hierarchy {

    /**
     * Returns an array of ids of the parent tenants of the current/specified tenant
     *
     * @param int $tenantid
     * @return array
     */
    public static function get_parent_tenants_ids(int $tenantid = 0): array {
        $tenant = tenancy::get_tenants()[$tenantid ?: tenancy::get_tenant_id()] ?? null;
        if (!$tenant) {
            return [];
        }
        $parents = preg_split('|/|', $tenant->path, -1, PREG_SPLIT_NO_EMPTY);
        array_pop($parents);
        return array_map(function($id) {
            return (int)$id;
        }, $parents);
    }

    /**
     * Allows to build SQL to show only entities from the current tenant or shared entities from parent tenant
     *
     * Example of usage in a report:
     * [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tp.tenantid', 'tp.shared=1');
     * $this->add_base_condition_sql($sql, $params);
     *
     * @param string $tenantidsql SQL select for the tenant id from the main query, for example "tp.tenantid"
     * @param string $issharedsql SQL condition whether the entity is shared, for example "tp.shared=1" or null if any
     *    entity is considered shared
     * @param int $currenttenantid id of the tenant that we should consider "current". For example, when we look for
     *    programs that are available in a dynamic rule - this will be a tenant of the dynamic rule (which may not be
     *    the current tenant, especially inside cron or an event listener).
     * @return array [$sql, $params]
     */
    public static function filter_own_or_parent_shared_entities_sql(string $tenantidsql, ?string $issharedsql = null,
                int $currenttenantid = 0): array {
        global $DB;
        $currenttenantid = $currenttenantid ?: tenancy::get_tenant_id();
        $parentids = self::get_parent_tenants_ids($currenttenantid);
        $p1 = database::generate_param_name();
        $ownparams = [$p1 => $currenttenantid];
        $ownsql = "$tenantidsql=:$p1";
        if (!$parentids) {
            return [$ownsql, $ownparams];
        }

        [$sql, $params] = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED,
            database::generate_param_name() . '_', true, 0);
        $issharedsql = $issharedsql === null ? '' : "$issharedsql AND ";
        return ["($ownsql OR ($issharedsql $tenantidsql $sql))", $params + $ownparams];
    }

    /**
     * Checks if the given entity is from the current tenant or shared entity from a parent tenant
     *
     * @param int $entitytenantid tenant id of the entity
     * @param bool $sharedcondition is this entity "shared", is it available if it is in the parent tenant
     * @param int $currenttenantid id of the tenant that we should consider "current" (normally not needed unless
     *    we are inside cron or event listener)
     * @return bool
     */
    public static function is_own_or_parent_shared_entity(int $entitytenantid, bool $sharedcondition,
            int $currenttenantid = 0): bool {
        $currenttenantid = $currenttenantid ?: tenancy::get_tenant_id();
        return ($entitytenantid == $currenttenantid ||
            ($sharedcondition && self::is_subtenant_of($currenttenantid, $entitytenantid)));
    }

    /**
     * Returns SQL to find all sub-tenants of a given tenant
     *
     * @param int $tenantid
     * @param bool $includeself
     * @return array
     */
    public static function get_subtenants_sql(int $tenantid = 0, bool $includeself = true): array {
        global $DB;
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        $tenant = tenancy::get_tenants()[$tenantid];
        $param = database::generate_param_name();
        $alias = database::generate_alias();
        $wheresql = $DB->sql_like($alias.'.path', ":$param");
        $params = [$param => $tenant->path.'/%'];
        if ($includeself) {
            $param2 = database::generate_param_name();
            $wheresql = "{$alias}.id = :{$param2} OR (".$wheresql.")";
            $params[$param2] = $tenantid;
        }
        $sql = " IN (select {$alias}.id from {tool_tenant} {$alias} WHERE ".$wheresql.") ";
        return [$sql, $params];
    }

    /**
     * Checks if one tenant is a subtenant of another tenant (direct or with intermediate parents)
     *
     * @param int $tenantid
     * @param int $parenttenantid
     * @return bool
     */
    public static function is_subtenant_of(int $tenantid, int $parenttenantid = 0): bool {
        $tenant = tenancy::get_tenants()[$tenantid] ?? null;
        $parenttenant = tenancy::get_tenants()[$parenttenantid ?: tenancy::get_tenant_id()] ?? null;
        return $tenant && $parenttenant &&
            substr($tenant->path, 0, strlen($parenttenant->path) + 1) === $parenttenant->path . '/';
    }

    /**
     * Fixes the 'path' and 'depth' fields in the tool_tenant table
     */
    public static function fix_hierarchy_paths() {
        $processed = [];
        $needfixing = false;
        $entities = [];
        foreach (\tool_tenant\tenant::get_records([]) as $record) {
            $entities[$record->get('id')] = $record;
        }

        foreach ($entities as $id => &$entity) {
            $level = $entity->get('depth');
            $path = $entity->get('path');
            if ($entity->get('parentid') && !array_key_exists($entity->get('parentid'), $entities)) {
                // Orphaned record!
                $entity->set('parentid', null);
                $entity->save();
                $needfixing = true;
            }

            if (!$entity->get('parentid')) {
                $expectedlevel = 1;
                $expectedpath = '/' . $id;
                if ($level != $expectedlevel || $path !== $expectedpath) {
                    $entity->set('depth', $expectedlevel);
                    $entity->set('path', $expectedpath);
                    $entity->save();
                    $needfixing = true;
                }
                $processed[$id] = $id;
            } else {
                $parent = $entities[$entity->get('parentid')];
                $expectedlevel = $parent->get('depth') + 1;
                $expectedpath = $parent->get('path') . '/' . $id;
                if ($level != $expectedlevel || $path != $expectedpath) {
                    $needfixing = true;
                }
            }
        }

        if (!$needfixing) {
            return;
        }

        while (count($processed) < count($entities)) {
            foreach ($entities as $id => &$entity) {
                if (!array_key_exists($id, $processed) && array_key_exists($entity->get('parentid'), $processed)) {
                    $parent = $entities[$entity->get('parentid')];
                    $level = $entity->get('depth');
                    $path = $entity->get('path');
                    $expectedlevel = $parent->get('depth') + 1;
                    $expectedpath = $parent->get('path') . '/' . $id;
                    if ($level != $expectedlevel || $path !== $expectedpath) {
                        $entity->set('depth', $expectedlevel);
                        $entity->set('path', $expectedpath);
                        $entity->save();
                    }
                    $processed[$id] = $id;
                }
            }
        }
    }

    /**
     * Allows to build SQL to show entities from the current tenant or its sub-tenants or shared entities from parent tenant
     *
     * @param string $tenantidsql SQL select for the tenant id from the main query, for example "tp.tenantid"
     * @param string|null $issharedsql SQL condition whether the entity is shared, for example "tp.shared=1" or null if any
     *    entity is considered shared
     * @param int $currenttenantid id of the tenant that we should consider "current". For example, when we look for
     *    programs that are available in a dynamic rule - this will be a tenant of the dynamic rule (which may not be
     *    the current tenant, especially inside cron or an event listener).
     * @return array [$sql, $params]
     */
    public static function filter_own_or_sub_or_parent_shared_entities_sql(string $tenantidsql, ?string $issharedsql = null,
                                                                           int $currenttenantid = 0): array {
        $currenttenantid = $currenttenantid ?: tenancy::get_tenant_id();
        $haschildren = (bool)array_filter(tenancy::get_tenants(), function($tenant) use ($currenttenantid) {
            return $tenant->parentid == $currenttenantid;
        });
        [$sql1, $params1] = self::filter_own_or_parent_shared_entities_sql($tenantidsql, $issharedsql, $currenttenantid);
        if (!$haschildren) {
            return [$sql1, $params1];
        } else {
            $hasparents = (bool)self::get_parent_tenants_ids($currenttenantid);
            [$sql2, $params2] = self::get_subtenants_sql($currenttenantid, !$hasparents);
            $sql2 = "$tenantidsql $sql2";
            if (!$hasparents) {
                return [$sql2, $params2];
            }
        }
        return ["($sql1 OR $sql2)", $params1 + $params2];
    }

    /**
     * Returns SQL fragment and params to restrict entities to the current tenant or any of it's sub-tenants, where the entity
     * doesn't have a shared property (e.g. "jobs")
     *
     * @param string $tenantidsql
     * @param int $currenttenantid
     * @return array [$sql, $params]
     */
    public static function filter_own_or_sub_entities_sql(string $tenantidsql, int $currenttenantid = 0): array {
        [$sql, $params] = self::get_subtenants_sql($currenttenantid, true);

        return ["{$tenantidsql} {$sql}", $params];
    }

    /**
     * Checks if the given tenant has subtenants
     *
     * @param int $tenantid
     * @return bool
     */
    public static function has_subtenants(int $tenantid): bool {
        $tenant = tenancy::get_tenants()[$tenantid] ?? null;
        return $tenant ? (bool)$tenant->haschildren : false;
    }

    /**
     * Checks that idnumber is unique for this tenant and the shared space.
     *
     * In case if checked in the shared space the idnumber has to be unique everywhere
     *
     * @param string $table table that holds info about the entity, should have 'idnumber' and 'tenantid' fields
     * @param string|null $idnumber idnumber, if empty then it is considered unique
     * @param int|null $id entity id if already exists in the table
     * @param int $tenantid tenant id where entity is or will be (optional, defaults to current tenant)
     * @param string|null $extraquery additional query, for example exclude archived entities from search
     * @param array $extraparams additional params for the extra query
     * @return bool
     */
    public static function is_entity_idnumber_unique(string $table, ?string $idnumber,
            ?int $id = 0, int $tenantid = 0, ?string $extraquery = null, array $extraparams = []): bool {
        global $DB;
        if (!strlen($idnumber ?? '')) {
            return true;
        }
        $sql = $DB->sql_equal('idnumber', ':idnumber', false);
        $params = ['idnumber' => $idnumber];

        if ($id) {
            $sql .= ' AND id <> :id';
            $params['id'] = $id;
        }

        $tenantid = $tenantid ?: \tool_tenant\tenancy::get_tenant_id();
        $sharedspaceid = sharedspace::get_shared_space_id();
        if ($sharedspaceid === $tenantid) {
            // For entities in shared space, idnumber must be unique everywhere.
            null;
        } else if ($sharedspaceid) {
            // If shared space is enabled, the idnumber must be unique for this tenant and sharedspace.
            $sql .= " AND (tenantid = :tenantid OR tenantid = :sharedspaceid)";
            $params['tenantid'] = $tenantid;
            $params['sharedspaceid'] = $sharedspaceid;
        } else {
            // Otherwise idnumber must be unique within this tenant.
            $sql .= " AND tenantid = :tenantid";
            $params['tenantid'] = $tenantid;
        }

        if ($extraquery) {
            $sql .= " AND $extraquery";
            $params += $extraparams;
        }
        return !$DB->record_exists_select($table, $sql, $params);
    }

    /**
     * Finds a record by idnumber in the given tenant and the shared space.
     *
     * @param string $table table that holds info about the entity, should have 'idnumber' and 'tenantid' fields
     * @param string $idnumber idnumber used for the lookup, can not be an empty string
     * @param int $tenantid tenant id where we initiate the search (optional, defaults to current tenant)
     * @param string|null $extraquery for example 'archived=0'
     * @param array $extraparams parameters for extra query
     * @param bool $includeshared include entities from the shared space
     * @param string|null $issharedsql additional query for the entities from parent tenants (f.e. 'shared=1')
     * @return \stdClass|null full record from the specified table if found, null otherwise
     */
    public static function find_entity_by_idnumber(string $table, string $idnumber, int $tenantid = 0,
                ?string $extraquery = null, array $extraparams = [],
                bool $includeshared = true, ?string $issharedsql = null): ?\stdClass {
        global $DB;

        // We need a case insensitive comparison on the value.
        $sql = $DB->sql_equal('idnumber', ':idnumber', false);
        $params = ['idnumber' => $idnumber];

        if ($includeshared) {
            [$sqlshared, $paramsshared] =
                self::filter_own_or_parent_shared_entities_sql('tenantid', $issharedsql, $tenantid);
            $sql .= " AND $sqlshared";
            $params += $paramsshared;
        } else {
            $sql .= " AND tenantid = :tenantid";
            $params['tenantid'] = $tenantid ?: tenancy::get_tenant_id();
        }
        if ($extraquery) {
            $sql .= " AND $extraquery";
            $params += $extraparams;
        }

        return $DB->get_record_select($table, $sql, $params) ?: null;
    }
}
