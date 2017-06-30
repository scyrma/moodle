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
 * Files exporter.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist\external;

defined('MOODLE_INTERNAL') || die();

use Traversable;
use context_system;
use core\external\exporter;
use renderer_base;
use tool_fileslist\external\file_exporter;
use tool_fileslist\file;
use tool_fileslist\mapping_iterator;

/**
 * Class for exporting a collection of file objects.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class files_exporter extends exporter {
    /**
     * @var Traversable $files Collection of files to export.
     */
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

    protected function get_other_values(renderer_base $output) : array {
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
