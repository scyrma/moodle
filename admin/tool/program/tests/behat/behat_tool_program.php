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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * tool_tenant steps definitions.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use tool_program\api;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_tenant\tenant;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_tenant.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_program extends behat_base {
    /**
     * Each element specifies:
     * - The data generator sufix used.
     * - The required fields.
     * - The mapping between other elements references and database field names.
     *
     * @var array
     */
    protected static $elements = [
        'programs' => [
            'datagenerator' => 'program',
            'required' => [
                'fullname'
            ],
            'switchids' => [
                'tenant' => 'tenantid',
            ],
        ],
        'program_users' => [
            'datagenerator' => 'program_user',
            'required' => [
                'user',
                'program',
            ],
            'switchids' => [
                'user' => 'userid',
                'program' => 'programid',
            ],
        ],
        "program_courses" => [
            'datagenerator' => 'program_course',
            'required' => [
                'course',
                'program',
            ],
            'switchids' => [
                'course' => 'courseid',
                'program' => 'programid',
            ],
        ]
    ];

    /**
     * @var int $instancecount
     */
    protected $instancecount = 0;

    /**
     * Creates the specified element. More info about available elements in http://docs.moodle.org/dev/Acceptance_testing#Fixtures.
     *
     * @Given /^the following tool program data "(?P<element_string>(?:[^"]|\\")*)" exist:$/
     *
     * @param string $elementname The name of the entity to add
     * @param TableNode $data
     */
    public function the_following_tool_program_data_exist($elementname, TableNode $data): void {
        // Now that we need them require the data generators.
        require_once(__DIR__ . '/../../../../../lib/phpunit/classes/util.php');

        if (empty(self::$elements[$elementname])) {
            throw new PendingException($elementname . ' data generator is not implemented');
        }

        $datagenerator = testing_util::get_data_generator();
        $toolprogramgenerator = $datagenerator->get_plugin_generator('tool_program');
        $elementdatagenerator = self::$elements[$elementname]['datagenerator'];
        $requiredfields = self::$elements[$elementname]['required'];
        if (!empty(self::$elements[$elementname]['switchids'])) {
            $switchids = self::$elements[$elementname]['switchids'];
        }

        foreach ($data->getHash() as $elementdata) {
            // Check if all the required fields are there.
            foreach ($requiredfields as $requiredfield) {
                if (!isset($elementdata[$requiredfield])) {
                    throw new RuntimeException($elementname . ' requires the field ' . $requiredfield . ' to be specified');
                }
            }

            // Switch from human-friendly references to ids.
            if (!empty($switchids)) {
                foreach ($switchids as $element => $field) {
                    $methodname = 'get_' . $element . '_id';
                    // Not all the switch fields are required, default vars will be assigned by data generators.
                    if (isset($elementdata[$element])) {
                        // Temp $id var to avoid problems when $element == $field.
                        $id = $this->{$methodname}($elementdata[$element]);
                        unset($elementdata[$element]);
                        $elementdata[$field] = $id;
                    }
                }
            }

            // Preprocess the entities that requires a special treatment.
            if (method_exists($this, 'preprocess_' . $elementdatagenerator)) {
                $elementdata = $this->{'preprocess_' . $elementdatagenerator}($elementdata);
            }

            // Creates element.
            $methodname = 'create_' . $elementdatagenerator;
            if (method_exists($toolprogramgenerator, $methodname)) {
                // Using data generators directly.
                $toolprogramgenerator->{$methodname}($elementdata);
            } else if (method_exists($this, 'process_' . $elementdatagenerator)) {
                // Using an alternative to the direct data generator call.
                $this->{'process_' . $elementdatagenerator}($elementdata);
            } else {
                throw new PendingException($elementname . ' data generator is not implemented');
            }
        }
    }

    /**
     * Preprocess program
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_program($data): array {
        if (!isset($data['program_tags'])) {
            $data['program_tags'] = [
                'hello', 'world'
            ];
        } else {
            $data['program_tags'] = explode(',', $data['program_tags']);
        }

        $data = $this->get_date_constant($data, 'enddatetype');
        $data = $this->get_date_constant($data, 'duedatetype');
        $data = $this->get_date_constant($data, 'startdatetype');

        return $data;
    }

    /**
     * Convert the date constant name to its value
     *
     * @param array $data
     * @param string $fieldname
     * @return array
     */
    protected function get_date_constant(array $data, string $fieldname): array {
        if (!empty($data[$fieldname]) && !is_numeric($data[$fieldname])) {
            $data[$fieldname] = constant('\tool_program\constants::DATE_' . strtoupper($data[$fieldname]));
        }
        return $data;
    }

    /**
     * Process program
     *
     * @param stdClass|array $record
     * @return program
     */
    public function process_program($record = null): program {
        if (!array_key_exists('fullname', $record)) {
            $record['fullname'] = 'New program ' . (++$this->instancecount);
        }
        $record['descriptionformat'] = FORMAT_HTML;
        $program = api::create_program((object) $record);

        // Some properties like 'completioncriteria' and 'completionatleast' apply to the base set and not the program.
        if (isset($record['completioncriteria'])) {
            $record['setid'] = $program->get_base_set()->get('id');
            api::update_set_completion_criteria((object)$record);
        }

        if (!empty($record['generatecourses'])) {
            for ($i = 0; $i < $record['generatecourses']; $i++) {
                $course = $this->get_generator()->generate_course_with_completion_self();
                $this->get_generator()->add_course_to_set($course->id, $program->get_base_set()->get('id'));
            }
        }
        return $program;
    }

    /**
     * Get program id
     *
     * @param string $programfullname
     * @return int
     */
    protected function get_program_id(string $programfullname): int {
        global $DB;
        return (int) $DB->get_record(program::TABLE, ['fullname' => $programfullname], '*', MUST_EXIST)->id;
    }

    /**
     * Get user id
     *
     * @param string $username
     * @return int
     */
    protected function get_user_id(string $username): int {
        global $DB;
        return (int) $DB->get_record('user', ['username' => $username], '*', MUST_EXIST)->id;
    }

    /**
     * Gets the tenant id.
     *
     * @param string $tenantname
     * @return int
     */
    protected function get_tenant_id(string $tenantname): int {
        global $DB;
        return (int) $DB->get_record(tenant::TABLE, ['name' => $tenantname], '*', MUST_EXIST)->id;
    }

    /**
     * Gets the course id.
     *
     * @param string $coursename
     * @return int
     */
    protected function get_course_id(string $coursename): int {
        global $DB;
        return (int) $DB->get_record('course', ['shortname' => $coursename], '*', MUST_EXIST)->id;
    }

    /**
     * Preprocess program user
     *
     * @param array $data
     * @return array
     */
    public function preprocess_program_user($data): array {
        if (!isset($data['certificationid'])) {
            $data['certificationid'] = 0;
        }

        return $data;
    }

    /**
     * Process program user
     *
     * @param stdClass|array $record
     * @return program_user
     */
    public function process_program_user($record = null): program_user {
        $program = new program($record['programid']);

        return api::allocate_user($program, (object) $record);
    }

    /**
     * Process program course
     *
     * @param null $record
     */
    public function process_program_course($record = null) {
        api::add_course_to_base_set($record['programid'], $record['courseid']);
    }

    /**
     * Returns the program generator
     *
     * @return tool_program_generator|component_generator_base
     */
    protected function get_generator(): tool_program_generator {
        $datagenerator = testing_util::get_data_generator();

        return $datagenerator->get_plugin_generator('tool_program');
    }

    /**
     * Allocates users to programs
     *
     * @Given /^the following users allocations to programs exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_user_allocations_to_programs_exist(TableNode $data): void {
        foreach ($data->getHash() as $elementdata) {
            $programid = $this->get_program_id($elementdata['program']);

            $elementdata = array_filter($elementdata, function($value) {
                return ''.$value !== '';
            });

            $programuserdata = (object)$elementdata;
            $programuserdata->programid = $programid;
            $programuserdata->userid = $this->get_user_id($elementdata['user']);
            $programuserdata->certificationid = 0;

            $program = new program($programid);
            api::allocate_user($program, $programuserdata);
        }
    }

    /**
     * Completes program allocations
     *
     * @Given /^the following tool program user allocations are completed:$/
     *
     * @param TableNode $data
     */
    public function the_following_tool_program_user_allocations_are_completed(TableNode $data): void {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $programid = $this->get_program_id($elementdata['program']);
            $program = new program($programid);
            $userid = $this->get_user_id($elementdata['user']);
            $generator->complete_program($program, $userid);
        }
    }

    /**
     * Allocates users to programs
     *
     * @Given /^I press "(?P<button_string>(?:[^"]|\\")*)" for the "(?P<course_string>(?:[^"]|\\")*)" program course$/
     *
     * @param string $buttonname
     * @param string $coursename
     */
    public function i_press_for_the_program_course($buttonname, $coursename): void {
        $xpath = "//div[@data-region='programs-overview-course-view' and contains(.,'" .
            $this->escape($coursename) . "')]//div[@data-region='course-call-to-action']";

        $this->execute('behat_general::i_click_on_in_the',
            [$buttonname, 'button', $xpath, 'xpath_element']);
    }

    /**
     * Adds courses to a program
     *
     * @Given /^the following program courses exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_program_courses_exist(TableNode $data): void {
        foreach ($data->getHash() as $elementdata) {
            $programid = $this->get_program_id($elementdata['program']);
            $courseid = $this->get_course_id($elementdata['course']);

            api::add_course_to_base_set($programid, $courseid);
        }
    }

    /**
     * Return the list of partial named selectors.
     *
     * Those selectors can be used to capture dashboard elements. Examples:
     *    And I click on "Expand" "link" in the "ProgramName" "tool_program > Dashboard item"
     *
     * @return array
     */
    public static function get_partial_named_selectors(): array {
        return [
            new behat_component_named_selector('Dashboard item', [
                <<<XPATH
    .//div[contains(concat(' ', normalize-space(@class), ' '), ' dashboard-item ')
            and
            normalize-space(descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' element-name ')]) = %locator%
            ]
XPATH
            ], true),
        ];
    }
}
