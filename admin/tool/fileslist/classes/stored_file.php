<?php declare(strict_types=1);

namespace tool_fileslist;

use context;
use moodle_url;
use stdClass;

final class stored_file implements file {
    private $context;
    private $component;
    private $filearea;
    private $path;
    private $name;
    private $size;
    private $userid;
    private $mimetype;

    public function __construct(
        int $id,
        stdClass $user,
        context $context,
        string $component,
        string $filearea,
        string $path,
        string $name,
        int $size,
        string $mimetype,
        moodle_url $locationurl
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->context = $context;
        $this->component = $component;
        $this->filearea = $filearea;
        $this->path = $path;
        $this->name = $name;
        $this->locationurl = $locationurl;
        $this->size = $size;
        $this->mimetype = $mimetype;
    }

    public function get_id() : int {
        return $this->id;
    }

    public function get_context() : context {
        return $this->context;
    }

    public function get_component() : string {
        return $this->component;
    }

    public function get_filearea() : string {
        return $this->filearea;
    }

    public function get_mimetype() : string {
        return $this->mimetype;
    }

    public function get_path() : string {
        return $this->path;
    }

    public function get_name() : string {
        return $this->name;
    }

    public function get_location_url() : moodle_url {
        return $this->locationurl;
    }

    public function get_size() : int {
        return $this->size;
    }

    public function get_user() : stdClass {
        return $this->user;
    }
}
