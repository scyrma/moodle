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
 * @package    local_logging
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_logging;

require_once(dirname(__DIR__) . '/vendor/autoload.php');

use Monolog\Formatter\LogglyFormatter;
use local_logging\monolog\fluenthandler as FluentHandler;

defined('MOODLE_INTERNAL') || die();

class logger {
    /**
     * Get the logger for the named channel.
     *
     * @param string $channel The name of the channel to log to.
     * @return Monolog\Logger
     */
    protected static function get_logger($channel = null) {
        static $loggers = array();

        if (null === $channel) {
            $channel = 'logstore';
        }

        if (!isset($loggers[$channel])) {
            // Setup the logger.
            $logger[$channel] = new \Monolog\Logger($channel);

            $handler = new FluentHandler();
            $logger[$channel]->pushHandler($handler);
        }

        return $logger[$channel];
    }

    /**
     * Log the item.
     *
     * @param string $eventname The name of the event to log.
     * @param object $eventdata The event data.
     * @param int $loglevel The Monolog log level constant.
     * @param string $channel The name of the channel to log to.
     */
    public static function log($eventname, $eventdata, $loglevel = null, $channel = null) {
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

            // TODO add the Amazon Instance tag and other associated info.

            if (!isset($loglevel)) {
                // Default to the INFO level.
                $loglevel = \Monolog\Logger::INFO;
            }

            // Log at the desired level.
            $logger->addRecord($loglevel, $eventname, $eventdata);
        }
    }

    /**
     */
    public static function is_logging($channel) {
        // TODO Check which channels are to be logged.
        return true;
    }
}
