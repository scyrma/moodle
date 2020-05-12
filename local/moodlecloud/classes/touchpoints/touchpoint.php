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
 * Touchpoint class.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints;
defined('MOODLE_INTERNAL') || die();

use DateTimeImmutable;
use InvalidArgumentException;
use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\criteria;
use local_moodlecloud\touchpoints\exceptions\cannot_execute;

/**
 * Class representing a touchpoint.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class touchpoint {

    const STATUS_PENDING = 'pending';
    const STATUS_FAILURE = 'failure';
    const STATUS_SUCCESS = 'success';
    const STATUS_RETRYING = 'retrying';

    /** @var criteria $criteria Touchpoint execution criteria. */
    private $criteria;

    /** @var DateTimeImmutable $executiontime The time after which the touchpoint can be executed. */
    private $executiontime;

    /** @var array $actions Array of actions to execute. */
    private $actions;

    /** @var string $name The touchpoint name. */
    private $name;

    /** @var int $numattempts The number of times we have tried to execute this touchpoint. */
    private $numattempts;

    /**
     * Constructor.
     *
     * @param string $name The touchpoint name.
     * @param criteria $criteria Execution criteria.
     * @param array $actions Array of actions to execute.
     * @param DateTimeImmutable $executiontime The time after which the touchpoint can be executed.
     * @param int $numattempts The number of times we have tried to execute this touchpoint.
     */
    public function __construct(
        string $name,
        criteria $criteria,
        array $actions,
        DateTimeImmutable $executiontime,
        int $numattempts
    ) {
        $this->name = $name;
        $this->criteria = $criteria;
        $this->actions = $actions;
        $this->executiontime = $executiontime;
        $this->numattempts = $numattempts;
    }

    /**
     * Convert a human readable touchpoint name to an identifier.
     *
     * Used in things like lang strings.
     *
     * @param string $name The touchpoint name.
     * @return string
     */
    public static function name_to_identifier(string $name) : string {
        return functions::compose('strtolower', functions::partial('str_replace', ' ', '_'))($name);
    }

    /**
     * Get the touchpoint's name.
     *
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Get the touchpoint criteria.
     *
     * @return criteria
     */
    public function get_criteria() : criteria {
        return $this->criteria;
    }

    /**
     * Get the touchpoint actions.
     *
     * @return array
     */
    public function get_actions() : array {
        return $this->actions;
    }

    /**
     * Get the number of attempts made to execute this touchpoint.
     *
     * @return int
     */
    public function get_num_attempts() : int {
        return $this->numattempts;
    }

    /**
     * Can we execute the touchpoint?
     *
     * @return bool
     */
    public function can_execute_actions() : bool {
        return $this->criteria_is_met() && time() >= $this->executiontime->getTimestamp();
    }

    /**
     * Get the touchpoint execution time.
     *
     * @return DateTimeImmutable
     */
    public function get_execution_time() : DateTimeImmutable {
        return $this->executiontime;
    }

    /**
     * Returns true if the touchpoint criteria is met.
     *
     * @return bool
     */
    public function criteria_is_met() : bool {
        return $this->criteria->is_met();
    }

    /**
     * Increment the number of execution attempts.
     *
     * @return self
     */
    public function increment_attempts() : self {
        return new self(
            $this->name,
            $this->criteria,
            $this->actions,
            $this->executiontime,
            $this->numattempts + 1
        );
    }

    /**
     * Execute this touchpoint.
     *
     * @throws cannot_execute when there the touchpoint cannot be executed.
     * @return array An array of computation results. The keys are the action names.
     */
    public function execute() : array {
        if (!$this->can_execute_actions()) {
            throw new cannot_execute('Cannot execute an unexecutable touchpoint.');
        }

        return array_reduce($this->actions, function($c, $v) {
            return array_merge($c, [$v->get_name() => $v->execute($c)]);
        }, []);
    }
}
