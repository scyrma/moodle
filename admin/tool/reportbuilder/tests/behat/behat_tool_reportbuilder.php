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
 * tool_reportbuilder steps definitions.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_reportbuilder.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_reportbuilder extends behat_base {

    /**
     * Returns the reportbuilder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator() : tool_reportbuilder_generator {
        $datagenerator = testing_util::get_data_generator();
        return $datagenerator->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Returns the organisation generator
     *
     * @return tool_organisation_generator
     * @throws coding_exception
     */
    protected function get_organisation_generator() : tool_organisation_generator {
        $datagenerator = testing_util::get_data_generator();
        return $datagenerator->get_plugin_generator('tool_organisation');
    }

    /**
     * Generates custom reports
     *
     * @Given /^the following custom reports exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_custom_reports_exist(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $this->lookup_tenant($elementdata);
            $generator->create_report($elementdata);
        }
    }

    /**
     * Generate custom report audiences
     *
     * @Given /^the following custom report audiences exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_custom_report_audiences_exist(TableNode $data) {
        global $DB;

        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $record = (object)$elementdata;

            $record->reportid = $DB->get_field('tool_reportbuilder', 'id', ['name' => $record->report], MUST_EXIST);
            unset($record->report);

            if (isset($record->position)) {
                $record->positionid = $this->get_organisation_generator()->lookup_position($record->position);
                unset($record->position);
            }
            if (isset($record->department)) {
                $record->departmentid = $this->get_organisation_generator()->lookup_department($record->department);
                unset($record->department);
            }

            $generator->create_audience($record);
        }
    }

    /**
     * Looks up tenant id
     *
     * @param array $elementdata
     * @throws dml_exception
     */
    protected function lookup_tenant(array &$elementdata) {
        global $DB;
        if (array_key_exists('tenant', $elementdata)) {
            if (empty($elementdata['tenant'])) {
                // Shared for all tenants.
                $elementdata['tenantid'] = 0;
            } else {
                // Lookup tenant id by tenant name.
                $elementdata['tenantid'] = $DB->get_field('tool_tenant', 'id',
                    ['name' => $elementdata['tenant']], MUST_EXIST);
            }
            unset($elementdata['tenant']);
        } else {
            // Otherwise assume default tenant.
            $elementdata['tenantid'] = \tool_tenant\tenancy::get_default_tenant_id();
        }
    }
}
