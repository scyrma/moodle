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
 * Touchpoint factory.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints;
defined('MOODLE_INTERNAL') || die();

use DateTimeImmutable;
use local_moodlecloud\touchpoints\resolvers\action_resolver;
use local_moodlecloud\touchpoints\resolvers\criterion_resolver;
use stdClass;

/**
 * A factory to create touchpoints.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class touchpoint_factory {

    /** @var criterion_resolver $criterionresolver */
    private $criteriaresolver;

    /** @var action_resolver $actionresolver */
    private $actionresolver;

    /**
     * Constructor.
     *
     * @param criterion_resolver $criterionresolver
     * @param action_resolver $actionresolver
     */
    public function __construct(
        criterion_resolver $criteriaresolver,
        action_resolver $actionresolver
    ) {
        $this->criteriaresolver = $criteriaresolver;
        $this->actionresolver = $actionresolver;
    }

    /**
     * Create a touchpoint instance.
     *
     * @param stdClass $jsonobject The JSON object from the touchpoint definitions file.
     * @param stdClass $dbrow The database row the touchpoint is persisted in.
     * @return touchpoint
     */
    public function create_instance(
        stdClass $jsonobject,
        stdClass $dbrow
    ) : touchpoint {
        return new touchpoint(
            $jsonobject->name,
            new criteria(
                ...array_map(
                    $this->get_resolver_callable($this->criteriaresolver),
                    $jsonobject->criteria
                )
            ),
            array_map(
                $this->get_resolver_callable($this->actionresolver),
                $jsonobject->actions
            ) ?? [],
            (new DateTimeImmutable)->setTimestamp($dbrow->created + $jsonobject->cooldown),
            (int)$dbrow->attempts
        );
    }

    /**
     * Return a callable that can resolve something given an stdClass containing the name and arguments.
     *
     * @param resolver $resolver The resolver to use.
     * @return callable
     */
    private function get_resolver_callable(resolver $resolver) : callable {
        return function(stdClass $datum) use ($resolver) {
            return $resolver->resolve($datum->name, ...$datum->arguments);
        };
    }
}
