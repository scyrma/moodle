<?php

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
 * CLI cron
 *
 * This script looks through all the module directories for cron.php files
 * and runs them.  These files can contain cleanup functions, email functions
 * or anything that needs to be run on a regular basis.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

function are_any_locked($locks) {
    $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
    foreach ($locks as $lockname) {
        if ($lock = $cronlockfactory->get_lock($lockname, 1)) {
            $lock->release();
        } else {
            return true;
        }
    }
    return false;
}

// Build up a list of the locks.
$locks = array(
        'core_cron',
    );

$records = $DB->get_records('task_scheduled');
foreach ($records as $record) {
    $locks[] = $record->classname;
}


$records = $DB->get_records('task_adhoc');
foreach ($records as $record) {
    $locks[] = 'adhoc_' . $record->id;
}

if (are_any_locked($locks)) {
    exit(1);
}
exit(0);
