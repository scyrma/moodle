<?php declare(strict_types=1);

namespace tool_fileslist\external;

defined('MOODLE_INTERNAL') || die();

use Traversable;
use core\external\exporter;
use tool_fileslist\mapping_iterator;
use tool_fileslist\external\file_exporter;
use tool_fileslist\file;
use context_system;

final class files_exporter extends exporter {
    private $files;

    public function __construct(Traversable $files, array $related = []) {
        $this->files = $files;
        parent::__construct([], $related);
    }

    protected static function define_other_properties() : array {
        return [
            'files' => [
                'type' => file_exporter::read_properties_definition(),
                'multiple' => true
            ]
        ];
    }

    protected function get_other_values(\renderer_base $output) : array {
        return [
            'files' => iterator_to_array(
                new mapping_iterator(
                    $this->files,
                    function(file $file) use ($output) {
                        return (new file_exporter($file, ['context' => context_system::instance()]))->export($output);
                    }
                )
            )
        ];
    }
}