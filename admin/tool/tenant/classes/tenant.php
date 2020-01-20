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
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

use core\output\inplace_editable;

/**
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant extends \core\persistent implements \cacheable_object {

    /** The table name. */
    const TABLE = 'tool_tenant';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'name' => array(
                'type' => PARAM_TEXT,
                'description' => 'The tenant name.',
            ),
            'sitename' => array(
                'type' => PARAM_TEXT,
                'description' => 'The tenant site name.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'siteshortname' => array(
                'type' => PARAM_TEXT,
                'description' => 'The tenant site short name.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'idnumber' => array(
                'type' => PARAM_RAW,
                'description' => 'An id number used for external services.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'archived' => array(
                'type' => PARAM_INT,
                'description' => 'Is archived.',
                'default' => 0,
            ),
            'timearchived' => array(
                'type' => PARAM_INT,
                'description' => 'Time the tenant was archived.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'isdefault' => array(
                'type' => PARAM_INT,
                'description' => 'Is default tenant',
                'default' => 0,
            ),
            'sortorder' => array(
                'type' => PARAM_INT,
                'description' => 'Sort order',
                'default' => 0,
            ),
            'categoryid' => array(
                'type' => PARAM_INT,
                'description' => 'Category ID this tenant is linked to',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'cssconfig' => array(
                'type' => PARAM_RAW,
                'description' => 'The CSS config for this tenant.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'useloginurlid' => array(
                'type' => PARAM_BOOL,
                'description' => 'Use login URL id',
                'default' => 1,
            ),
            'useloginurlidnumber' => array(
                'type' => PARAM_BOOL,
                'description' => 'Use login URL idnumber',
                'default' => 1,
            ),
        );
    }

    /**
     * Tenant name ready for display
     * @return string
     */
    public function get_formatted_name() : string {
        return format_string($this->get('name'), true,
            ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Inplace editable object for the name
     * @return inplace_editable
     */
    public function get_editable_name() : inplace_editable {
        $displayname = $this->get_formatted_name();
        if (permission::can_view_tenant_details($this->get('id'))) {
            $displayname = \html_writer::link(manager::get_edit_tenant_url($this->get('id')), $displayname);
        }
        return new inplace_editable(
            'tool_tenant',
            'tenant_name',
            $this->get('id'),
            // TODO permissions callback.
            !$this->get('archived') && has_capability('tool/tenant:manage', \context_system::instance()),
            $displayname,
            $this->get('name'),
            get_string('edittenantname', 'tool_tenant'),
            get_string('newnamefor', 'tool_tenant', $this->get_formatted_name())
        );
    }

    /**
     * Return number of users in this tenant
     * @return int
     */
    public function get_users_count() {
        // TODO convert to additional property or something like that.
        global $DB;
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $this->get('id'));
        return $DB->count_records_sql("SELECT COUNT(1) FROM {user} u {$join} WHERE {$where}", $params);
    }

    /**
     * Get the category associcated with this tenant.
     *
     * @return \core_course_category A course category object
     */
    public function get_category() : ?\core_course_category {
        return !$this->get('categoryid') ? null :
            \core_course_category::get($this->get('categoryid'), IGNORE_MISSING, true);
    }

    /**
     * All login URLs that are visible/avilable for this tenant
     *
     * @param bool $withcopylinks include "Copy to clipboard" link
     * @return array array of strings
     */
    public function get_login_urls(bool $withcopylinks = true) {
        global $CFG, $OUTPUT;
        $urls = [];
        if (($id = $this->get('id')) && $this->get('useloginurlid')) {
            $url = $CFG->wwwroot.'/?tenantid='.$id;
            if ($withcopylinks) {
                $url .= $OUTPUT->render_from_template('tool_wp/copy_to_clipboard', ['text' => $url]);
            }
            $urls[] = $url;
        }
        if (($idnumber = $this->get('idnumber')) && $this->get('useloginurlidnumber')) {
            $url = $CFG->wwwroot.'/?tenant='.urlencode($idnumber);
            if ($withcopylinks) {
                $url .= $OUTPUT->render_from_template('tool_wp/copy_to_clipboard', ['text' => $url]);
            }
            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * Prepares the object for caching. Works like the __sleep method.
     *
     * @return mixed The data to cache, can be anything except a class that implements the cacheable_object... that would
     *      be dumb.
     */
    public function prepare_to_cache() {
        return $this->to_record();
    }

    /**
     * Takes the data provided by prepare_to_cache and reinitialises an instance of the associated from it.
     *
     * @param mixed $data
     * @return object The instance for the given data.
     */
    public static function wake_from_cache($data) {
        return new self(0, $data);
    }
}

