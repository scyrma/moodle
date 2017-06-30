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
 * Stored file class.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use context;
use moodle_url;
use stdClass;

/**
 * Class representing a file stored by Moodle.
 *
 * Note this is similar but distinct from core's stored_file class.
 * This class is simply a data container/aggregate for elements we need
 * to display to users.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stored_file implements file {
    /**
     * @var int $id The file's id in the database.
     */
    private $id;

    /**
     * @var stdClass $user User object (i.e. a record from the users table).
     */
    private $user;

    /**
     * @var context $context Context associated with the file.
     */
    private $context;

    /**
     * @var string $component Component the file belongs to.
     */
    private $component;

    /**
     * @var string $filearea Filearea the file belongs to.
     */
    private $filearea;

    /**
     * @var string $path Path to the file.
     */
    private $path;

    /**
     * @var string $name Name of the file.
     */
    private $name;

    /**
     * @var int $size File size in bytes.
     */
    private $size;

    /**
     * @var string $mimetype MIME type of the file.
     */
    private $mimetype;

    /**
     * @var moodle_url $locationurl URL pointing to where the file is used in Moodle.
     */
    private $locationurl;

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
