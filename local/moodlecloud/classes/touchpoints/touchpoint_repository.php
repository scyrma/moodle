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
 * Touchpoint repository.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints;
defined('MOODLE_INTERNAL') || die();

use ArrayIterator;
use DateTimeImmutable;
use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\touchpoint;
use local_moodlecloud\touchpoints\touchpoint_factory;
use moodle_database;
use stdClass;

/**
 * Touchpoint repository class.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class touchpoint_repository {

    /** @var touchpoint_factory $factory */
    private $factory;

    /** @var array $definitions Touchpoint definitions. */
    private $definitions;

    /** @var moodle_database $db */
    private $db;

    /** @var array $dbrownamemap A cache of DB rows where the touchpoint name is the key. */
    private $dbrownamemap;

    /** @var array $pool A cache of touchpoints already retrieved. */
    private $pool;

    /**
     * Constructor.
     *
     * @param moodle_database $db
     * @param touchpoint_factory $factory
     * @param array $definitions Touchpoint definitions.
     */
    public function __construct(
        moodle_database $db,
        touchpoint_factory $factory,
        array $definitions
    ) {
        list($statusinsql, $statusinparams) = $db->get_in_or_equal(
            [touchpoint::STATUS_PENDING, touchpoint::STATUS_RETRYING]
        );

        $this->db = $db;
        $this->factory = $factory;
        $this->definitions = $definitions;
        $this->dbrownamemap = $this->prepersist_new_definitions(
            array_reduce(
                $this->db->get_records_sql("SELECT * FROM {moodlecloud_touchpoints} WHERE status $statusinsql", $statusinparams),
                function($c, $v) {
                    return array_merge($c, [$v->name => $v]);
                }, []
            )
        );
    }

    /**
     * Get all the touchpoints.
     *
     * @return array Array of touchpoints.
     */
    public function get_touchpoints() : array {
        return $this->create_touchpoints_from_definitions($this->definitions);
    }

    /**
     * Persist a touchpoint.
     *
     * @param touchpoint $touchpoint The touchpoint to persist.
     * @param string $status Status to mark against the touchpoint.
     * @param ?array $data Extra data to save against the touchpoint.
     * @return touchpoint
     */
    public function persist(touchpoint $touchpoint, string $status, array $data = null) {
        list($statusinsql, $statusinparams) = $this->db->get_in_or_equal([touchpoint::STATUS_PENDING, touchpoint::STATUS_RETRYING]);
        $this->db->execute(
            "UPDATE {moodlecloud_touchpoints} SET attempts = ?, status = ?, data = ? WHERE name = ? AND status $statusinsql",
            array_merge([$touchpoint->get_num_attempts(), $status, $data ? json_encode($data) : null, $touchpoint->get_name()], $statusinparams)
        );

        return $touchpoint;
    }

    /**
     * Get a touchpoint by name.
     *
     * @param string $name The touchpoint name.
     * @return touchpoint The touchpoint.
     */
    public function get_touchpoint_by_name(string $name) : touchpoint {
        return $this->create_touchpoints_from_definitions(
            [
                functions::find(
                    function($definition) use ($name) {
                        return $definition->name === $name;
                    }, new ArrayIterator($this->definitions)
                )
            ]
        )[0];
    }

    /**
     * Helper function to prepersist new touchpoint definitions before we do anything else.
     *
     * "New" meaning, not existing in the database yet.
     *
     * @param array $dbrownamemap
     * @return array An db row name map with the new touchpoints added.
     */
    private function prepersist_new_definitions(array $dbrownamemap) : array {
        $newdefinitions = array_filter(
            $this->definitions,
            function($definition) use ($dbrownamemap) {
                return !isset($dbrownamemap[$definition->name]);
            }
        );

        return array_merge(
            array_reduce(
                $newdefinitions,
                function($c, $v) {
                    $newrecord = (object)[
                        // Set the created time to just a bit in the past to ensure the soonest possible execution.
                        'created' => time() - $v->cooldown - 1,
                        'name' => $v->name,
                        'status' => touchpoint::STATUS_PENDING,
                        'attempts' => 0
                    ];
                    $this->db->insert_record('moodlecloud_touchpoints', $newrecord);
                    return array_merge($c, [$v->name => $newrecord]);
                },
                []
            ),
            $dbrownamemap
        );
    }

    /**
     * Helper function to create touchpoint instances from a definitions file.
     *
     * @param array $defintions Array of touchpoint definitions.
     * @return array Array of touchpoint instances.
     */
    private function create_touchpoints_from_definitions(array $definitions) : array {
        return array_map(
            function(stdClass $datum) {
                // If we've already created this touchpoint, return it.
                if (!empty($this->pool[$datum->name])) {
                    return $this->pool[$datum->name];
                }

                // Create a list of actions we need to add to the touchpoint.
                // We don't simply add them all because in some cases we are retrying a touchpoint,
                // and we only want to retry the actions that didn't succeed the first time.
                $actionwhitelist = !empty($this->dbrownamemap[$datum->name]->data)
                                       ? array_keys(json_decode($this->dbrownamemap[$datum->name]->data, true))
                                       : false;

                if ($actionwhitelist) {
                    $datum->actions = array_filter(
                        $datum->actions,
                        function($action) use ($actionwhitelist) {
                            return in_array($action->name, $actionwhitelist);
                        }
                    );
                }

                // Add to the pool and return.
                return $this->pool[$datum->name] = $this->factory->create_instance(
                    $datum,
                    $this->dbrownamemap[$datum->name]
                );
            },
            $definitions
        );
    }
}
