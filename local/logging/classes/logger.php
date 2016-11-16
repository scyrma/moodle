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

use Monolog\Formatter\LineFormatter;
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
            $loggers[$channel] = new \Monolog\Logger($channel);

            $handler = new FluentHandler();

            if ($channel === 'exceptions') {
                // Provide stacktraces with exceptions.
                $formatter = new LineFormatter();
                $formatter->includeStacktraces();
                $handler->setFormatter($formatter);
            }

            $loggers[$channel]->pushHandler($handler);
        }

        return $loggers[$channel];
    }

    /**
     * Log the item.
     *
     * @param string $eventname The name of the event to log.
     * @param object $eventdata The event data.
     * @param string $channel The name of the channel to log to.
     * @param int $loglevel The Monolog log level constant.
     */
    public static function log($eventname, $eventdata, $channel = null, $loglevel = null) {
        global $_SERVER, $USER, $CFG;

        if ($logger = self::get_logger($channel)) {
            if (!isset($eventdata['userid'])) {
               $eventdata['userid'] = isset($USER->id) ? $USER->id : null;
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

            if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
                $eventdata['type'] = 'CLI';
            } else if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
                $eventdata['type'] = 'AJAX';
            } else if (isset($_SERVER) && isset($_SERVER['SERVER_ADDR'])) {
                $eventdata['type'] = 'HTTP';
            } else {
                $eventdata['type'] = 'Unknown';
            }

            if (function_exists('getremoteaddr')) {
                // Add the IP address of the client.
                $eventdata['ipaddress'] = getremoteaddr();
            }

            if (isset($CFG->moodlecloudversion)) {
                $eventdata['moodlecloudversion'] = $CFG->moodlecloudversion;
            }
            $eventdata['wwwroot'] = $CFG->wwwroot;

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
