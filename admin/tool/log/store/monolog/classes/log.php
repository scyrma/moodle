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
 * Monolog log reader/writer.
 *
 * @package    logstore_monolog
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_monolog;

require_once(dirname(__DIR__) . '/vendor/autoload.php');

use Monolog\Logger;
use Monolog\Formatter\LogglyFormatter;
use logstore_monolog\monolog\fluenthandler as FluentHandler;

defined('MOODLE_INTERNAL') || die();

class log {

    protected static function get_logger($channel = 'logstore') {
        global $CFG;

        static $loggers = array();

        if (!isset($loggers[$channel])) {
            if (!$logpath = get_config('logstore_monolog', 'logpath')) {
                // If this plugin is not configured to log somewhere, then don't bother setting it up any further.
                return null;
            }

            // Setup the logger.
            $logger[$channel] = new Logger($channel);

            $handler = new FluentHandler();
            $logger[$channel]->pushHandler($handler);
        }

        return $logger[$channel];
    }

    /**
     * Log the item.
     *
     * @param array $evententries raw event data
     */
    public static function log($eventname, $eventdata, $channel = 'logstore') {
        global $_SERVER, $USER, $CFG;

        if ($logger = self::get_logger($channel)) {
            if (!isset($eventdata['userid'])) {
                $eventdata['userid'] = $USER->id ?: null;
            }
            if (isset($_SERVER['REQUEST_URI'])) {
                $eventdata['uri'] = $_SERVER['REQUEST_URI'];
            }
            if (isset($_SERVER['SCRIPT_FILENAME'])) {
                $eventdata['script'] = $_SERVER['SCRIPT_FILENAME'];
            }
            if (isset($_SERVER['argv'])) {
                $eventdata['cliargs'] = $_SERVER['argv'];
            }

            $eventdata['moodleversion'] = $CFG->version;
            $eventdata['wwwroot'] = $CFG->wwwroot;

            // TODO add the Amazon Instance tag.
            $logger->addInfo($eventname, $eventdata);
        }
    }

    /**
     */
    public static function is_logging($channel) {
        // TODO Check which channels are to be logged.
        return true;
    }
}
