<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

declare(strict_types=1);

namespace mod_appointment\reportbuilder\local\systemreports;

use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\user_filter_manager;
use stdClass;
use core_reportbuilder\testable_system_report_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");
require_once("{$CFG->dirroot}/reportbuilder/tests/fixtures/testable_system_report_table.php");

/**
 * Test for sessions system report
 *
 * @package     mod_appointment
 * @covers      \mod_appointment\reportbuilder\local\systemreports\sessions
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Odei Alba <odei.alba@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions_test extends \core_reportbuilder_testcase {

    /**
     * Data provider for {@see test_sessions_filters}
     *
     * @return array
     */
    public function sessions_filters_provider(): array {
        return [
            'All sessions' => [
                [
                    'session_date:status_operator' => select::ANY_VALUE,
                ], [
                    'sessionpast',
                    'sessionongoing',
                    'sessionfuture',
                ]
            ],
            'Only future sessions' => [
                [
                    'session_date:status_operator' => select::EQUAL_TO,
                    'session_date:status_value' => 1,
                ], [
                    'sessionfuture',
                ]
            ],
            'Only ongoing sessions' => [
                [
                    'session_date:status_operator' => select::EQUAL_TO,
                    'session_date:status_value' => 2,
                ], [
                    'sessionongoing',
                ]
            ],
            'Only past sessions' => [
                [
                    'session_date:status_operator' => select::EQUAL_TO,
                    'session_date:status_value' => 3,
                ], [
                    'sessionpast',
                ]
            ],
            'All except future sessions' => [
                [
                    'session_date:status_operator' => select::NOT_EQUAL_TO,
                    'session_date:status_value' => 1,
                ], [
                    'sessionpast',
                    'sessionongoing',
                ]
            ],
            'All except ongoing sessions' => [
                [
                    'session_date:status_operator' => select::NOT_EQUAL_TO,
                    'session_date:status_value' => 2,
                ], [
                    'sessionpast',
                    'sessionfuture',
                ]
            ],
            'All except past sessions' => [
                [
                    'session_date:status_operator' => select::NOT_EQUAL_TO,
                    'session_date:status_value' => 3,
                ], [
                    'sessionongoing',
                    'sessionfuture',
                ]
            ],
        ];
    }

    /**
     * Test session filters
     *
     * @param array $filtervalues
     * @param array $expectedsessions
     *
     * @dataProvider sessions_filters_provider
     */
    public function test_sessions_filters(array $filtervalues, array $expectedsessions): void {
        $this->resetAfterTest();
        $appointmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_appointment');

        $course = $this->getDataGenerator()->create_course();
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);

        // Create past session.
        $datepast = new stdClass();
        $datepast->timestart = strtotime('-2 hour');
        $datepast->timefinish = strtotime('-1 hour');
        $sessionpast = $appointmentgenerator->create_session(['appointment' => $appointment->id], [], [$datepast]);

        // Create ongoing session.
        $dateongoing = new stdClass();
        $dateongoing->timestart = strtotime('-1 hour');
        $dateongoing->timefinish = strtotime('+1 hour');
        $sessionongoing = $appointmentgenerator->create_session(['appointment' => $appointment->id], [], [$dateongoing]);

        // Create future session.
        $datefuture = new stdClass();
        $datefuture->timestart = strtotime('+1 hour');
        $datefuture->timefinish = strtotime('+2 hour');
        $sessionfuture = $appointmentgenerator->create_session(['appointment' => $appointment->id], [], [$datefuture]);

        // Build array with expected session ids.
        $allsessionids = [
            'sessionpast' => $sessionpast->id,
            'sessionongoing' => $sessionongoing->id,
            'sessionfuture' => $sessionfuture->id,
        ];

        $expectedsessionids = [];
        foreach ($expectedsessions as $expectedsession) {
            $expectedsessionids[] = $allsessionids[$expectedsession];
        }

        // Create report containing single column, and given filter.
        $report = \core_reportbuilder\manager::create_report_persistent((object) [
            'type' => sessions::TYPE_SYSTEM_REPORT,
            'source' => sessions::class,
            'default' => 0,
        ]);

        user_filter_manager::set($report->get('id'), $filtervalues);
        $rows = testable_system_report_table::create($report->get('id'), [
            'appointmentid' => $appointment->id,
        ])->get_table_rows();

        // Extract session ids from results.
        $sessionids = [];
        foreach ($rows as $row) {
            preg_match('|data-sessionid="(\d+)"|m', $row['datestart'], $matches);
            $sessionids[] = $matches[1];
        }

        // Assert values.
        $this->assertEqualsCanonicalizing($expectedsessionids, $sessionids);
    }
}
