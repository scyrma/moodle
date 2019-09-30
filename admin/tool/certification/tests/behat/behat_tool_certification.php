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
 * Behat tests.
 *
 * @package   tool_certification
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_program\persistent\program;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_certification.
 *
 * @package   tool_certification
 * @category  test
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_certification extends behat_base {

    /**
     * Each element specifies:
     * - The data generator sufix used.
     * - The required fields.
     * - The mapping between other elements references and database field names.
     *
     * @var array
     */
    protected static $elements = [
        'certifications' => [
            'datagenerator' => 'certification',
            'required' => [
                'fullname'
            ],
            'switchids' => [
                'tenant' => 'tenantid',
                'program' => 'program'
            ],
        ],
        'certification_users' => [
            'datagenerator' => 'certification_user',
            'required' => [
                'user',
                'certification',
            ],
            'switchids' => [
                'user' => 'userid',
                'certification' => 'certificationid',
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
     * @Given /^the following tool certification data "(?P<element_string>(?:[^"]|\\")*)" exist:$/
     *
     * @param string $elementname The name of the entity to add
     * @param TableNode $data
     */
    public function the_following_tool_certification_data_exist($elementname, TableNode $data): void {
        // Now that we need them require the data generators.
        require_once(__DIR__ . '/../../../../../lib/phpunit/classes/util.php');

        if (empty(self::$elements[$elementname])) {
            throw new PendingException($elementname . ' data generator is not implemented');
        }

        $datagenerator = testing_util::get_data_generator();
        $toolcertgenerator = $datagenerator->get_plugin_generator('tool_certification');
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
            if (method_exists($toolcertgenerator, $methodname)) {
                // Using data generators directly.
                $toolcertgenerator->{$methodname}($elementdata);
            } else if (method_exists($this, 'process_' . $elementdatagenerator)) {
                // Using an alternative to the direct data generator call.
                $this->{'process_' . $elementdatagenerator}($elementdata);
            } else {
                throw new PendingException($elementname . ' data generator is not implemented');
            }
        }
    }

    /**
     * Returns the certification generator
     *
     * @return tool_certification_generator
     * @throws coding_exception
     */
    protected function get_generator() : tool_certification_generator {
        $datagenerator = testing_util::get_data_generator();
        return $datagenerator->get_plugin_generator('tool_certification');
    }

    /**
     * Preprocess certification
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_certification($data): array {
        if (isset($data['certification_tags'])) {
            $data['certification_tags'] = explode(',', $data['certification_tags']);
        }
        return $data;
    }

    /**
     * Process certification
     *
     * @param stdClass|array $record
     * @return certification
     */
    public function process_certification($record = null): certification {
        if (!array_key_exists('fullname', $record)) {
            $record['fullname'] = 'New certification ' . (++$this->instancecount);
        }

        $generator = $this->get_generator();
        return $generator->generate_certification($record);
    }

    /**
     * Get certification id
     *
     * @param string $certfullname
     * @return int
     */
    protected function get_certification_id(string $certfullname): int {
        global $DB;
        return (int) $DB->get_record(certification::TABLE, ['fullname' => $certfullname], '*', MUST_EXIST)->id;
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
     * Gets the tenant id from it's name.
     * @throws Exception
     * @param string $tenantname
     * @return int
     */
    protected function get_tenant_id($tenantname) {
        global $DB;
        if (!$id = $DB->get_record(\tool_tenant\tenant::TABLE, ['name' => $tenantname], '*', MUST_EXIST)->id) {
            throw new Exception('The specified tenant with name "' . $tenantname . '" does not exist');
        }
        return $id;
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
     * Process certification user
     *
     * @param array $record
     * @return certification_user
     */
    public function process_certification_user($record): certification_user {
        $certification = new certification($record['certificationid']);
        if (!isset($record['status']) || !strlen($record['status'])) {
            $record['status'] = 1;
        }
        return api::allocate_user($certification, (object) $record);
    }

    /**
     * Open the auto-complete suggestions list (Assuming there is only one on the page.).
     *
     * @Given /^I open the autocomplete program list$/
     */
    public function i_open_the_autocomplete_program_list(): void {
        $csselement = '.form-autocomplete-downarrow';
        $nodeelement = '.select_program_field';
        $this->execute('behat_general::i_click_on_in_the', [$csselement, 'css_element', $nodeelement, 'css_element']);
    }

    /**
     * Allocates users to certifications
     *
     * @Given /^the following users allocations to certifications exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_user_allocations_to_certifications_exist(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $certificationid = $this->get_certification_id($elementdata['certification']);
            $generator->allocate_user($this->get_user_id($elementdata['user']), $certificationid);
        }
    }

    /**
     * Completes certification allocations
     *
     * @Given /^the following tool certification user allocations are certified:$/
     *
     * @param TableNode $data
     */
    public function the_following_tool_certification_user_allocations_are_certified(TableNode $data): void {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $certificationid = $this->get_certification_id($elementdata['certification']);
            $certification = new certification($certificationid);
            $userid = $this->get_user_id($elementdata['user']);
            $generator->complete_certification($certification, $userid);
        }
    }

    /**
     * Navigate to one more week date in the calendar.
     *
     * @Given /^I view the calendar for "(?P<week>\d+)" more weeks$/
     * @param int $weeks the number of weeks
     */
    public function i_view_the_calendar_for_one_more_week(int $weeks): void {
        $time = strtotime("+$weeks week");
        $this->getSession()->visit($this->locate_path('/calendar/view.php?view=day&course=1&time='.$time));
    }
}
