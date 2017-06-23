<?php declare(strict_types=1);

namespace tool_fileslist;

use stdClass;

final class util {
    private static $filestorage;

    public static function record_to_file(stdClass $record, file_storage $storage) : stored_file {
        return $storage->get_file(
            $record->contextid,
            $record->component,
            $record->filearea,
            $record->itemid,
            $record->filepath,
            $record->filename
        );
    }

    public static function get_files_repository() {
        global $DB;
        return new files_repository(
            $DB,
            new db_row_collection_factory()
        );
    }

    public static function get_file_storage() {
        return self::$filestorage ?? self::$filestorage = \get_file_storage();
    }
}