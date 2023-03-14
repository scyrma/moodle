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
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use tool_organisation\event\department_created;
use tool_organisation\event\department_deleted;
use tool_organisation\event\department_updated;
use tool_organisation\event\position_created;
use tool_organisation\event\position_deleted;
use tool_organisation\event\position_updated;
use tool_tenant\hierarchy as tenanthierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class hierarchy_manager {

    /**
     * Returns the name of the persistent class that holds an instance
     *
     * @return string
     */
    abstract protected function get_class() : string;

    /**
     * Returns the name of the DB table for an instance
     *
     * @return string
     * @throws \coding_exception
     */
    protected function get_table() : string {
        $class = $this->get_class();
        if (!class_exists($class) || !is_subclass_of($class, hierarchy::class)) {
            throw new \coding_exception('Class must extend hierarchy');
        }
        return $class::TABLE;
    }

    /**
     * Returns an instance of a hierarchy
     *
     * @param array $conditions
     * @param bool $currenttenantonly only allow to create in the current tenant (default: true). Can be set to false
     *     from unittests and imports
     * @return hierarchy
     * @throws \moodle_exception
     */
    protected function get_hierarchy_entity(array $conditions, bool $currenttenantonly = true) : hierarchy {
        global $DB;
        $class = $this->get_class();
        if ($currenttenantonly) {
            $conditions['tenantid'] = tenancy::get_tenant_id();
        }
        if (!$record = $DB->get_record($this->get_table(), $conditions)) {
            if ($this instanceof department_manager) {
                throw new \moodle_exception('departmentnotfound', 'tool_organisation');
            } else if ($this instanceof position_manager) {
                throw new \moodle_exception('positionnotfound', 'tool_organisation');
            } else {
                throw new \moodle_exception('Entity not found');
            }
        }
        return new $class(0, $record);
    }

    /**
     * Returns the list of hierarchy entities
     *
     * @param array $conditions
     * @param string $sortorder
     * @return array
     */
    protected function get_hierarchy_entities(array $conditions, string $sortorder = 'sortorder, id') : array {
        global $DB;
        $class = $this->get_class();

        // Filter by same or parent tenant.
        [$sql, $params] = tenanthierarchy::filter_own_or_parent_shared_entities_sql("tenantid", "shared=1");

        // It seems that get_records_select() is not picking up that parentid => null.
        if (array_key_exists('parentid', $conditions) && is_null($conditions['parentid'])) {
            $sql .= ' AND parentid IS NULL';
            unset($conditions['parentid']);
        }
        $params = array_merge($conditions, $params);

        $records = $DB->get_records_select($this->get_table(), $sql, $params, $sortorder);

        $entities = [];
        foreach ($records as $record) {
            $entity = new $class(0, $record);
            if (permission::can_access_entity($entity)) {
                $entities[$record->id] = new $class(0, $record);
            }
        }
        return $entities;
    }

    /**
     * Returns a flat list of children
     *
     * @param hierarchy $entity
     * @param bool $includearchived
     * @return hierarchy[]
     */
    public function get_all_children(hierarchy $entity, bool $includearchived = false) : array {
        global $DB;
        $class = $this->get_class();

        $wheresql = $DB->sql_like('path', ':pathmask');
        if (!$includearchived) {
            $wheresql .= ' AND archived = 0';
        }
        $records = $DB->get_records_select($this->get_table(),
            $wheresql,
            ['pathmask' => $entity->get('path') . '/%'],
            'pathlevel, sortorder, id');
        $entities = [];
        foreach ($records as $record) {
            $entities[$record->id] = new $class(0, $record);
        }
        return $entities;
    }

    /**
     * The framework is too big (has more than 200 children), disable drag&drop sorting in the UI
     *
     * @param hierarchy $entity
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function too_many_children_disable_sorting(hierarchy $entity): bool {
        global $DB;
        $wheresql = $DB->sql_like('path', ':pathmask');
        $cnt = $DB->count_records_select($this->get_table(),
            $wheresql,
            ['pathmask' => $entity->get('path') . '/%']);
        return $cnt > 200;
    }

    /**
     * Returns a tree structure
     *
     * @param int $frameworkid
     * @param bool $includearchived
     * @return hierarchy
     */
    protected function get_hierarchy_structure(int $frameworkid, bool $includearchived = false) : hierarchy {
        $framework = $this->get_hierarchy_entity(['id' => $frameworkid] + ($includearchived ? [] : ['archived' => 0]), false);

        $children = $this->get_all_children($framework, $includearchived);
        $entities = [$frameworkid => $framework];
        $haserrors = false;
        // Build the tree and while doing it check that there are no orphaned records and all 'path' and 'pathlevel'
        // attributes are correct.
        do {
            $found = false;
            foreach ($children as $id => $child) {
                if (!$child->get('parentid')) {
                    $haserrors = $haserrors || ($child->get('pathlevel') != 1) || ($child->get('path') !== '/' . $child->get('id'));
                } else if (array_key_exists($child->get('parentid'), $entities)) {
                    $parent = &$entities[$child->get('parentid')];
                    $entities[$id] = $parent->add_child($child);
                    $haserrors = $haserrors || ($child->get('pathlevel') != $parent->get('pathlevel') + 1)
                        || ($child->get('path') !== $parent->get('path') . '/' . $child->get('id'));
                    unset($children[$id]);
                    $found = true;
                } else {
                    $haserrors = true;
                }
            }
        } while ($found);
        if ($children || $haserrors) {
            debugging('Orphaned or inconsistent records found in the hierarchy structure, we will attempt to fix them',
                DEBUG_DEVELOPER);
            $this->fix_hierarchy_paths();
        }
        return $framework;
    }

    /**
     * Creates a new department
     *
     * @param hierarchy $parent
     * @param \stdClass $data
     * @param bool $currenttenantonly only allow to create in the current tenant (default: true). Can be set to false
     *     from unittests and imports
     * @return hierarchy
     * @throws \coding_exception
     */
    protected function create_hierarchy(hierarchy $parent, \stdClass $data, bool $currenttenantonly = true) : hierarchy {
        global $DB;
        if (isset($data->parentid) && ($parent->get('id') != $data->parentid)) {
            throw new \coding_exception('Parent argument does not match');
        }
        if ($parent->get('tenantid')) {
            $data->tenantid = $parent->get('tenantid');
        } else if (empty($data->tenantid) || $currenttenantonly) {
            $data->tenantid = tenancy::get_tenant_id();
        }
        if ($parent->get('id')) {
            $data->parentid = $parent->get('id');
            $data->path = $parent->get('path') . '/';
            $data->pathlevel = $parent->get('pathlevel') + 1;
        } else {
            $data->parentid = null;
            $data->path = '/';
            $data->pathlevel = 1;
        }

        if (isset($data->parentid)) {
            // If it has parentid set the same shared value as its parent.
            $data->shared = $parent->get('shared');
        } else {
            // If it doesn't have parentid it must be a framework. Set shared property depending if is in Shared space or not.
            $data->shared = sharedspace::is_shared_space($data->tenantid) ? 1 : 0;
        }

        if (!isset($data->sortorder)) {
            $lastsortorder = $DB->get_field($this->get_table(), 'MAX(sortorder)',
                ['archived' => 0, 'parentid' => $data->parentid]);
            $data->sortorder = ($lastsortorder === null) ? 0 : ($lastsortorder + 1);
        }
        // Set department as not locked by default if no value has been set.
        if (!isset($data->locked)) {
            $data->locked = 0;
        }
        $classname = get_class($parent);
        $entity = new $classname(0, $data);
        $entity->create();
        $data->path .= $entity->get('id');
        $entity->set('path', $data->path);
        $entity->update();
        if ($entity instanceof department) {
            department_created::create_from_object($entity)->trigger();
        } else if ($entity instanceof position) {
            position_created::create_from_object($entity)->trigger();
        }
        return $entity;
    }

    /**
     * Updates a hierarchy
     *
     * @param hierarchy $entity
     * @param \stdClass $newdata
     * @return hierarchy same object that was passed to this method
     */
    protected function update_hierarchy_entity(hierarchy $entity, \stdClass $newdata) : hierarchy {
        $oldrecord = $entity->to_record();
        $classname = get_class($entity);
        foreach ($newdata as $key => $value) {
            if ($classname::has_property($key) && $key !== 'id') {
                $entity->set($key, $value);
            }
        }
        $entity->save();
        if ($entity instanceof department) {
            department_updated::create_from_object($entity, $oldrecord)->trigger();
        } else if ($entity instanceof position) {
            position_updated::create_from_object($entity, $oldrecord)->trigger();
        }
        return $entity;
    }

    /**
     * Returns direct children of a given department
     *
     * @param hierarchy $parent
     * @return hierarchy[]
     */
    protected function fetch_hierarchy_children(hierarchy $parent) : array {
        global $DB;
        $rs = $DB->get_recordset($this->get_table(),
            ['parentid' => $parent->get('id'), 'archived' => 0], 'sortorder, id');
        $class = get_class($parent);
        foreach ($rs as $child) {
            $parent->add_child(new $class(0, $child));
        }
        $rs->close();
        return $parent->get_children();
    }

    /**
     * Moves department to a new place in hierarchy
     *
     * @param int $id
     * @param int $parentid
     * @param int $beforeid
     * @throws \moodle_exception
     */
    public function move(int $id, int $parentid, int $beforeid = 0) {
        $class = $this->get_class();

        // Validate that departments with given ids exist and move is allowed.
        $hierarchy = $this->get_hierarchy_entity(['id' => $id, 'archived' => 0]);
        if ($hierarchy->is_framework()) {
            if ($parentid) {
                throw new \moodle_exception('errormovehierarchy', 'tool_organisation');
            }
            $parent = null;
            $parentid = null;
        } else {
            $parent = $this->get_hierarchy_entity(['id' => $parentid, 'archived' => 0]);
            if ($parent->get_framework_id() != $hierarchy->get_framework_id()) {
                throw new \moodle_exception('errormovehierarchy', 'tool_organisation');
            }
            if (strpos($parent->get('path'), $hierarchy->get('path') . '/') === 0) {
                // Can't move under own [grand]child.
                throw new \moodle_exception('errormovehierarchy', 'tool_organisation');
            }
        }

        // Move to another parent if necessary.
        if ($parentid != $hierarchy->get('parentid')) {
            // We only want to trigger event when parent is changed. When changing sort order only events are not triggered.
            $params = (object)['parentid' => $parentid, 'pathlevel' => $parent->get('pathlevel') + 1,
                'path' => $parent->get('path') . '/' . $hierarchy->get('id')];
            $this->update_hierarchy_entity($hierarchy, $params);
            // Fix levels and paths of the children.
            $this->fix_hierarchy_paths();
        }

        // Change sortorders of this department and its siblings.
        $siblings = $parent ? $this->fetch_hierarchy_children($parent) :
            $this->get_hierarchy_entities(['archived' => 0, 'parentid' => null]);
        unset($siblings[$id]);
        $ids = array_keys($siblings);
        if (!$beforeid || !array_key_exists($beforeid, $siblings)) {
            $ids[] = $id;
        } else {
            $idx = array_search($beforeid, $ids);
            $ids = array_merge(array_slice($ids, 0, $idx), [$id], array_slice($ids, $idx));
        }
        $siblings[$id] = $hierarchy;
        foreach ($ids as $idx => $siblingid) {
            if ($siblings[$siblingid]->get('sortorder') != $idx) {
                $siblings[$siblingid]->set('sortorder', $idx);
                $siblings[$siblingid]->save();
            }
        }
    }

    /**
     * Find best parent for orphaned entity
     *
     * @param hierarchy $entity
     * @param hierarchy[] $entities
     */
    protected function find_best_parent_for_orphaned_entity(hierarchy $entity, array &$entities) {
        $id = $entity->get('id');
        if ($entity->get('pathlevel') == 1 || $entity->get('path') === '/' . $id) {
            // This is a framework.
            $entity->set('parentid', null);
            $entity->save();
            return;
        }

        // Try to search for a new parent in the path.
        $paths = preg_split('|/|', $entity->get('path'));
        for ($i = count($paths) - 1; $i > 0; $i--) {
            $parentid = (int)$paths[$i];
            if ($id != $parentid && array_key_exists($parentid, $entities)) {
                $entity->set('parentid', $parentid);
                $entity->save();
                return;
            }
        }

        // Just move to the first framework or create one if needed.
        $frameworks = [];
        foreach ($entities as $id => &$entity) {
            if (!$entity->get('parentid')) {
                $frameworks[$id] = $entity->get('sortorder');
            }
        }
        if ($frameworks) {
            asort($frameworks);
            $fid = key($frameworks);
        } else {
            $classname = get_class($entity);
            $framework = $this->create_hierarchy(new $classname(), (object)['name' => 'Orphaned']);
            $fid = $framework->get('id');
            $entities[$fid] = $framework;
        }
        $entity->set('parentid', $fid);
        $entity->save();
    }

    /**
     * Fix path and pathlevel attributes for all entities
     */
    public function fix_hierarchy_paths() {
        $processed = [];
        $needfixing = false;
        $entities = $this->get_hierarchy_entities([]);

        foreach ($entities as $id => &$entity) {
            $level = $entity->get('pathlevel');
            $path = $entity->get('path');
            if ($entity->get('parentid') && !array_key_exists($entity->get('parentid'), $entities)) {
                // Orphaned record!
                $this->find_best_parent_for_orphaned_entity($entity, $entities);
            }

            if (!$entity->get('parentid')) {
                $expectedlevel = 1;
                $expectedpath = '/' . $id;
                if ($level != $expectedlevel || $path !== $expectedpath) {
                    $entity->set('pathlevel', $expectedlevel);
                    $entity->set('path', $expectedpath);
                    $entity->save();
                    $needfixing = true;
                }
                $processed[$id] = $id;
            } else {
                $parent = $entities[$entity->get('parentid')];
                $expectedlevel = $parent->get('pathlevel') + 1;
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
                    $level = $entity->get('pathlevel');
                    $path = $entity->get('path');
                    $expectedlevel = $parent->get('pathlevel') + 1;
                    $expectedpath = $parent->get('path') . '/' . $id;
                    if ($level != $expectedlevel || $path !== $expectedpath) {
                        $entity->set('pathlevel', $expectedlevel);
                        $entity->set('path', $expectedpath);
                        $entity->save();
                    }
                    $processed[$id] = $id;
                }
            }
        }
    }

    /**
     * Editor options for description field
     *
     * @return array
     */
    public static function get_description_editor_options() {
        global $CFG;
        return [
            'maxfiles'   => EDITOR_UNLIMITED_FILES,
            'maxbytes'   => $CFG->maxbytes,
            'trusttext'  => false,
            'forcehttps' => false,
            'context'    => \context_system::instance()
        ];
    }

    /**
     * Name of filearea for position description
     * @return string
     */
    abstract public static function get_description_filearea(): string;

    /**
     * Deletes hierarchy entity and all its children
     *
     * This method assumes that there is no associated information left (i.e. jobs)
     *
     * @param hierarchy $entity
     */
    protected function delete_hierarchy_entity(hierarchy $entity) {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $entities = [$entity->get('id') => $entity] + $this->get_all_children($entity, true);
        foreach ($entities as $id => $entity) {
            $fs->delete_area_files($context->id, 'tool_organisation',
                static::get_description_filearea(), $entity->get('id'));
            $entity->delete();
            if ($entity instanceof department) {
                department_deleted::create_from_object($entity)->trigger();
            } else if ($entity instanceof position) {
                position_deleted::create_from_object($entity)->trigger();
            }
        }
    }

    /**
     * Delete all entities inside one tenant. Used when tenant is deleted.
     *
     * @param int $tenantid
     */
    public function delete_all_entities_for_tenant(int $tenantid) {
        global $DB;
        // Remove frameworks.
        $records = $DB->get_records($this->get_table(), ['tenantid' => $tenantid, 'parentid' => null], '');
        $class = $this->get_class();
        $entities = [];
        foreach ($records as $record) {
            $entities[$record->id] = new $class(0, $record);
        }
        foreach ($entities as $entity) {
            $this->delete_hierarchy_entity($entity);
        }

        // Remove orphans. We do it one by one because it may trigger children deletion.
        while ($record = $DB->get_record($this->get_table(), ['tenantid' => $tenantid], '*', IGNORE_MULTIPLE)) {
            $this->delete_hierarchy_entity(new $class(0, $record));
        }
    }

    /**
     * Get potential position/departments for the parent selector in the same framework.
     * Excludes current entity, its parent and all its children.
     *
     * @param string $search
     * @param hierarchy|null $entity
     * @param hierarchy|null $fwentity
     * @return array
     */
    public function get_potential_parents(string $search, ?hierarchy $entity = null, ?hierarchy $fwentity = null): array {
        global $DB;

        $params = [];

        $frameworkid = $entity ? $entity->get_framework_id() : $fwentity->get('id');
        $params += ['fwid' => $frameworkid];
        $frameworkpathsql = $DB->sql_like('path', ':fwpathmask');
        $params += ['fwpathmask' => '/' . $frameworkid . '/%'];

        if (!$entity) {
            $params += ['entityid' => $fwentity->get('id')];

            $query = 'SELECT id, name, path
                FROM {' . $this->get_table() . '}
                WHERE archived = 0
                  AND (id = :fwid
                    OR ' . $frameworkpathsql . '
                    OR id = :entityid)';
        } else {
            $params += ['entityid' => $entity->get('id')];
            $params += ['cdpathmask' => '%/' . $entity->get('id') . '/%'];
            $currentpathsql = $DB->sql_like('path', ':cdpathmask', true, true, true);

            $query = 'SELECT id, name, path
                FROM {' . $this->get_table() . '}
                WHERE archived = 0
                  AND id != :entityid
                  AND (id = :fwid
                    OR (' . $frameworkpathsql . '
                      AND ' . $currentpathsql . '))';
        }

        return $this->format_parents_dropdown($search, $query, $frameworkpathsql, $params);
    }

    /**
     * Return formated position/departments for dropdown selector.
     *
     * @param string $search
     * @param string $query
     * @param string $frameworkpathsql
     * @param array $params
     * @return array
     */
    private function format_parents_dropdown(string $search, string $query, string $frameworkpathsql, array $params): array {
        global $DB;

        $i = 0;
        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('name', ":search{$i}1", false, false)
                . ' OR ' .
                $DB->sql_like('idnumber', ":search{$i}2", false, false)
                . ')';
            $params += ["search{$i}1" => '%' . $word . '%', "search{$i}2" => '%' . $word . '%'];
        }

        $query .= " ORDER BY path";

        $results = $DB->get_records_sql($query, $params);

        // Main framework from search.
        // If the main framework is not in the search results, name it "Top" and search it again.
        // If the framework was added to the results, sort the results by path.
        if (!array_key_exists($params['fwid'], $results)) {
            $mainframework = $DB->get_record_select($this->get_table(), ':top LIKE :fullsearch AND id = :fwid',
                ['top' => strtolower(get_string('top')), 'fullsearch' => strtolower("%{$search}%"), 'fwid' => $params['fwid']],
                'id, name, path');
            if (!empty($mainframework)) {
                $results[$mainframework->id] = $mainframework;
                if (count($results) > 1) {
                    usort($results, function($a, $b) {
                        return $a->path <=> $b->path;
                    });
                }
            }
        }

        // Get all the hierarchy names in this framework.
        $alldepartmentsnames = $DB->get_records_select($this->get_table(), $frameworkpathsql,
            ['fwpathmask' => $params['fwpathmask']], '', 'id, name');

        // Format the result.
        $formatparams = ['context' => \context_system::instance(), 'escape' => false];
        foreach ($results as $result) {
            // Format name.
            $optionname = $params['fwid'] == $result->id ? get_string('top') : $result->name;
            $result->name = format_string($optionname, true, $formatparams);
            // Format path.
            $path = preg_split('|/|', $result->path, -1, PREG_SPLIT_NO_EMPTY);
            $path = array_slice($path, 1, count($path) - 2);
            foreach ($path as $key => $id) {
                $path[$key] = format_string($alldepartmentsnames[$id]->name, true, $formatparams);
            }
            if (count($path) > 3) {
                $path = array_slice($path, -3, 3);
                array_unshift($path, '...');
            }
            $result->path = empty($path) ? '' : '(' . implode('/', $path) . ')';
        }

        return $results;
    }
}

