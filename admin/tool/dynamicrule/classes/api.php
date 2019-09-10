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
 * Dynamic rules internal API.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\rule;
use tool_dynamicrule\permission;
use tool_wp\db;

/**
 * Dynamic rules internal API.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Returns rule.
     *
     * @param int $id ruleid
     * @param bool $bypasstenantcheck
     * @return rule
     * @throws \moodle_exception
     */
    public static function get_rule(int $id, $bypasstenantcheck = false) : rule {
        if ($id && rule::record_exists($id)) {
            $rule = new rule($id);
            // Make sure rule belongs to current user tenant.
            if (!$bypasstenantcheck && ($rule->get('tenantid') != \tool_tenant\tenancy::get_tenant_id())) {
                throw new \moodle_exception('rulenotfound', 'tool_dynamicrule');
            }
            return $rule;
        }
        throw new \moodle_exception('rulenotfound', 'tool_dynamicrule');
    }

    /**
     * Creates a new rule.
     *
     * @param \stdClass $data
     * @return rule
     */
    public static function create_rule(\stdClass $data) : rule {
        if (!isset($data->tenantid)) {
            $data->tenantid = \tool_tenant\tenancy::get_tenant_id();
        }
        $rule = new rule(0, $data);
        $rule->create();
        return $rule;
    }

    /**
     * Creates a rule for component.
     *
     * @param string $component
     * @param string $componentarea
     * @param int $itemid
     * @param int $tenantid
     * @param string $name
     * @param bool $enabled
     * @return int $ruleid
     */
    public static function create_rule_for_component($component, $componentarea, int $itemid,
            int $tenantid, $name = '', $enabled = false) : int {
        if (!$name) {
            $name = $component . '_' . $componentarea . '_' . $itemid;
        }

        $record = new \stdClass();
        $record->name = $name;
        $record->enabled = (int) $enabled;
        $record->matchlimit = 0;
        $record->matchinterval = 0;
        $record->component = $component;
        $record->componentarea = $componentarea;
        $record->itemid = $itemid;
        $record->tenantid = $tenantid;

        $rule = self::create_rule($record);
        return $rule->get('id');
    }

    /**
     * Updates a rule.
     *
     * @param int $ruleid
     * @param \stdClass $newdata
     * @return rule
     */
    public static function update_rule(int $ruleid, \stdClass $newdata) : rule {
        $rule = self::get_rule($ruleid);
        foreach ($newdata as $key => $value) {
            if (rule::has_property($key) && $key !== 'id') {
                $rule->set($key, $value);
            }
        }
        $rule->update();
        return $rule;
    }

    /**
     * Delete a rule.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function delete_rule(int $ruleid) : bool {
        $rule = self::get_rule($ruleid);

        // Make sure rule is archived.
        if (!$rule->is_archived()) {
            throw new \invalid_parameter_exception('Rule must be archived prior to be deleted.');
        }

        return $rule->delete();
    }

    /**
     * Archive a rule.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function archive_rule(int $ruleid) : bool {
        $rule = self::get_rule($ruleid);
        // Disable and archive.
        $rule->set('enabled', 0);
        $rule->set('archived', 1);
        return $rule->update();
    }

    /**
     * Unarchive a rule.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function unarchive_rule(int $ruleid) : bool {
        $rule = self::get_rule($ruleid);
        $rule->set('archived', 0);
        return $rule->update();
    }

    /**
     * Enable a rule.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function enable_rule(int $ruleid) : bool {
        $rule = self::get_rule($ruleid);
        if (!$rule->is_broken() && !$rule->is_archived() && $rule->has_conditions() && $rule->has_outcomes()) {
            $rule->set('enabled', 1);
            if ($enabled = $rule->update()) {
                // Queue rule processing as adhock task, so we do not keep user waiting.
                $adhocktask = new \tool_dynamicrule\task\process_rule();
                $adhocktask->set_custom_data($ruleid);
                $adhocktask->set_component('tool_dynamicrule');
                \core\task\manager::queue_adhoc_task($adhocktask);
            }
            return $enabled;
        }
        return false;
    }

    /**
     * Disable a rule.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function disable_rule(int $ruleid) : bool {
        $rule = self::get_rule($ruleid);
        $rule->set('enabled', 0);
        return $rule->update();
    }

    /**
     * Duplicate a rule.
     *
     * @param int $ruleid
     * @return int
     */
    public static function duplicate_rule(int $ruleid) : int {
        global $DB;
        $rule = self::get_rule($ruleid);
        $newrule = $rule->to_record();
        $i = 1;
        $newname = $newrule->name . ' Copy '. $i;
        while ($DB->record_exists('tool_dynamicrule', ['name' => $newname])) {
            $newname = $newrule->name . ' Copy '. (++$i);
        }
        $newrule->name = $newname;
        $newrule->enabled = 0;
        unset($newrule->id);
        unset($newrule->timecreated);
        unset($newrule->timemodified);
        $newruleid = self::create_rule($newrule)->get('id');
        $conditions = self::get_rule_conditions($ruleid);
        foreach ($conditions as $condition) {
            self::create_rule_condition($newruleid, get_class($condition), $condition->get_configdata());
        }
        $outcomes = self::get_rule_outcomes($ruleid);
        foreach ($outcomes as $outcome) {
            self::create_rule_outcome($newruleid, get_class($outcome), $outcome->get_configdata());
        }
        return $newruleid;
    }

    /**
     * Creates a new condition.
     *
     * @param int $ruleid
     * @param string $conditionclass
     * @param array $configdata
     * @param bool $bypasstenantcheck
     * @return condition_base
     */
    public static function create_rule_condition(int $ruleid, $conditionclass, array $configdata = [], $bypasstenantcheck = false) {
        $rule = self::get_rule($ruleid, $bypasstenantcheck);

        if (!class_exists($conditionclass) || !is_subclass_of($conditionclass, condition_base::class)) {
            throw new \invalid_parameter_exception('Invalid condition class.');
        }

        $condition = new $conditionclass();
        return $condition::create($rule->get('id'), $configdata);
    }

    /**
     * Creates a new outcome.
     *
     * @param int $ruleid
     * @param string $outcomeclass
     * @param array $configdata
     * @param bool $bypasstenantcheck
     * @return outcome_base
     */
    public static function create_rule_outcome(int $ruleid, $outcomeclass, array $configdata = [], $bypasstenantcheck = false) {
        $rule = self::get_rule($ruleid, $bypasstenantcheck);

        if (!class_exists($outcomeclass) || !is_subclass_of($outcomeclass, outcome_base::class)) {
            throw new \invalid_parameter_exception('Invalid outcome class.');
        }

        $outcome = new $outcomeclass();
        return $outcome::create($rule->get('id'), $configdata);
    }

    /**
     * Updates the match table.
     *
     * Returns the list of new users that match conditions of the given rule (that did not match before).
     * When $targetuserid specified, matching query is restricted to a single user,
     * this is used when rule processing triggered by event.
     *
     * @param int $ruleid
     * @param int|null $targetuserid user id for restricting rule matching query
     * @param int $currenttime to use in unittests only, otherwise time() will be used
     * @return \stdClass[] list of of user records where each record has 'id' attribute
     */
    public static function get_matching_users(int $ruleid, int $targetuserid = null, int $currenttime = null): array {
        global $DB;

        $currenttime = $currenttime ?: time();

        if (!self::static_conditions_are_matching($ruleid)) {
            // Static conditions do not match, unmatch all users.
            self::update_unmatched_users($ruleid, [], $currenttime);
            return [];
        }

        // Find all users that match the rule, for each user we will have a "lastmatchid" - whether they were matched before.
        $sql = "SELECT DISTINCT u.id, mlastmatch.id AS lastmatchid
                  FROM {user} u";

        list($join, $where, $params) = self::get_matching_join_sql($ruleid);
        list($condjoin, $condwhere, $condparams) = self::get_rule_conditions_sql($ruleid);

        // Restrict query to single user if required.
        if ($targetuserid !== null) {
            $pg = db::generate_param_name();
            $condparams += [$pg => $targetuserid];
            $condwhere .= " AND u.id = :{$pg} ";
        }

        $users = $DB->get_records_sql($sql . $join . $condjoin . '  WHERE ' . $where . $condwhere,
            $params + $condparams);

        // Now mark in db everybody who no longer matches.
        self::update_unmatched_users($ruleid, $users, $currenttime);

        // Remove from the list users that are already marked as matched.
        $users = array_filter($users, function($u) {
            return empty($u->lastmatchid);
        });

        // If there are restrictions leave only users that satisfy them.
        $users = self::filter_users_by_rule_restriction($ruleid, $users, $currenttime);

        self::insert_matched_users($ruleid, $users, $currenttime);
        return $users;
    }

    /**
     * Returns the part of SQL statement used to retrieve users affected by the rule conditions
     *
     * Example of usage:
     * list($sql, $params) = self::get_dry_run_sql();
     * $DB->get_records_sql('SELECT DISTINCT u.id' . $sql, $params);
     *
     * @param int $ruleid
     * @param int|null $currenttime
     * @return array array [$fromsql, $params]
     */
    private static function get_dry_run_sql(int $ruleid, int $currenttime = null): array {
        // Find all users that match the rule who have not matched before.
        $sql = ' FROM {user} u ';
        list($join, $where, $params) = self::get_matching_join_sql($ruleid);
        list($condjoin, $condwhere, $condparams) = self::get_rule_conditions_sql($ruleid);
        list($restrictionjoin, $groupbyhaving, $restrictionparams) = self::get_rule_restriction_sql($ruleid, $currenttime);
        $sql = $sql . $join . $condjoin . $restrictionjoin .
            '  WHERE ' . $where . $condwhere . ' AND mlastmatch.id IS NULL ' .
            $groupbyhaving;
        $params = $params + $condparams + $restrictionparams;
        return [$sql, $params];
    }

    /**
     * Allows to find users that will be matching the rule conditions that did not match before (no db changes are made here)
     *
     * @param int $ruleid
     * @param int|null $currenttime
     * @return \stdClass[] list of users
     */
    public static function get_matching_users_dry_run(int $ruleid, int $currenttime = null): array {
        global $DB;

        if (!self::static_conditions_are_matching($ruleid)) {
            return [];
        }

        // Find all users that match the rule who have not matched before.
        list($sql, $params) = self::get_dry_run_sql($ruleid, $currenttime);
        return $DB->get_records_sql('SELECT DISTINCT u.id' . $sql, $params);
    }

    /**
     * Return the number of users now matching the conditions of the given rule that did not match before
     *
     * Used for the dry run of the rule
     *
     * @param int $ruleid
     * @param int|null $currenttime
     * @return int
     */
    public static function count_matching_users(int $ruleid, int $currenttime = null): int {
        global $DB;

        if (!self::static_conditions_are_matching($ruleid)) {
            return 0;
        }

        // Find all users that match the rule who have not matched before.
        list($sql, $params) = self::get_dry_run_sql($ruleid, $currenttime);
        $a = self::generate_alias();
        return $DB->get_field_sql("SELECT COUNT(DISTINCT {$a}.id) AS cnt FROM ( SELECT u.id " . $sql . ") {$a}", $params);
    }

    /**
     * Helps to create a query for users who belong to the same tenant including their current match (mlastmatch.id)
     *
     * @param int $ruleid
     * @return array returns [$join, $where, $params] filtering the matches by ruleid, tenant and unmatchedtime
     */
    public static function get_matching_join_sql(int $ruleid): array {
        global $DB;

        // Filter users by tenant.
        $tenantid = $DB->get_field('tool_dynamicrule', 'tenantid', ['id' => $ruleid]);
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $tenantid);

        $p = self::generate_param_name();
        $join .= " LEFT JOIN {tool_dynamicrule_match} mlastmatch
                          ON (mlastmatch.userid = u.id AND
                              mlastmatch.ruleid = :{$p} AND
                              mlastmatch.unmatchedtime IS NULL)";
        $params[$p] = $ruleid;

        return [$join, $where, $params];
    }

    /**
     * Updates the match table to indicate that users no longer match
     *
     * @param int $ruleid
     * @param \stdClass[] $matchedusers list of users who MATCHED (everybody else will be updated)
     * @param int $currenttime
     */
    private static function update_unmatched_users(int $ruleid, array $matchedusers, int $currenttime) {
        global $DB;
        if (!$matchedusers) {
            $unmatcheduseridsql = '';
            $unmatechedparams = [];
        } else {
            list($unmatcheduseridsql, $unmatechedparams) = $DB->get_in_or_equal(array_column($matchedusers, 'id'),
                SQL_PARAMS_NAMED, 'user', false);
            $unmatcheduseridsql = " AND userid {$unmatcheduseridsql}";
        }

        $sql = "UPDATE {tool_dynamicrule_match}
                   SET unmatchedtime = :currenttime
                 WHERE ruleid = :ruleid
                   {$unmatcheduseridsql}
                   AND unmatchedtime IS NULL";

        $unmatechedparams['currenttime'] = $currenttime;
        $unmatechedparams['ruleid'] = $ruleid;

        $DB->execute($sql, $unmatechedparams + $unmatechedparams);
    }

    /**
     * Insert matching users for the given rule with given array of users
     *
     * @param int $ruleid
     * @param \stdClass[] $users list of users who matched
     * @param int $currenttime
     */
    private static function insert_matched_users(int $ruleid, array $users, int $currenttime = null) {
        global $DB;

        $currenttime = $currenttime ?: time();
        $matcheduser = ['ruleid' => $ruleid, 'matchedtime' => $currenttime];
        foreach ($users as $user) {
            $matcheduser['userid'] = $user->id;
            $DB->insert_record('tool_dynamicrule_match', $matcheduser);
        }
    }

    /**
     * Return the list of conditions defined in the system.
     *
     * @return condition_base[]
     */
    public static function get_conditions(): array {
        return self::get_all_instances_of_type('condition');
    }

    /**
     * Return the list of outcomes defined in the system.
     *
     * @return outcome_base[]
     */
    public static function get_outcomes(): array {
        return self::get_all_instances_of_type('outcome');
    }

    /**
     * Returns the list of conditions or outcomes instances in the system.
     *
     * @param string $type Type of instance required (condition or outcome).
     * @return condition_base[]
     */
    private static function get_all_instances_of_type($type): array {
        if (!in_array($type, ['condition', 'outcome'])) {
            throw new \coding_exception('Wrong instance type specified.');
        }

        $instances = [];
        $baseclass = 'tool_dynamicrule\\' . $type . '_base';
        // Go through all plugins and find out which of them got dynamicrule class instances defined.
        $componentinstances = \core_component::get_component_classes_in_namespace(null,
            '\\tool_dynamicrule\\' . $type);
        if (!empty($componentinstances)) {
            // Found component with dynamicrule class instance, add instance to the list.
            foreach (array_keys($componentinstances) as $instanceclass) {
                $reflectionclass = new \ReflectionClass($instanceclass);
                // Verify class parent and that it is not abstract.
                if ($reflectionclass->isSubclassOf($baseclass) &&
                        !$reflectionclass->isAbstract() && $instanceclass::is_available()) {
                    $instances[] = new $instanceclass();
                }
            }
        }
        return $instances;
    }

    /**
     * Return the list of conditions for the given rule
     *
     * @param int $ruleid
     * @return condition_base[]
     */
    public static function get_rule_conditions(int $ruleid): array {
        global $DB;
        $records = $DB->get_records(condition::TABLE, ['ruleid' => $ruleid], 'id');
        $conditions = [];
        foreach ($records as $r) {
            if (class_exists($r->classname) && is_subclass_of($r->classname, condition_base::class)) {
                $conditions[] = new $r->classname(0, $r);
            }
        }
        return $conditions;
    }

    /**
     * Return the list of outcomes for the given rule
     *
     * @param int $ruleid
     * @return outcome_base[]
     */
    public static function get_rule_outcomes($ruleid) {
        global $DB;
        $records = $DB->get_records(outcome::TABLE, ['ruleid' => $ruleid], 'id');
        $outcomes = [];
        foreach ($records as $r) {
            if (class_exists($r->classname) && is_subclass_of($r->classname, outcome_base::class)) {
                $outcomes[] = new $r->classname($r->id, $r);
            }
        }
        return $outcomes;
    }

    /**
     * Process all rules applying the outcomes to users matching the conditions
     */
    public static function process_rules() {
        global $DB;

        $rules = $DB->get_records(rule::TABLE, ['enabled' => 1, 'archived' => 0, 'broken' => 0], 'name');

        foreach ($rules as $rulerecord) {
            // We don't process rules where all conditions are event-based.
            // They are processed on event (see \tool_dynamicrule\event\observer).
            $eventonly = true;
            $ruleconditions = self::get_rule_conditions($rulerecord->id);
            foreach ($ruleconditions as $condition) {
                if ($condition->get_event_subscription() === false) {
                    $eventonly = false;
                    break;
                }
            }
            if (!$eventonly) {
                $rule = new rule(0, $rulerecord);
                self::process_rule($rule);
            }
        }
    }

    /**
     * Process a rule applying the outcomes to users matching the conditions.
     *
     * When $targetuserid specified, matching is restricted to a single user,
     * this is used when rule processing triggered by event.
     *
     * @param rule $rule
     * @param int|null $targetuserid user id for restricting rule matching query
     */
    public static function process_rule($rule, int $targetuserid = null) {
        if ($rule->is_enabled()) {
            $conditionsvalid = self::is_conditions_configuration_valid($rule->get('id'));
            $outcomesvalid = self::is_outcomes_configuration_valid($rule->get('id'));
            if ($conditionsvalid && $outcomesvalid) {
                if ($users = self::get_matching_users($rule->get('id'), $targetuserid)) {
                    self::apply_outcomes($rule->get('id'), $users);
                }
            } else {
                self::mark_rule_as_broken($rule->get('id'));
            }
        }
    }

    /**
     * Applies given outcomes to the given userids
     *
     * @param int $ruleid
     * @param array $userids
     */
    private static function apply_outcomes($ruleid, $userids) {
        $outcomes = self::get_rule_outcomes($ruleid);
        foreach ($outcomes as $o) {
            $o->apply_to_users($userids);
        }
    }

    /**
     * Return true if the static conditions are all matching, false otherwise
     *
     * @param int $ruleid
     * @return bool
     */
    public static function static_conditions_are_matching(int $ruleid): bool {
        $conditions = self::get_rule_conditions($ruleid);
        if (empty($conditions)) {
            return false;
        }
        foreach ($conditions as $condition) {
            if ($condition instanceof condition_static) {
                if (!$condition->is_matching()) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Helps to build SQL for users who satisfy the rule restrictions
     *
     * @param int $ruleid
     * @param int|null $currenttime
     * @return array [$join, $groupbyhaving, $params]
     */
    private static function get_rule_restriction_sql(int $ruleid, int $currenttime = null): array {
        $rule = self::get_rule($ruleid, true);
        if (!$rule->get('matchlimit')) {
            return ['', '', []];
        }

        if (is_null($currenttime)) {
            $currenttime = time();
        }

        $pruleid = self::generate_param_name();
        $ptimelimit = self::generate_param_name();
        $pcountlimit = self::generate_param_name();
        $m = self::generate_alias();

        $limitsql = '';
        if ($rule->get('matchinterval')) {
            $limitsql = "AND {$m}.matchedtime > :{$ptimelimit} ";
        }
        $join = " LEFT JOIN {tool_dynamicrule_match} {$m} ON {$m}.ruleid = :{$pruleid} AND u.id = {$m}.userid " . $limitsql;
        $groupbyhaving = " GROUP BY u.id HAVING count({$m}.id) < :{$pcountlimit} ";

        $params = [
            $pruleid => $ruleid,
            $ptimelimit => ($currenttime - $rule->get('matchinterval')),
            $pcountlimit => $rule->get('matchlimit')
        ];
        return [$join, $groupbyhaving, $params];
    }

    /**
     * If there are rule restrictions filter list of matched users only to those who satisfy restrictions
     *
     * For example, if there is a restriction that rule can not trigger more than once per hour and for some
     * users it has already triggered during the last hour, these users will not be returned
     *
     * @param int $ruleid
     * @param \stdClass[] $matcheduserids list of users who match the conditions, each record record has 'id' property
     * @param int $currenttime
     * @return \stdClass[] list of user records where each record has 'id' property
     */
    private static function filter_users_by_rule_restriction(int $ruleid, array $matchedusers, int $currenttime = null): array {
        global $DB;

        if (!$matchedusers) {
            return [];
        }
        list($join, $groupbyhaving, $params) = self::get_rule_restriction_sql($ruleid, $currenttime);
        if (empty($join) && empty($groupbyhaving)) {
            return $matchedusers;
        }

        list($matchsql, $matchparams) = $DB->get_in_or_equal(array_column($matchedusers, 'id'), SQL_PARAMS_NAMED,
            self::generate_param_name() . 'user');

        $sql = 'SELECT u.id
            FROM {user} u '. $join . '
            WHERE u.id ' . $matchsql . $groupbyhaving;
        return $DB->get_records_sql($sql, $params + $matchparams);

    }

    /**
     * Returns [$join, $where, $params] filtering the users by all conditions.
     *
     * @param int $ruleid
     * @return array array [$join, $where, $params]
     */
    public static function get_rule_conditions_sql(int $ruleid): array {
        $join = '';
        $where = '';
        $params = [];
        foreach (self::get_rule_conditions($ruleid) as $condition) {
            if ($condition instanceof condition_sql) {
                list($newjoin, $newwhere, $newparams) = $condition->get_sql();
                self::check_condition_sql($condition, $newjoin, $newwhere, $newparams);
                $join .= $newjoin;
                $where .= ' AND ' . $newwhere;
                $params += $newparams;
            }
        }
        return [$join, $where, $params];
    }

    /**
     * Various checks for the return value of get_sql() function that show developer warnings.
     *
     * @param condition_sql $condition
     * @param string $join
     * @param string $where
     * @param array $params
     */
    protected static function check_condition_sql(condition_sql $condition, string $join, string $where, array $params) {
        db::validate_params($params, 'SQL for condition ' . get_class($condition) .
            ' uses parameters that were not generated with generate_param_name()');
        $u = db::generate_alias();
        db::validate_sql("SELECT {$u}.id FROM {user} $u $join WHERE $where",
            'SQL for condition ' . get_class($condition) .
            ' uses aliases that were not generated with generate_alias()');
    }

    /**
     * Generates unique table/column alias that must be used in conditions SQL
     *
     * @return string
     */
    public static function generate_alias() {
        return db::generate_alias();
    }

    /**
     * Generates unique parameter name that must be used in conditions SQL
     *
     * @return string
     */
    public static function generate_param_name() {
        return db::generate_param_name();
    }

    /**
     * Check every rule condition and see if it's configuration settings are still valid.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function is_conditions_configuration_valid(int $ruleid): bool {
        $valid = true;
        foreach (self::get_rule_conditions($ruleid) as $condition) {
            if ($condition->is_broken()) {
                $valid = false;
            } else if (!$condition->is_configuration_valid()) {
                $condition->mark_as_broken();
                $valid = false;
            }
        }
        return $valid;
    }

    /**
     * Check every rule outcome and see if it's configuration settings are still valid.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function is_outcomes_configuration_valid(int $ruleid): bool {
        $valid = true;
        foreach (self::get_rule_outcomes($ruleid) as $outcome) {
            if ($outcome->is_broken()) {
                $valid = false;
            } else if (!$outcome->is_configuration_valid()) {
                $outcome->mark_as_broken();
                $valid = false;
            }
        }
        return $valid;
    }

    /**
     * Mark rule as broken
     *
     * @param int $ruleid
     * @return bool
     */
    public static function mark_rule_as_broken($ruleid) {
        $rule = self::get_rule($ruleid);
        $rule->set('enabled', 0);
        $rule->set('broken', 1);
        return $rule->update();
    }

    /**
     * Mark rule as not broken
     *
     * @param int $ruleid
     */
    public static function mark_rule_as_not_broken($ruleid) {
        $rule = self::get_rule($ruleid);
        $conditionsvalid = self::is_conditions_configuration_valid($rule->get('id'));
        $outcomesvalid = self::is_outcomes_configuration_valid($rule->get('id'));
        if ($conditionsvalid && $outcomesvalid) {
            $rule->set('broken', 0);
            $rule->update();
        }
    }

    /**
     * Return the inplace_editable element for rule name.
     *
     * @param \tool_dynamicrule\rule $rule
     * @return \core\output\inplace_editable | false
     */
    public static function get_name_inplace_editable($rule) {
        $formattedname = $displayname = $rule->get_formatted_name();
        $editable = permission::can_edit_rule($rule);
        if ($editable) {
            $editurl = new \moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $rule->get('id')]);
            $displayname = \html_writer::link($editurl, $formattedname);
        }
        return new \core\output\inplace_editable(
            'tool_dynamicrule',
            'rulename',
            $rule->get('id'),
            $editable,
            $displayname,
            $rule->get('name'),
            get_string('editrulename', 'tool_dynamicrule', $formattedname),
            get_string('newnameforrule', 'tool_dynamicrule', $formattedname)
        );
    }

    /**
     * Badges selector.
     *
     * @param string $search
     * @return array
     */
    public static function get_potential_badges(string $search): array {
        global $DB;

        if (!permission::can_manage_rules()) {
            return [];
        }

        // We check for non archived badges.
        // Until MDL-65065 is not fixed we will use badges with no criteria and only check status 4.
        $query = 'SELECT *
                  FROM {badge}
                  WHERE status <> 4';

        $params = [];
        $i = 0;
        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('name', ":search{$i}1", false, false) . ')';
            $params += ["search{$i}1" => '%' . $word . '%'];
        }

        $result = $DB->get_records_sql($query, $params);

        // We apply format string to the name.
        if (!empty($result)) {
            foreach ($result as $res) {
                $res->name = format_string($res->name, true,
                    ['context' => \context_system::instance(), 'escape' => false]);
            }
        }

        return $result;
    }

    /**
     * Competencies selector.
     *
     * @param string $search
     * @return array
     */
    public static function get_potential_competencies(string $search): array {
        global $DB;

        if (!permission::can_manage_rules()) {
            return [];
        }

        $query = "SELECT *
            FROM {competency}
            WHERE 1=1";

        $i = 0;
        $params = [];
        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('shortname', ":search{$i}1", false, false) . ')';
            $params += ["search{$i}1" => '%' . $word . '%'];
        }

        $result = $DB->get_records_sql($query, $params);

        // We apply format string to the shortname.
        if (!empty($result)) {
            foreach ($result as $res) {
                $res->shortname = format_string($res->shortname, true,
                    ['context' => \context_system::instance(), 'escape' => false]);
            }
        }

        return $result;
    }
}
