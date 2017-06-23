<?php declare(strict_types=1);

namespace tool_fileslist;

use core_user;
use stdClass;

final class container {
    private static $usercache = [];

    public static function get_file_repository() : file_repository {
        global $DB;
        return new file_repository(
            new db_rows($DB, 'files', 'filesize DESC'),
            new file_factory(
                get_file_storage(),
                function(int $uid) : stdClass {
                    return self::$usercache[$uid] = self::$usercache[$uid] ?? core_user::get_user($uid);
                }
            )
        );
    }
}
