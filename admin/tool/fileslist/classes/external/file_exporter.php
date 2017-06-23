<?php declare(strict_types=1);

namespace tool_fileslist\external;

use tool_fileslist\file;
use core\external\exporter;
use renderer_base;
use context_system;

final class file_exporter extends exporter {
    private $file;

    public function __construct(file $file, array $related = []) {
        $this->file = $file;

        parent::__construct(
            (object)[
                'id' => $file->get_id(),
                'component' => $file->get_component(),
                'filearea' => $file->get_filearea(),
                'name' => $file->get_name(),
                'mimetype' => $file->get_mimetype(),
                'size' => $file->get_size(),
                'location' => $file->get_location_url()->out(true)
            ],
            $related
        );
    }

    protected static function define_other_properties() : array  {
        return [
            'user' => [
                'type' => user_exporter::read_properties_definition()
            ]
        ];
    }

    protected static function define_properties() : array {
        return [
            'id' => ['type' => PARAM_INT],
            'component' => ['type' => PARAM_TEXT],
            'filearea' => ['type' => PARAM_TEXT],
            'name' => ['type' => PARAM_TEXT],
            'mimetype' => ['type' => PARAM_TEXT],
            'size' => ['type' => PARAM_INT],
            'location' => ['type' => PARAM_URL]
        ];
    }

    protected function get_other_values(renderer_base $output) : array {
        return [
            'user' => (new user_exporter($this->file->get_user(), ['context' => context_system::instance()]))->export($output)
        ];
    }

    protected static function define_related() : array {
        return ['context' => 'context'];
    }
}