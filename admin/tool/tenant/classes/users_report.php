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
 * Class for the users report
 *
 * @package     tool_tenant
 * @copyright   2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_column;

/**
 * Class for the users report
 *
 * @package     tool_tenant
 * @copyright   2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_report extends \tool_reportbuilder\system_report {

    /**
     * Initialise the report.
     */
    public function initialise() {
        $tenantid = $this->get_parameter('id', 0, PARAM_INT);
        $this->set_main_table('user', 'u');
        $this->set_downloadable(false);
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $tenantid);
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);
        $this->add_base_fields('u.id,u.firstname as fullusername,' .
            user_entity::get_all_user_name_fields(true, 'u')); // Necessary for actions.
        $this->add_actions();
        $this->set_columns();
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        $tenantid = $this->get_parameter('id', 0, PARAM_INT);
        return \tool_tenant\manager::can_browse_users($tenantid);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('userscount', 'tool_tenant');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        global $CFG;
        $this->annotate_entity('user', new \lang_string('entityuser', 'tool_reportbuilder'));

        $context = \context_system::instance();
        if (has_capability('tool/tenant:allocate', $context)) {
            $movecolumn = (new report_column(
                'check',
                new \lang_string('select'),
                'user'
            ))
                ->add_fields('u.id,'.user_entity::get_all_user_name_fields(true, 'u'))
                ->set_is_default(true, 0)
                ->add_callback([$this, 'col_checkbox']);
            $this->add_column($movecolumn);
        }

        // Add user column.
        $usercolumn = (new report_column(
            'id',
            new \lang_string('fullname'),
            'user'
        ))
            ->add_fields(user_entity::get_all_user_name_fields(true, 'u'))
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true, 0)
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'fullname']);
        $this->add_column($usercolumn);

        // Get additional fields.
        $additionaluserfields = \get_extra_user_fields($context);
        $extra = preg_split('/,/', $CFG->showuseridentity, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($extra as $key => $userfield) {
            $newcolumn = (new report_column(
                $userfield,
                ($userfield && get_string_manager()->string_exists($userfield, 'moodle')) ? new \lang_string($userfield) : null,
                'user'
            ))
                ->add_field('u.' . $userfield)
                ->set_is_available(in_array($userfield, $additionaluserfields))
                ->set_is_default(true, $key + 3);
            $this->add_column($newcolumn);
            $newcolumn->add_callback([$this, 'country_code_transform']);
        }
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param  int $value
     * @param  \stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(int $value, \stdClass $row) : string {
        $id = 'selectuser' . $value;
        $checkbox = \html_writer::checkbox('users[' . $value . ']', $value, false, null,
            ['id' => $id, 'data-bulkuserid' => $value]);
        $label = get_string('selectuser', 'tool_tenant', fullname($row));
        return $checkbox . \html_writer::tag('label', $label,
                ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Changes the country code to a language string.
     *
     * @param  string    $value Value of the row.
     * @param  \stdClass $row   The whole row
     * @return string the converted country string.
     */
    public function country_code_transform(string $value, \stdClass $row) : string {
        if (!empty($row->country) && $value == $row->country) {
            return get_string($value, 'countries');
        }
        return $value;
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions() {
        $tenantid = $this->get_parameter('id', 0, PARAM_INT);
        if (!\tool_tenant\manager::can_update_users($tenantid)) {
            return;
        }

        $editurl = new \moodle_url('#');
        $editicon = new \pix_icon('i/settings', get_string('edituser'), 'core');
        $action = new \tool_reportbuilder\report_action($editurl, $editicon, [
            'data-user-edit' => true,
            'data-id' => ':id',
            'data-fullusername' => ':fullusername'
        ]);
        $action->add_callback(function($row) {
            $row->fullusername = fullname($row);
            return true;
        });
        $this->add_action($action);
    }
}
