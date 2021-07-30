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
 * Dynamic rules internal API.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die();

use tool_wp\db;

/**
 * Dynamic rules internal API.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api {

    /** @var int User processing is in progress */
    const STATUS_IN_PROGRESS = 0;
    /** @var int User processing completed without errors */
    const STATUS_DONE = 1;
    /** @var int User processing resulted in error */
    const STATUS_ERROR = 2;

    /**
     * Different DB engines have different issues in IN statement processing, we use constant
     * below to chunk users processing in several parts, thus avoiding large number of params.
     * For more details, see MDL-53735.
     *
     * @var int The size of chunk of users array used for filtering to satisfy DB engine.
     */
    const FILTER_USERS_CHUNK_SIZE = 1000;

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
        if (!$rule->is_archived() &&
            $rule->has_conditions() &&
            $rule->has_outcomes() &&
            self::is_rule_configuration_valid($rule)) {
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

        return $conditionclass::create($rule->get('id'), $configdata);
    }

    /**
     * Returns rule condition.
     * @deprecated in WP-1912
     *
     * @param int $conditionid
     * @return condition_base
     */
    public static function get_rule_condition(int $conditionid): condition_base {
        debugging('Function get_rule_condition() should not be used, use condition_base::instance() instead', DEBUG_DEVELOPER);
        return condition_base::instance($conditionid);
    }

    /**
     * Returns rule outcome.
     * @deprecated in WP-1912
     *
     * @param int $outcomeid
     * @return outcome_base
     */
    public static function get_rule_outcome(int $outcomeid) : outcome_base {
        debugging('Function get_rule_outcome() should not be used, use outcome_base::instance() instead', DEBUG_DEVELOPER);
        return outcome_base::instance($outcomeid);
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

        return $outcomeclass::create($rule->get('id'), $configdata);
    }

    /**
     * Returns the list of users that match conditions of the given rule.
     *
     * Notice this function does not take into account user matching history and
     * matching limitation defined in rule settings. See self::process_rule for
     * usage.
     *
     * When $targetuserid specified, matching query is restricted to a single user,
     * this is used when rule processing triggered by event.
     *
     * @param rule|int $rule Rule persistent (or rule id for backward compatibility).
     * @param int|null $targetuserid user id for restricting rule matching query
     * @return \stdClass[] list of of user records where each record has 'id' attribute
     */
    public static function get_matching_users($rule, int $targetuserid = null): array {
        global $DB;

        if (is_int($rule)) {
            // Rule ID is provided, retrieve rule object.
            $rule = self::get_rule($rule, true);
        }

        if (!condition::count_records(['ruleid' => $rule->get('id')])) {
            // No conditions in this rule.
            return [];
        }

        // Find all users that match the rule, for each user we will have a "lastmatchid" - whether they were matched before.
        $sql = "SELECT DISTINCT u.id, mlastmatch.id AS lastmatchid
                  FROM {user} u";

        list($join, $where, $params) = self::get_matching_join_sql($rule->get('id'));
        list($condjoin, $condwhere, $condparams) = self::get_rule_conditions_sql($rule->get('id'));

        // Restrict query to single user if required.
        if ($targetuserid !== null) {
            $pg = db::generate_param_name();
            $condparams += [$pg => $targetuserid];
            $condwhere .= " AND u.id = :{$pg} ";
        }

        // Fetch those who are matching now (including who matched before).
        $users = $DB->get_records_sql($sql . $join . $condjoin . '  WHERE ' . $where . $condwhere,
            $params + $condparams);

        return $users;
    }

    /**
     * Check if counting users matching the rule conditions is needed.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function is_matching_users_count_needed(int $ruleid): bool {
        global $DB;
        $conditionstat = $DB->get_record_sql(
            'SELECT COUNT(c.id) AS totalconditions, SUM(c.broken) AS brokenconditions
               FROM {tool_dynamicrule_condition} c
              WHERE c.ruleid = :ruleid', ['ruleid' => $ruleid]);
        return $conditionstat->totalconditions && !$conditionstat->brokenconditions &&
            self::static_conditions_are_matching($ruleid);
    }

    /**
     * Returns the number of users now matching the conditions.
     *
     * Rule matching limitations are ignored, as this method is called when there
     * are no rule matches yet to display the number in the conditions interface.
     *
     * @param int $ruleid
     * @return int
     */
    public static function count_matching_users(int $ruleid): int {
        global $DB;

        if (!self::is_matching_users_count_needed($ruleid)) {
            return 0;
        }

        // Find all users that match this rule.
        list($join, $where, $params) = self::get_matching_join_sql($ruleid);
        list($condjoin, $condwhere, $condparams) = self::get_rule_conditions_sql($ruleid);
        $sql = ' FROM {user} u ' . $join . $condjoin .
            '  WHERE ' . $where . $condwhere . ' AND mlastmatch.id IS NULL ';
        $params = $params + $condparams;

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
     * @param int|null $targetuserid If targeting a user set this to user id to unmatch only this user
     */
    private static function update_unmatched_users(int $ruleid, array $matchedusers, int $currenttime, $targetuserid = null) {
        global $DB;
        if ($matchedusers) {
            if (count($matchedusers) == 1 && isset($matchedusers[$targetuserid])) {
                // Reaching this point is possible if rule processing was triggered
                // by event. User is still matching the rule, so nothing needs to be done.
                return;
            } else {
                // Find all users that match the rule, same what is done in {@see self::get_matching_users()}.
                // This might be not required when MDL-53735 is addredded (we can just pass a list of
                // $matchedusers within NOT IN statement.
                $sql = "SELECT DISTINCT u.id
                          FROM {user} u";
                list($join, $where, $params) = self::get_matching_join_sql($ruleid);
                list($condjoin, $condwhere, $condparams) = self::get_rule_conditions_sql($ruleid);
                // Fetch those who are matching now (including who matched before).
                // Notice we have extra select wrap to address MySQL ER_UPDATE_TABLE_USED issue.
                $unmatcheduseridsql = 'SELECT ou.id
                                         FROM (' . $sql . $join . $condjoin .
                                      ' WHERE ' . $where . $condwhere . ') ou';
                $unmatechedparams = $params + $condparams;
                $unmatcheduseridsql = " AND userid NOT IN ({$unmatcheduseridsql})";
            }
        } else {
            // Avoid unmatch everybody if we have a target user.
            if ($targetuserid !== null) {
                // This is the case when user need to be unmatched as result of event.
                // If user previously matched, this suggests event has happened
                // again and user no longer match, so unmatch user.
                $unmatechedparams = ['targetuserid' => $targetuserid];
                $unmatcheduseridsql = ' AND userid = :targetuserid';
            } else {
                // Unmatching everybody.
                $unmatcheduseridsql = '';
                $unmatechedparams = [];
            }
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
        // Go through all plugins and find out which of them got dynamicrule class instances defined.
        $componentinstances = \core_component::get_component_classes_in_namespace(null,
            '\\tool_dynamicrule\\' . $type);
        // Remove dummy from the list.
        unset($componentinstances['tool_dynamicrule\\tool_dynamicrule\\' . $type . '\\dummy']);
        foreach (array_keys($componentinstances) as $instanceclass) {
            // Create instance if this is not abstract class.
            $reflectionclass = new \ReflectionClass($instanceclass);
            if (!$reflectionclass->isAbstract()) {
                $instances[] = $instanceclass::instance();
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
            if ($instance = condition_base::instance(0, $r)) {
                $conditions[] = $instance;
            } else {
                // The condition class is missing, it is likely condition has
                // been removed from the system. Use dummy instead, so we won't
                // have it orphaned and allow user remove broken condition.
                $conditions[] = self::get_dummy_instance($r, 'condition');
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
            if ($instance = outcome_base::instance(0, $r)) {
                $outcomes[] = $instance;
            } else {
                // The outcome class is missing, it is likely outcome has
                // been removed from the system. Use dummy instead, so we won't
                // have it orphaned and allow user remove broken outcome.
                $outcomes[] = self::get_dummy_instance($r, 'outcome');
            }
        }
        return $outcomes;
    }

    /**
     * Return dummy instance for given condition or outcome record.
     *
     * Dummy instance is used for replacing condition or outcome that is
     * missing in the system, so the instance will be shown in the interface, allowing
     * user to remove broken instance.
     *
     * @param stdClass $record Condition or outcome record.
     * @param string $type Type of instance required (condition or outcome).
     * @return condition_base|outcome_base
     */
    private static function get_dummy_instance($record, $type) {
        if (!in_array($type, ['condition', 'outcome'])) {
            throw new \coding_exception('Wrong instance type specified.');
        }
        $explodedclass = explode('\\', $record->classname);
        $record->classname = '\\tool_dynamicrule\\tool_dynamicrule\\' . $type . '\\dummy';
        $baseclass = '\\tool_dynamicrule\\' . $type . '_base';
        $instance = $baseclass::instance(0, $record);
        // Store original class in configuration, it will be used to display broken description.
        $configdata = $instance->get_configdata();
        $configdata['instanceclass'] = $explodedclass[0] . ':' . $explodedclass[3];
        $instance->update_configdata($configdata, true);
        return $instance;
    }

    /**
     * Process all rules applying the outcomes to users matching the conditions
     */
    public static function process_rules() {
        $rules = rule::get_records(['enabled' => 1, 'archived' => 0], 'name');

        foreach ($rules as $rule) {
            // We don't process rules where all conditions are event-based.
            // They are processed on event (see \tool_dynamicrule\event\observer).
            $eventonly = true;
            $ruleconditions = self::get_rule_conditions($rule->get('id'));
            foreach ($ruleconditions as $condition) {
                if ($condition->get_event_subscription() === false) {
                    $eventonly = false;
                    break;
                }
            }
            if (!$eventonly) {
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
    public static function process_rule(rule $rule, int $targetuserid = null) {
        global $DB;
        if (!$rule->is_archived() && $rule->is_enabled()) {

            // Make sure we respect the rule tenant's settings.
            \tool_tenant\config::push_for_tenant($rule->get('tenantid'));

            if (self::is_rule_configuration_valid($rule)) {
                // For consistency we are using the same timestamp for all matching queries.
                $currenttime = time();

                // Preventing infinite loop (we hit same rule as result of event
                // chain initiated from this rule processing or, less likely,
                // event started at same time cron is processing the same rule for this user).
                if ($targetuserid !== null && self::is_in_progress_for_user($rule->get('id'), $targetuserid)) {
                    \tool_tenant\config::pop();
                    return;
                }

                // Static condition check, although we don't have any (WP-2203).
                if (!self::static_conditions_are_matching($rule->get('id'))) {
                    // Static conditions do not match, unmatch all users.
                    self::update_unmatched_users($rule->get('id'), [], $currenttime);
                    \tool_tenant\config::pop();
                    return;
                }

                // Conditions matching check.
                $users = self::get_matching_users($rule, $targetuserid);

                // Now mark in db everybody who no longer matches (everyone not in $users array).
                self::update_unmatched_users($rule->get('id'), $users, $currenttime, $targetuserid);

                // Remove from the list users that are already marked as matched (thus action was applied),
                // In reality this should not filter user when we have $targetuserid, which suggests this
                // is an event and user who matched should not get unlisted and excluded from processing,
                // but we can't use this aproach for now (see WP-2253).
                $users = array_filter($users, function($u) {
                    return empty($u->lastmatchid);
                });

                // If there are restrictions based on previous matches, leave only users that satisfy them.
                $users = self::filter_users_by_rule_restriction($rule, $users, $currenttime);

                // Retreive outcomes.
                $outcomes = self::get_rule_outcomes($rule->get('id'));
                array_walk($outcomes, function($outcome) {
                    // Prepare each outcome for applying.
                    $outcome->setup_for_applying();
                });

                // Apply outcomes.
                foreach ($users as $user) {
                    // Re-fetch the user (user object is incomplete).
                    $user = \core_user::get_user($user->id);
                    // Mark user as matched and outcome applying started.
                    $recordid = $DB->insert_record('tool_dynamicrule_match', [
                        'ruleid' => $rule->get('id'),
                        'userid' => $user->id,
                        'matchedtime' => time(),
                        'status' => self::STATUS_IN_PROGRESS,
                    ]);

                    $errors = [];
                    try {
                        foreach ($outcomes as $outcome) {
                            $outcome->apply_to_user($user);
                        }
                    } catch (\Exception $e) {
                        // Outcome can't be applied for some reason. Record error.
                        $errors[] = [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'class' => get_class($e),
                            'trace' => $e->getTraceAsString(),
                        ];
                    }

                    // Mark as outcome applying is completed.
                    $statusupdate = ['id' => $recordid, 'status' => self::STATUS_DONE];
                    if (count($errors)) {
                        // Record error status.
                        $statusupdate['status'] = self::STATUS_ERROR;
                        $statusupdate['errordata'] = json_encode($errors);
                    }
                    $DB->update_record('tool_dynamicrule_match', $statusupdate);
                }
            }

            // Change config back to the current tenant.
            \tool_tenant\config::pop();
        }
    }

    /**
     * Check if there is ongoing rule processing for the given user.
     *
     * @param int $ruleid
     * @param int $targetuserid
     * @return bool
     */
    private static function is_in_progress_for_user(int $ruleid, int $targetuserid): bool {
        global $DB;
        $sql = "SELECT COUNT(1) FROM {tool_dynamicrule_match}
                               WHERE status = :status
                                 AND ruleid = :ruleid
                                 AND userid = :userid";
        $params = [
            'status' => self::STATUS_IN_PROGRESS,
            'ruleid' => $ruleid,
            'userid' => $targetuserid,
        ];
        return (bool) $DB->count_records_sql($sql, $params);
    }

    /**
     * Return true if the static conditions are all matching, false otherwise
     *
     * @param int $ruleid
     * @return bool
     */
    public static function static_conditions_are_matching(int $ruleid): bool {
        $conditions = self::get_rule_conditions($ruleid);
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
     * @param rule $rule
     * @param int|null $currenttime
     * @return array [$join, $groupbyhaving, $params]
     */
    public static function get_rule_restriction_sql(rule $rule, int $currenttime = null): array {
        if (!$rule->get('matchlimit')) {
            return ['', '', []];
        }

        $currenttime = $currenttime ?? time();

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
            $pruleid => $rule->get('id'),
            $ptimelimit => ($currenttime - $rule->get('matchinterval')),
            $pcountlimit => $rule->get('matchlimit')
        ];
        return [$join, $groupbyhaving, $params];
    }

    /**
     * Filter list of matched users only to those who satisfy rule matching limits.
     *
     * For example, if there is a restriction that rule can not be triggered more than
     * once per hour and it has been applied to some users during the last hour,
     * these users will not be returned.
     *
     * @param rule $rule
     * @param \stdClass[] $matcheduserids list of users who match the conditions, each record record has 'id' property
     * @param int $currenttime
     * @return \stdClass[] list of user records where each record has 'id' property
     */
    private static function filter_users_by_rule_restriction(rule $rule, array $matchedusers, int $currenttime = null): array {
        global $DB;

        if (!$matchedusers) {
            return [];
        }
        list($join, $groupbyhaving, $params) = self::get_rule_restriction_sql($rule, $currenttime);
        if (empty($join) && empty($groupbyhaving)) {
            return $matchedusers;
        }

        // We split processing into chunks not to upset DB engine with IN params number over limit.
        // Beware, we invalidate keys at this point (we don't need them any more).
        $userschunks = array_chunk($matchedusers, self::FILTER_USERS_CHUNK_SIZE);
        $matchedusers = [];
        foreach ($userschunks as $chunk) {
            list($matchsql, $matchparams) = $DB->get_in_or_equal(array_column($chunk, 'id'), SQL_PARAMS_NAMED,
                self::generate_param_name() . 'user');

            $sql = 'SELECT u.id
                      FROM {user} u '. $join . '
                     WHERE u.id ' . $matchsql . $groupbyhaving;
            $chunk = $DB->get_records_sql($sql, $params + $matchparams);
            $matchedusers = array_merge($matchedusers, $chunk);
        }
        return $matchedusers;
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
                $where .= ' AND (' . $newwhere . ')';
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
     * Check every rule condition and see if it's configuration settings are still valid. If valid mark it as not broken.
     *
     * @param rule $rule
     * @return bool
     */
    public static function validate_conditions_configuration(rule $rule): bool {
        $valid = true;
        foreach (self::get_rule_conditions($rule->get('id')) as $condition) {
            $configisvalid = self::is_condition_configuration_valid($condition);
            if (!$configisvalid) {
                $condition->mark_as_broken();
                $valid = false;
            } else if ($configisvalid && $condition->is_broken()) {
                $condition->mark_as_not_broken();
            }
        }
        return $valid;
    }

    /**
     * Check every rule condition and see if it's configuration settings are still valid.
     * @deprecated in WP-1583
     *
     * @param rule $rule
     * @return bool
     */
    private static function is_conditions_configuration_valid(rule $rule): bool {
        debugging('Function is_conditions_configuration_valid() should not be used', DEBUG_DEVELOPER);
    }

    /**
     * Check every rule outcome and see if it's configuration settings are still valid. If valid mark it as not broken.
     *
     * @param rule $rule
     * @return bool
     */
    public static function validate_outcomes_configuration(rule $rule): bool {
        $valid = true;
        foreach (self::get_rule_outcomes($rule->get('id')) as $outcome) {
            $configisvalid = self::is_outcome_configuration_valid($outcome);
            if (!$configisvalid) {
                $outcome->mark_as_broken();
                $valid = false;
            } else if ($configisvalid && $outcome->is_broken()) {
                $outcome->mark_as_not_broken();
            }
        }
        return $valid;
    }

    /**
     * Check every rule outcome and see if it's configuration settings are still valid.
     * @deprecated in WP-1583
     *
     * @param rule $rule
     * @return bool
     */
    private static function is_outcomes_configuration_valid(rule $rule): bool {
        debugging('Function is_outcomes_configuration_valid() should not be used', DEBUG_DEVELOPER);
    }

    /**
     * Check if condition configuration is valid.
     *
     * This wrapper methods ensures that configuration is not empty.
     *
     * @param condition_base $condition
     * @return bool
     */
    public static function is_condition_configuration_valid(condition_base $condition): bool {
        return !empty($condition->get_configdata()) && $condition->is_configuration_valid();
    }

    /**
     * Check if outcome configuration is valid.
     *
     * This wrapper methods ensures that configuration is not empty.
     *
     * @param outcome_base $outcome
     * @return bool
     */
    public static function is_outcome_configuration_valid(outcome_base $outcome): bool {
        return !empty($outcome->get_configdata()) && $outcome->is_configuration_valid();
    }

    /**
     * Validate rule configuration, returning false means rule is
     * broken and can't be used.
     *
     * @param rule $rule
     * @return bool
     */
    public static function is_rule_configuration_valid(rule $rule): bool {
        // Retrieve active tenants.
        $tenants = \tool_tenant\tenancy::get_tenants();
        $tenantvalid = array_key_exists($rule->get('tenantid'), $tenants);

        // Check conditions and outcomes.
        $conditionsvalid = self::validate_conditions_configuration($rule);
        $outcomesvalid = self::validate_outcomes_configuration($rule);

        return $tenantvalid && $conditionsvalid && $outcomesvalid;
    }

    /**
     * Mark rule as broken
     * @deprecated in WP-1583
     *
     * @param int $ruleid
     * @return bool
     */
    public static function mark_rule_as_broken($ruleid) {
        debugging('Function mark_rule_as_broken() should not be used', DEBUG_DEVELOPER);
    }

    /**
     * Mark rule as not broken
     * @deprecated in WP-1583
     *
     * @param int $ruleid
     */
    public static function mark_rule_as_not_broken($ruleid) {
        debugging('Function mark_rule_as_not_broken() should not be used', DEBUG_DEVELOPER);
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
            $attributes = ['data-action' => 'editcontent', 'data-id' => $rule->get('id')];
            $displayname = \html_writer::link($editurl, $formattedname, $attributes);
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
     * TODO: Review if it needs to be in DR API WP-2526
     *
     * @param string $search
     * @return array
     */
    public static function get_potential_badges(string $search): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/badgeslib.php');

        $context = \context_system::instance();
        if (!has_capability('moodle/badges:awardbadge', $context)) {
             throw new \required_capability_exception($context, 'moodle/badges:awardbadge', 'nopermissions', '');
        }

        // We select badges of type site, active and with manual issue criteria.
        $query = 'SELECT b.*
                  FROM {badge} b
                  JOIN {badge_criteria} bc
                  ON b.id = bc.badgeid
                  WHERE (b.status = :badgestatusactive OR b.status = :badgestatusactivelocked)
                  AND b.type = :badgetype AND bc.criteriatype = :badgecriteriatype';

        $params = [
            'badgestatusactive' => BADGE_STATUS_ACTIVE,
            'badgestatusactivelocked' => BADGE_STATUS_ACTIVE_LOCKED,
            'badgetype' => BADGE_TYPE_SITE,
            'badgecriteriatype' => BADGE_CRITERIA_TYPE_MANUAL,
        ];
        $i = 0;
        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('name', ":search{$i}1", false, false) . ')';
            $params += ["search{$i}1" => '%' . $DB->sql_like_escape($word) . '%'];
        }

        $result = $DB->get_records_sql($query, $params);

        // We apply format string to the name.
        if (!empty($result)) {
            foreach ($result as $res) {
                $res->name = format_string($res->name, true, ['context' => $context, 'escape' => false]);
            }
        }

        return $result;
    }

    /**
     * Cohorts selector.
     *
     * TODO: Review if it needs to be in DR API WP-2526
     *
     * @param string $search
     * @param string $instancetype
     * @return array
     */
    public static function get_potential_cohorts(string $search, string $instancetype): array {
        global $CFG;
        require_once($CFG->dirroot.'/cohort/lib.php');

        $capability = ($instancetype === 'outcome') ? 'moodle/cohort:assign' : 'moodle/cohort:view';

        // Put together system and category contexts user can access.
        $categories = \core_course_category::make_categories_list($capability);
        $contextids = [];
        foreach (array_keys($categories) as $categoryid) {
            $contextids[\context_coursecat::instance($categoryid)->id] = '';
        }
        if (has_capability($capability, \context_system::instance())) {
            $contextids[SYSCONTEXTID] = '';
        }

        if (count($contextids)) {
            // Search cohorts user can view.
            $cohorts = cohort_get_all_cohorts(0, 0, $search);

            // Remove cohorts user can't access.
            $cohorts = array_filter($cohorts['cohorts'], function($cohort) use ($contextids) {
                return array_key_exists($cohort->contextid, $contextids);
            });

            foreach ($cohorts as $cohort) {
                $cohort->name = format_string($cohort->name, true, ['context' => $cohort->contextid, 'escape' => false]);
            }
            return $cohorts;
        }
        return [];
    }

    /**
     * Competencies selector.
     *
     * TODO: Review if it needs to be in DR API WP-2526
     *
     * @param string $search
     * @return array
     */
    public static function get_potential_competencies(string $search): array {
        global $DB;

        // Check listing capability first.
        // For now this works for system context only.
        // TODO WP-1616.
        $capabilities = ['moodle/competency:competencyview', 'moodle/competency:competencymanage'];
        $context = \context_system::instance();
        if (!has_any_capability($capabilities, $context)) {
             throw new \required_capability_exception($context, 'moodle/competency:competencyview', 'nopermissions', '');
        }

        $query = "SELECT c.*
                    FROM {competency} c
                    JOIN {competency_framework} cf ON (c.competencyframeworkid = cf.id)
                   WHERE cf.contextid = :contextid";
        $params = ['contextid' => $context->id];
        $i = 0;
        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('c.shortname', ":search{$i}1", false, false) . ')';
            $params += ["search{$i}1" => '%' . $DB->sql_like_escape($word) . '%'];
        }

        $result = $DB->get_records_sql($query, $params);

        // We apply format string to the shortname.
        if (!empty($result)) {
            foreach ($result as $res) {
                $res->shortname = format_string($res->shortname, true, ['context' => $context, 'escape' => false]);
            }
        }

        return $result;
    }

    /**
     * Returns number of already matched users for this rule.
     *
     * @param int $ruleid
     * @return int
     * @throws \dml_exception
     */
    public static function count_matched_users(int $ruleid): int {
        global $DB;
        return $DB->count_records('tool_dynamicrule_match', ['ruleid' => $ruleid]);
    }

    /**
     * Check if the rule is scheduled task.
     *
     * If at least one of the rule conditions is not event-based, then the rule is scheduled task.
     *
     * @param int $ruleid
     * @return bool
     */
    public static function is_rule_scheduled_task(int $ruleid) : bool {
        foreach (self::get_rule_conditions($ruleid) as $condition) {
            if (!$condition->get_event_subscription() && !$condition->is_broken()) {
                return true;
            }
        }
        return false;
    }
}
