<?php declare(strict_types=1);
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
 * File factory.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use context_module;
use file_storage;
use moodle_database;
use moodle_url;
use stdClass;

/**
 * Class to instantiate a file object.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_factory {
    /**
     * @var file_storage $fs Moodle file storage API.
     */
    private $fs;

    /**
     * callable $getuser A callable that knows how to return a user object.
     */
    private $getuser;

    public function __construct(file_storage $fs, callable $usercache) {
        $this->fs = $fs;
        $this->getuser = $usercache;
    }

    public function create_instance(
        stdClass $record
    ) : file {
        $loadcontext = function($cid) {
            return context::instance_by_id($cid);
        };

        return new stored_file(
            ...(util::array_builder($this->fs->get_file_instance($record))
                ->push_from_inner_class('get_id')->to_int()
                ->push_from_inner_class('get_userid')->to_int()->transform($this->getuser)
                ->push_from_inner_class('get_contextid')->transform($loadcontext)
                ->push_from_inner_class('get_component')
                ->push_from_inner_class('get_filearea')
                ->push_from_inner_class('get_filepath')
                ->push_from_inner_class('get_filename')
                ->push_from_inner_class('get_filesize')->to_int()
                ->push_from_inner_class('get_mimetype')
                // NB: \stored_file is _Moodle's_ stored file, not our implementation of the file interface.
                // It is the same instance from the call to get_file_instance above.
                ->push_from_callable(function(\stored_file $innermoodlestoredfile, array_builder $builder) {
                    if ($builder->get(2) instanceof context_module ||
                        $builder->get(2) instanceof context_course
                    ) {
                        return new moodle_url('/course/view.php', ['id' => $builder->get(2)->get_course_context()->instanceid]);
                    }

                    if ($innermoodlestoredfile->get_filearea() == 'private') {
                        return new moodle_url('/user/profile.php', ['id' => $innermoodlestoredfile->get_userid()]);
                    }

                    return new moodle_url(null);
                })
                ->build()
            )
        );
    }
}
