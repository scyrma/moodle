<?php declare(strict_types=1);

namespace tool_fileslist;

use context;
use moodle_url;
use stdClass;

interface file {
    public function get_id() : int;
    public function get_user() : stdClass;
    public function get_context() : context;
    public function get_component() : string;
    public function get_filearea() : string;
    public function get_mimetype() : string;
    public function get_path() : string;
    public function get_name() : string;
    public function get_size() : int;
    public function get_location_url() : moodle_url;
}
