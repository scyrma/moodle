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
 * Criteria resolver
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints\resolvers;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\criterion;
use local_moodlecloud\touchpoints\resolver;
use local_moodlecloud\util;

/**
 * Class to resolve criterion from a simple string.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criterion_resolver implements resolver {

    /** @var string $criterianamespace The namespace where criteria live. */
    private $criterianamespace;

    /**
     * Constructor.
     *
     * @param string $criterianamespace The namespace where criteria live.
     */
    public function __construct(string $criterianamespace) {
        $this->criterianamespace = $criterianamespace;
    }

    public function resolve(string $criterianame, ...$arguments) : criterion {
        return functions::instance_from_string($this->criterianamespace . '\\' . $criterianame, ...$arguments);
    }
}
