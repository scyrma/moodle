<?php declare(strict_types=1);

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use tool_fileslist\external\files_exporter;
use tool_fileslist\container;
use external_api;
use stdClass;

final class tool_fileslist_external extends external_api {
    public static function get_files_list() {
        global $PAGE;

        self::validate_context(\context_system::instance());

        return (
            new files_exporter(container::get_file_repository()->get_valid_files())
        )->export($PAGE->get_renderer('core'));
    }

    protected static function get_files_list_returns() {
        return files_exporter::get_read_structure();
    }

    protected static function get_files_list_parameters() {
        return new external_function_parameters([]);
    }
}