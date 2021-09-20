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
 * Class user_with_jobs
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\output;

use core\external\exporter;
use core_user\output\myprofile\category;
use core_user\output\myprofile\manager;
use core_user\output\myprofile\renderer;
use context_system;
use moodle_url;
use renderer_base;
use stdClass;
use tool_organisation\helper;

/**
 * Class user_with_jobs
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_with_jobs extends exporter {

    /** @var array $managedusersids */
    protected $managedusersids = [];

    /**
     * Returns a list of objects that are related to this persistent.
     *
     * Only objects listed here can be cached in this object.
     *
     * The class name can be suffixed:
     * - with [] to indicate an array of values.
     * - with ? to indicate that 'null' is allowed.
     *
     * @return array of 'propertyname' => array('type' => classname, 'required' => true)
     */
    public static function define_related(): array {
        return [
            'jobs' => job::class . '[]?',
            'time' => 'int?',
            'fulluserrecord' => 'stdClass?',
        ];
    }

    /**
     * Returns the list of jobs this user is holding that can be additionally filtered
     *
     * @param bool $manageronly only return manager jobs
     * @param int $withpermissions only return jobs with permissions
     * @return job[]
     */
    public function get_jobs(bool $manageronly = false, int $withpermissions = 0): array {
        if (!isset($this->related['jobs'])) {
            debugging('Jobs were not set when instance was created', DEBUG_DEVELOPER);
            return [];
        }
        $jobs = $this->related['jobs'];
        if ($manageronly) {
            $jobs = array_filter($jobs, function(job $job) use ($withpermissions) {
                return $job->get_position()->is_manager($withpermissions);
            });
        }
        return $jobs;
    }

    /**
     * Checks if user has a manager job
     *
     * @param int $withpermissions
     * @return bool
     */
    public function is_manager(int $withpermissions = 0): bool {
        $jobs = $this->get_jobs(true, $withpermissions);
        return !empty($jobs);
    }

    /**
     * Checks if this user is a manager over another user
     *
     * @param int $userid
     * @param int $withpermissions
     * @return bool
     */
    public function is_manager_over_user(int $userid, int $withpermissions = 0) : bool {
        global $DB;
        if (!array_key_exists($withpermissions, $this->managedusersids)) {
            if (!$this->is_manager($withpermissions)) {
                $this->managedusersids[$withpermissions] = [];
            } else {
                list($where, $params) = $this->get_managed_users_select('u', $withpermissions);
                $this->managedusersids[$withpermissions] =
                    $DB->get_fieldset_sql('SELECT u.id FROM {user} u WHERE ' . $where, $params);
            }
        }
        return in_array($userid, $this->managedusersids[$withpermissions]);
    }

    /**
     * Does this user have a job that is a department manager
     *
     * @return bool
     */
    public function is_department_manager(): bool {
        $jobs = $this->related['jobs'];
        $jobs = array_filter($jobs, function(job $job) {
            return $job->get_position()->is_department_manager();
        });
        return !empty($jobs);
    }

    /**
     * Does this user have a job that is a global manager
     *
     * @return bool
     */
    public function is_global_manager(): bool {
        $jobs = $this->related['jobs'];
        $jobs = array_filter($jobs, function(job $job) {
            return $job->get_position()->is_global_manager();
        });
        return !empty($jobs);
    }

    /**
     * Gets user property
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key) {
        return $this->data->$key;
    }

    /**
     * Retrieve full user record
     *
     * @return stdClass
     */
    public function get_full_user_record(): stdClass {
        global $DB, $USER;
        if (empty($this->related['fulluserrecord'])) {
            $this->related['fulluserrecord'] = ($USER->id == $this->data->id) ? $USER :
                $DB->get_record('user', ['id' => $this->data->id]);
        }
        return $this->related['fulluserrecord'];
    }

    /**
     * Set full user record
     *
     * @param stdClass $data
     */
    public function set_full_user_record(stdClass $data): void {
        $this->related['fulluserrecord'] = $data;
    }

    /**
     * Returns the list of this user's jobs that make him a subordinate of a $manager user
     *
     * For example, if a user has another job in another position/department that is not managed
     * by the $manager, this job would not be returned here
     *
     * @param user_with_jobs $manager
     * @return job[]
     */
    public function get_relevant_jobs(user_with_jobs $manager): array {
        $managerjobs = $manager->get_jobs(true);
        return array_filter($this->get_jobs(), function(job $job) use ($managerjobs) {
            foreach ($managerjobs as $managerjob) {
                if ($job->is_job_relevant($managerjob)) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Returns the list of this user's jobs that make him a manager of a $subordinate user
     *
     * For example, if a manager has another job in another position/department that is not a manager
     * job or is not relevant to the $user, this job would not be returned here
     *
     * @param user_with_jobs $subordinate
     * @return job[]
     */
    public function get_relevant_manager_jobs(user_with_jobs $subordinate): array {
        $subordinatejobs = $subordinate->get_jobs();
        return array_filter($this->get_jobs(true), function(job $managerjob) use ($subordinatejobs) {
            foreach ($subordinatejobs as $job) {
                if ($job->is_job_relevant($managerjob)) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Get the properties definition of this exporter used for create, and update structures.
     * The read structures are returned by: {@see self::read_properties_definition()}.
     *
     * @return array Keys are the property names, and value their definition.
     */
    public static function define_properties(): array {
        return [
            'id' => [
                'type' => \core_user::get_property_type('id'),
            ],
        ];
    }

    /**
     * Other properties definition
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'userfullname' => [
                'type' => \core_user::get_property_type('firstname')
            ],
            'jobs' => [
                'type' => job::read_properties_definition(),
                'multiple' => true
            ],
            'isglobalmanager' => ['type' => PARAM_BOOL],
            'isdepartmentmanager' => ['type' => PARAM_BOOL],
            'canmessage' => ['type' => PARAM_BOOL],
            'profileurl' => ['type' => PARAM_URL],
            'userpicture' => ['type' => PARAM_RAW],
            'attentiontext' => ['type' => PARAM_RAW],
            'userdetails' => ['type' => PARAM_RAW],
            'learning' => ['type' => PARAM_RAW],
        ];
    }

    /**
     * Export user profile category
     *
     * @param renderer $output
     * @param category $category
     * @return array
     */
    protected function export_profile_tree_category($output, category $category): array {
        $rv = [
            'classes' => $category->classes,
            'nodes' => []
        ];
        foreach ($category->nodes as $node) {
            $rv['nodes'][] = $output->render($node);
        }
        $rv['nodescount'] = count($rv['nodes']);
        return $rv;
    }

    /**
     * Values for other properties
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        global $USER, $PAGE, $CFG;
        $jobs = [];
        foreach ($this->get_jobs() as $job) {
            $jobs[] = $job->export($output);
        }

        $user = $this->get_full_user_record();
        $profileurl = '';
        if (user_can_view_profile($user)) {
            $profileurl = (new moodle_url('/user/profile.php', ['id' => $this->data->id]))->out(false);
        }
        $canmessage = (!empty($CFG->messaging) && \core_message\api::can_send_message($user->id, $USER->id));

        $tree = manager::build_tree($user, $this->data->id == $USER->id);

        $categories = $tree->categories;
        /** @var renderer $useroutput */
        $useroutput = $PAGE->get_renderer('core_user', 'myprofile');

        $rv = [
            'userfullname' => fullname($this->data, has_capability('moodle/site:viewfullnames', context_system::instance())),
            'jobs' => $jobs,
            'isdepartmentmanager' => $this->is_department_manager(),
            'isglobalmanager' => $this->is_global_manager(),
            'canmessage' => $canmessage,
            'profileurl' => $profileurl,
            'userpicture' => '',
            'attentiontext' => $this->extract_warning_information($categories),
            'userdetails' => '',
            'learning' => '',
        ];

        $rv['userpicture'] = $output->user_picture($user, ['class' => 'rounded-circle', 'link' => false]);

        if (array_key_exists('contact', $categories)) {
            $rv['userdetails'] = $this->export_profile_tree_category($useroutput, $categories['contact']);
        }

        if (array_key_exists('learning', $categories)) {
            $rv['learning'] = $this->export_profile_tree_category($useroutput, $categories['learning']);
        }
        return $rv;
    }

    /**
     * Helps to create SQL to retrieve users managed by the current manager
     *
     * Example:
     * list($where, $params) = $userwithjobs->get_managed_users_select();
     * $DB->get_records_sql("SELECT * FROM {user} u WHERE $where", $params);
     *
     * @param string $usertablealias
     * @param int $withpermissions
     * @param int $time
     * @return array [$where, $params]
     */
    public function get_managed_users_select($usertablealias = 'u', int $withpermissions = 0, int $time = 0): array {
        return helper::get_managed_users_select($this, $usertablealias, $withpermissions, $time);
    }

    /**
     * Extract warning information from the user profile navigation tree
     *
     * @param category[] $categories
     * @return string
     */
    protected function extract_warning_information(array $categories): string {
        $warnings = [];

        // Extract overdue/expired programs and certifications.
        $targetnodes = ['overduecertifications', 'expiredcertifications', 'overdueprograms'];
        foreach ($targetnodes as $node) {
            if (!empty($categories['learning'])
                && !empty($categories['learning']->__get('nodes')[$node])
                && !empty($categories['learning']->__get('nodes')[$node]->__get('content'))) {
                $warningtext = $categories['learning']->nodes[$node]->content;
                $warnings[] = strip_links($warningtext);
            }
        }

        return implode('&#013;', $warnings);
    }
}
