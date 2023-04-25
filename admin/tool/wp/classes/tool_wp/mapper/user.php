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

namespace tool_wp\tool_wp\mapper;

use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;
use tool_wp\local\exportimport\forms\import_conflict_form;

/**
 * Allows to map users during export and search for existing users during import
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user extends export_import_mapper_base {

    /**
     * Initialises mapper and registers all potential notices
     */
    protected function initialise(): void {
        $this->register_potential_error(self::NOTFOUND, [
            self::ERROR_CONFLICTHEADER => 'Some users do not exist', // TODO string.
            self::ERROR_LOG => function(array $identifier) {
                return get_string('mappingerrorusernotfound', 'tool_wp', $this->get_identifier_for_display($identifier, true));
            },
            self::ERROR_IDENTIFIER => static function(array $identifier, bool $usequotes = false) {
                return self::display_identifier_idnumber_name($identifier, 'email', 'username', $usequotes);
            },
        ]);

        $this->register_potential_notice('emailmapping', [
            self::NOTICE_LOG => function(array $identifier, array $additionaldata) {
                $a = (object)[
                    'username' => s($identifier['username']),
                    'email' => s($identifier['email']),
                    'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $additionaldata['userid']]))->out()
                ];
                return get_string('mappingnoticeuseremail', 'tool_wp', $a);
            }
        ]);
    }

    /**
     * Returns the array of properties of an entity that can be used to find this entity during import
     *
     * This function is used when the entity itself is not included in the export
     *
     * @param int $id
     * @return array|null
     */
    public function get_mapping_data_for_workplace_export(int $id): ?array {
        global $DB;
        $obj = $DB->get_record('user', ['id' => $id, 'deleted' => 0], 'id, username, email');
        $obj->tenantid = tenancy::get_tenant_id($id);
        return $obj ? (array)$obj : null;
    }

    /**
     * Allows to locate the existing entity that is available to the current user by default identifier
     *
     * @param string $identifier the default identifier used by the entity (normally shortname/idnumber/name)
     * @param int $tenantid strictly inside the given tenant (for entities that can be inside tenants)
     * @return int|null the id of the entity or null if not found or not available
     */
    public function locate_mapping_default(string $identifier, ?int $tenantid = null): ?int {
        if ($userid = $this->locate_mapping(['username' => $identifier])) {
            return $userid;
        }
        if (validate_email($identifier)) {
            return $this->locate_mapping(['email' => $identifier]);
        }
        return null;
    }

    /**
     * Allows to locate the existing entity that is available to the current user
     *
     * @param array $identifier array or known entity's attributes, for example:
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID', 'tenantid' => 1]
     * @return int|null
     */
    public function locate_mapping(array $identifier): ?int {
        global $DB, $CFG;
        $sql = '';
        if (!\tool_tenant\permission::can_view_users_in_all_tenants()) {
            $tenantid = tenancy::get_tenant_id();
        } else if (!empty($identifier['tenantid'])) {
            $tenantid = $this->get_mapping('tool_tenant', $identifier['tenantid'], IGNORE_MISSING) ?:
                tenancy::get_tenant_id();
        }

        // If we have a valid tenant ID then we can filter users from it.
        if (!empty($tenantid) && $tenantid > 0) {
            $sql = tenancy::get_users_subquery(false, true, 'id', $tenantid);
        }
        if (!empty($identifier['username'])) {
            $userid = $DB->get_field_select('user', 'id',
                $sql . $DB->sql_equal('username', ':username', true, true) .
                ' AND mnethostid = :mnethostid AND deleted = :deleted',
                ['username' => strtolower($identifier['username']), 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
            if ($userid) {
                return $userid;
            }
        }
        if (!empty($identifier['email'])) {
            // Lookup by username failed, try looking up by email. If found log a notice.
            // If there are several users with this email this mapping will fail (return null).

            // The case-insensitive + accent-sensitive search may be expensive as some DBs such as MySQL cannot use the
            // index in that case. For that reason, we first perform accent-insensitive search in a subselect for potential
            // candidates (which can use the index) and only then perform the additional accent-sensitive search on this
            // limited set of records in the outer select. See MDL-68183.
            $sql .= $DB->sql_equal('email', ':email1', false, true) .
                   " AND id IN (SELECT id
                                FROM {user}
                               WHERE mnethostid = :mnethostid
                                 AND deleted = 0
                                 AND " . $DB->sql_equal('email', ':email2', false, false) . ")";
            $params = array(
                'email1' => $identifier['email'],
                'email2' => $identifier['email'],
                'mnethostid' => $CFG->mnet_localhost_id,
            );

            $userids = $DB->get_fieldset_select('user', 'id', $sql, $params);
            if (count($userids) == 1) {
                $userid = reset($userids);
                if (!empty($identifier['username'])) {
                    $this->log_mapping_notice('emailmapping', $identifier, ['userid' => $userid]);
                }
                return $userid;
            }
        }
        return null;
    }

    /**
     * Add options to conflict form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param array $importedentities
     * @param array $identifiers
     * @param bool $addskipaction
     */
    public function add_to_conflict_form(import_conflict_form $form, array $importedentities,
                                         array $identifiers, bool $addskipaction = true): void {
        parent::add_to_conflict_form($form, $importedentities, $identifiers, true);
        // Currently we don't provide any conflict resolution actions - if user is not found, he is not found and
        // we always skip import.
    }
}
