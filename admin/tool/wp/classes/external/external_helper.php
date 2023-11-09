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

namespace tool_wp\external;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Helper functions to use in the WS functions (parameters and lookups)
 *
 * @package    tool_wp
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_helper {
    /** @var array */
    protected array $previouslookups = [];

    /**
     * Helper function to describe the user parameter in web services
     *
     * @param string|null $description Description for the WS parameter, by default 'User'
     * @param int $required Required flag for the WS parameter, by default VALUE_REQUIRED
     * @param array|null $default if the flag is VALUE_DEFAULT, the default value for the WS parameter
     * @return external_single_structure
     */
    public static function user_lookup_structure(
            ?string $description = null,
            int $required = VALUE_REQUIRED,
            ?array $default = null): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(\core_user::get_property_type('id'),
                'Internal Moodle ID of the user', VALUE_OPTIONAL),
            'username' => new external_value(\core_user::get_property_type('username'),
                'Username', VALUE_OPTIONAL),
            'email' => new external_value(\core_user::get_property_type('email'),
                'An email address (only if it is unique)', VALUE_OPTIONAL),
        ], $description ?? 'User', $required, $default);
    }

    /**
     * Helper function to descript multiple users parameter in web services
     *
     * @param string|null $description Description for the WS parameter, by default 'List of users'
     * @param int $required Required flag for the WS parameter, by default VALUE_REQUIRED
     * @param array|null $default if the flag is VALUE_DEFAULT, the default value for the WS parameter
     * @return external_multiple_structure
     */
    public static function users_lookup_structure(
            ?string $description = null,
            int $required = VALUE_REQUIRED,
            ?array $default = null): external_multiple_structure {
        return new external_multiple_structure(
            self::user_lookup_structure(),
            $description ?? 'List of users',
            $required,
            $default
        );
    }

    /**
     * Store the found item so we can retrieve it from cache next time it is requested
     *
     * @param string $itemname
     * @param string $lookupkey
     * @param mixed $data
     * @return void
     */
    protected function remember_lookup(string $itemname, string $lookupkey, $data): void {
        if (!array_key_exists($itemname, $this->previouslookups)) {
            $this->previouslookups[$itemname] = [];
        }
        $this->previouslookups[$itemname][$lookupkey] = $data;
    }

    /**
     * Records an exception looking up for an item and also throws it
     *
     * @param string $itemname
     * @param string $lookupkey
     * @param string $code
     * @param string $message
     * @return void
     * @throws external_helper_exception
     */
    protected function lookup_exception(string $itemname, string $lookupkey,
            string $code, string $message): void {
        $exception = new external_helper_exception($itemname, $code, $message);
        $this->remember_lookup($itemname, $lookupkey, $exception);
        throw $exception;
    }

    /**
     * Check if this item was already requested and return it (or throw an exception if previous lookup had it)
     *
     * @param string $itemname
     * @param string $lookupkey
     * @return mixed
     * @throws external_helper_exception
     */
    protected function recover_lookup(string $itemname, string $lookupkey) {
        $data = $this->previouslookups[$itemname][$lookupkey] ?? null;
        if ($data && ($data instanceof external_helper_exception)) {
            throw $data;
        }
        return $data;
    }

    /**
     * Builds SQL to use in WHERE clause to filter users that belong to the specific tenant
     *
     * This is a wrapper for the tenant function because technically tool_wp does not depend on tool_tenancy
     *
     * @uses \tool_tenant\tenancy::get_users_subquery()
     *
     * @param bool $canseeall
     * @param bool $andpostfix
     * @param string $useridfield
     * @param int $tenantid
     * @param bool $withsubtenants
     * @return string
     */
    protected static function get_users_subquery(bool $canseeall = true, bool $andpostfix = true,
            string $useridfield = 'u.id', int $tenantid = 0, bool $withsubtenants = true): string {
        $default = $andpostfix ? ' 1=1 AND ' : ' ';
        return component_class_callback(\tool_tenant\tenancy::class, 'get_users_subquery',
            [$canseeall, $andpostfix, $useridfield, $tenantid, $withsubtenants], $default);
    }

    /**
     * Returns if user should not be visible to the current user at all because of multitenancy
     *
     * This is a wrapper for the tenant function because technically tool_wp does not depend on tool_tenancy
     *
     * @uses \tool_tenant\tenancy::is_user_hidden_by_tenancy()
     *
     * @param int|\stdClass $user
     * @param mixed $currentuserid
     * @return bool
     */
    protected static function is_user_hidden_by_tenancy($user, $currentuserid = null): bool {
        return component_class_callback(\tool_tenant\tenancy::class, 'is_user_hidden_by_tenancy',
            [$user, $currentuserid], false);
    }

    /**
     * Given data in the format of 'user' web service parameter, finds and returns a user
     *
     * Also see {@see self::user_lookup_structure()}
     *
     * @param array $userdata
     * @param string $itemname
     * @param string $fields fields from DB table 'user' to return (avoid using '*' as it can cause performance issues)
     * @return \stdClass user object
     */
    public function lookup_user(array $userdata, string $itemname = 'user',
            string $fields = 'id, username, email'): \stdClass {
        global $DB, $CFG;
        $lookupkey = json_encode($userdata);
        if ($user = $this->recover_lookup($itemname, $lookupkey)) {
            return $user;
        }

        $query = 'deleted = 0';
        $params = ['mnethostid' => $CFG->mnet_localhost_id];
        if (!empty($userdata['id'])) {
            $query .= ' AND id = :id AND mnethostid = :mnethostid';
            $params['id'] = $userdata['id'];
        } else if (strlen($userdata['username'] ?? '')) {
            $query .= ' AND username = :username';
            $params['username'] = $userdata['username'];
        } else if (!empty($userdata['email'])) {
            $tenantquery = self::get_users_subquery(true, true, 'id');
            $query = "$tenantquery $query AND email = :email AND mnethostid = :mnethostid";
            $params['email'] = $userdata['email'];
        } else {
            $this->lookup_exception($itemname, $lookupkey, 'invalidparameters',
                'User must be identified either by id or username or email');
        }
        $users = $DB->get_records_select('user', $query, $params, '', $fields);
        if (count($users) > 1) {
            $this->lookup_exception($itemname, $lookupkey, 'notunique',
                'Found more than one user');
        }
        $user = reset($users);
        if (!$user || self::is_user_hidden_by_tenancy($user)) {
            $this->lookup_exception($itemname, $lookupkey, 'notfound', 'User not found');
        }
        $this->remember_lookup($itemname, $lookupkey, $user);
        return $user;
    }
}
