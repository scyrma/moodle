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
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use core\output\inplace_editable;
use tool_wp\db;

/**
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
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
        return [
            'name' => [
                'type' => PARAM_TEXT,
                'description' => 'The tenant name.',
            ],
            'parentid' => [
                'type' => PARAM_INT,
                'description' => 'Id of the parent tenant',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'depth' => [
                'type' => PARAM_INT,
                'description' => 'Level in hierarchy',
                'default' => 1,
            ],
            'path' => [
                'type' => PARAM_PATH,
                'description' => 'Path',
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'sitename' => [
                'type' => PARAM_TEXT,
                'description' => 'The tenant site name.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'siteshortname' => [
                'type' => PARAM_TEXT,
                'description' => 'The tenant site short name.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'idnumber' => [
                'type' => PARAM_RAW,
                'description' => 'An id number used for external services.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'archived' => [
                'type' => PARAM_INT,
                'description' => 'Is archived.',
                'default' => 0,
            ],
            'timearchived' => [
                'type' => PARAM_INT,
                'description' => 'Time the tenant was archived.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'isdefault' => [
                'type' => PARAM_INT,
                'description' => 'Is default tenant',
                'default' => 0,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'description' => 'Sort order',
                'default' => 0,
            ],
            'categoryid' => [
                'type' => PARAM_INT,
                'description' => 'Category ID this tenant is linked to',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'cssconfig' => [
                'type' => PARAM_RAW,
                'description' => 'The CSS config for this tenant.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'useloginurlid' => [
                'type' => PARAM_BOOL,
                'description' => 'Use login URL id',
                'default' => 1,
            ],
            'useloginurlidnumber' => [
                'type' => PARAM_BOOL,
                'description' => 'Use login URL idnumber',
                'default' => 1,
            ],
            'showinloginselector' => [
                'type' => PARAM_BOOL,
                'description' => 'Tenant selector in the login page.',
                'default' => 1,
            ],
            'dashboardlinked' => [
                'type' => PARAM_BOOL,
                'description' => 'Tenand dashboard linked to Site default dashboard.',
                'default' => 1,
            ],
        ];
    }

    /**
     * Tenant name ready for display
     * @return string
     */
    public function get_formatted_name() : string {
        if ($this->get('id') == sharedspace::get_shared_space_id()) {
            return get_string('sharedspace', 'tool_tenant');
        }
        return format_string($this->get('name'), true,
            ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Default tenant badge for display.
     *
     * @return string Default badge is returned if isdefault => true or blank.
     */
    public function get_default_tenant_label(): string {
        return $this->get('isdefault') ? \html_writer::span(get_string('defaultname', 'tool_tenant'), 'badge badge-secondary',
            ['aria-label' => get_string('defaultname', 'tool_tenant')]) : '';
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
        if (hierarchy::has_subtenants($this->get('id'))) {
            $tu = db::generate_alias();
            [$subsql, $subparams] = hierarchy::get_subtenants_sql($this->get('id'));
            $join = " JOIN {tool_tenant_user} {$tu} ON {$tu}.userid = u.id AND {$tu}.tenantid $subsql ";
            $params = array_merge($params, $subparams);
        }
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
            // We need to escape the idnumber for output, but not for the clipboard template (or it will be double-escaped).
            $url = $CFG->wwwroot.'/?tenant='.s($idnumber);
            if ($withcopylinks) {
                $url .= $OUTPUT->render_from_template('tool_wp/copy_to_clipboard', [
                    'text' => "{$CFG->wwwroot}/?tenant={$idnumber}",
                ]);
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

