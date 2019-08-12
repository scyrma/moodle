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
 * Program class
 *
 * @package   tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\persistent;

use context_system;
use core\persistent;
use moodle_url;
use stdClass;
use stored_file;
use tool_program\constants;
use tool_program\form\edit_program_details_form;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program
 *
 * @package tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_program';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'tenantid' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'fullname' => [
                'type' => PARAM_TEXT,
            ],
            'idnumber' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'description' => [
                'type' => PARAM_RAW,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED
            ],
            'descriptionformat' => [
                'choices' => [
                    FORMAT_HTML,
                    FORMAT_MOODLE,
                    FORMAT_PLAIN,
                    FORMAT_MARKDOWN
                ],
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE,
            ],
            'startdatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'startdateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'startdaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'duedatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'duedateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'duedaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'enddatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'enddateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'enddaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'allocationstartdatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'allocationstartdateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'allocationenddatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'allocationenddateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'allocationenddaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'visible' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => true,
            ],
            'archived' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false,
            ],
            'timearchived' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'allowdirectallocation' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => true,
            ],
        ];
    }

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param stdClass $record If set will be passed to {@link self::from_record()}.
     */
    public function __construct(int $id = 0, stdClass $record = null) {
        if ($record) {
            $record = (object)array_intersect_key((array)$record, self::properties_definition());
        }
        if ($id && $record) {
            debugging('Either id or record need to be specified in the persistent constructor but not both',
                DEBUG_DEVELOPER);
        }
        parent::__construct($id, $record);
    }

    /**
     * Gets list of sets for this program.
     *
     * @return program_set[]
     */
    public function get_sets(): array {
        return program_set::get_records(['programid' => $this->get('id')]);
    }

    /**
     * Gets amount of sets for this program.
     *
     * @return int
     */
    public function get_sets_count(): int {
        return count($this->get_sets());
    }

    /**
     * Gets list of course ids within the program (does not return duplicates).
     *
     * @return int[]
     */
    public function get_courses_ids(): array {
        global $DB;

        $sql = 'SELECT DISTINCT pco.courseid
                           FROM {' . program_course::TABLE . '} pco
                     INNER JOIN {' . program_set::TABLE . '} s
                             ON s.id = pco.setid
                          WHERE s.programid = :programid ';

        return $DB->get_fieldset_sql($sql, ['programid' => $this->get('id')]);
    }

    /**
     * Gets list of courses within the program, does not return duplicates.
     * Do not confuse courses with program courses, this one returns the "core" course records.
     *
     * @return stdClass[]
     */
    public function get_courses(): array {
        global $DB;

        $courses = [];

        if ($coursesids = $this->get_courses_ids()) {
            [$sql, $params] = $DB->get_in_or_equal($coursesids, SQL_PARAMS_NAMED, 'id');
            $where = 'WHERE id ' . $sql;
            $courses = $DB->get_records_sql('SELECT * FROM {course} ' . $where . ' ORDER BY sortorder DESC', $params);
        }

        return $courses;
    }

    /**
     * Gets list of program courses within the program.
     *
     * @return program_course[]
     */
    public function get_program_courses(): array {
        global $DB;

        $programcourses = [];

        $sql = 'SELECT pco.*
                  FROM {' . program_course::TABLE . '} pco
            INNER JOIN {course} cou
                    ON cou.id = pco.courseid
            INNER JOIN {' . program_set::TABLE . '} pcoparent
                    ON pcoparent.id = pco.setid
                 WHERE pcoparent.programid = :programid ';
        $records = $DB->get_records_sql($sql, ['programid' => $this->get('id')]);

        foreach ($records as $record) {
            $programcourses[] = new program_course(0, $record);
        }

        return $programcourses;
    }

    /**
     * Gets amount of courses.
     *
     * @return int
     */
    public function get_courses_count(): int {
        return count($this->get_courses_ids());
    }

    /**
     * Gets list of users allocated to a program.
     *
     * Note, there is no tenant check here!
     *
     * @return int[]
     */
    protected function get_users_ids(): array {
        global $DB;

        return $DB->get_fieldset_select(program_user::TABLE, 'userid', 'programid = :programid', ['programid' => $this->get('id')]);
    }

    /**
     * Gets amount of users allocated to a program.
     *
     * @return int
     */
    public function get_users_count(): int {
        return count($this->get_users_ids());
    }

    /**
     * Gets list of users allocated to a program.
     *
     * Note, there is no tenant check here!
     *
     * @return stdClass[]
     */
    public function get_users(): array {
        global $DB;

        $users = [];

        if ($userids = $this->get_users_ids()) {
            [$sql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'id');
            $where = 'WHERE id ' . $sql;
            $users = $DB->get_records_sql('SELECT * FROM {user} ' . $where, $params);
        }
        return $users;
    }

    /**
     * Gets program users
     *
     * @return program_user[]
     */
    public function get_program_users(): array {
        return program_user::get_records(['programid' => $this->get('id')]);
    }

    /**
     * Get program image
     *
     * @return stored_file|false
     */
    public function get_image() {
        global $CFG;
        require_once($CFG->libdir . '/filestorage/file_storage.php');

        $fs = get_file_storage();
        $context = context_system::instance();
        $storedimages = $fs->get_area_files($context->id, 'tool_program', 'program_image', $this->get('id'), 'filename', false);

        return reset($storedimages);
    }

    /**
     * Get image url
     *
     * @return string
     */
    public function get_image_url(): string {
        if ($file = $this->get_image()) {
            return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        }
        return '';
    }

    /**
     * Gets Base set from program (defined as the set with parent = 0 from that program).
     *
     * @return program_set|false
     */
    public function get_base_set() {
        return program_set::get_record(['parent' => 0, 'programid' => $this->get('id')]);
    }

    /**
     * Check if program is archived
     *
     * @return bool
     */
    public function is_archived(): bool {
        return (1 === (int) $this->get('archived'));
    }

    /**
     * Check if program is hidden
     *
     * @return bool
     */
    public function is_hidden(): bool {
        return (1 !== (int) $this->get('visible'));
    }

    /**
     * Returns the embedded files in the description of the program (if any exists).
     *
     * @return stored_file[]
     */
    public function get_embedded_description_files(): array {
        global $CFG;
        require_once($CFG->libdir . '/filestorage/file_storage.php');
        $fs = get_file_storage();
        $context = context_system::instance();
        $files = $fs->get_area_files($context->id, 'tool_program', 'program_description', $this->get('id'), 'filename', false);
        if (count($files)) {
            $filesoptions = edit_program_details_form::description_editor_options();
            $acceptedtypes = $filesoptions['accepted_types'];
            if ($acceptedtypes !== '*') {
                require_once($CFG->libdir . '/filelib.php');
                foreach ($files as $key => $file) {
                    if (!file_extension_in_typegroup($file->get_filename(), $acceptedtypes)) {
                        unset($files[$key]);
                    }
                }
            }
            $fileslimit = $filesoptions['maxfiles'];
            if ($fileslimit > 0 && count($files) > $fileslimit) {
                // Return no more than $fileslimit number of files.
                $files = array_slice($files, 0, $fileslimit, true);
            }
        }

        return $files;
    }

    /**
     * Program context
     *
     * @return \context
     */
    public function get_context(): \context {
        return \context_system::instance();
    }
}
