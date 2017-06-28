<?php declare(strict_types=1);

namespace tool_fileslist;

use context;
use context_course;
use context_module;
use file_storage;
use moodle_database;
use moodle_url;
use stdClass;

final class file_factory {
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

        return new stored_file(
            // Use the array_accumulator to massage the data from the DB in to the correct
            // format for the stored_file constructor.
            ...(lambda::array_accumulator($this->fs->get_file_instance($record))
                ->get_id()->to_int()
                ->get_userid()->to_int()->transform($this->getuser)
                ->get_contextid()->transform(function($cid) {return context::instance_by_id($cid);})
                ->get_component()
                ->get_filearea()
                ->get_filepath()
                ->get_filename()
                ->get_filesize()->to_int()
                ->get_mimetype()
                ->insert(function(\stored_file $storedfile, $acc) {
                    if ($acc->get(2) instanceof context_module ||
                        $acc->get(2) instanceof context_course
                    ) {
                        return new moodle_url('/course/view.php', ['id' => $acc->get(2)->get_course_context()->instanceid]);
                    }

                    // NB: storedfile refers to Moodle core's stored file class. Not the stored_file class from our plugin.
                    if ($storedfile->get_filearea() == 'private') {
                        return new moodle_url('/user/profile.php', ['id' => $storedfile->get_userid()]);
                    }

                    return new moodle_url(null);

                    // return new moodle_url(
                    //     ...($storedfile->get_filearea() == 'private'
                    //         ? ['/user/profile.php', ['id' => $storedfile->get_userid()]]
                    //         : ($acc->get(2) instanceof context_module || $acc->get(2) instanceof context_course
                    //            ? ['/course/view.php', ['id' => $acc->get(2)->get_course_context()->instanceid]]
                    //            : [null]
                    //         )
                    //     )
                    // );
                })
                ->dump()
            )
        );
    }
}
