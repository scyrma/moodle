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
 * tool_tenant steps definitions.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        return api::create_program((object) $record);
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
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $progid = $this->get_program_id($elementdata['program']);
            $generator->allocate_user_to_program($progid, $this->get_user_id($elementdata['user']), '0');
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
}
