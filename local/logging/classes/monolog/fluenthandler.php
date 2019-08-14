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
 * Fluentd Handler for Monolog.
 *
 * @package    local_logging
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_logging\monolog;

require_once(dirname(dirname(__DIR__)) . '/vendor/autoload.php');

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Fluent\Logger\FluentLogger;
use Fluent\Logger\ConsoleLogger;

defined('MOODLE_INTERNAL') || die();

class fluenthandler extends \Monolog\Handler\AbstractProcessingHandler {

    const DEFAULT_HOST = 'unix:///var/run/td-agent/moodle.sock';

    /**
     * @var string $hostip The host IP address to log to.
     */
    protected $hostip;

    /**
     * @var object $logger the Fluent Instance
     */
    protected $logger;

    /**
     * @param integer       $level          The minimum logging level at which this handler will be triggered
     * @param Boolean       $bubble         Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($level = Logger::DEBUG, $bubble = true) {
        parent::__construct($level, $bubble);
    }

    /**
     * Get the logger.
     *
     * @return FluentLogger
     */
    protected function get_logger() {
        if (!$this->logger) {
            if (!function_exists('get_config')) {
                // We may not have get_config this early in the install.
                return null;
            }

            global $CFG;
            $this->logger = new FluentLogger(
                $CFG->forced_pluggin_settings['local_logging']['host'] ?? self::DEFAULT_HOST,
                $CFG->forced_pluggin_settings['local_logging']['port'] ?? null
            );
        }

        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record) {
        $channel = $record['channel'];

        if ($logger = $this->get_logger($channel)) {
            if (isset($record['formatted'])) {
                // Remove the formatted version. We pass JSON.
                unset($record['formatted']);
            }
            $this->logger->post('moodle.' . $channel, $record);
        }
    }
}
