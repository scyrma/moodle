<?php declare(strict_types=1);

namespace local_moodlecloud;

defined('MOODLE_INTERNAL') || die();

use core\task\{database_logger, task_base};

class moodlecloud_task_logger extends database_logger {
    public static function store_log_for_task(
        task_base $task,
        string $logpath,
        bool $failed,
        int $dbreads,
        int $dbwrites,
        float $timestart,
        float $timeend
    ) {
        if (strpos(get_class($task), 'moodlecloud') === false) {
            parent::store_log_for_task($task, $logpath, $failed, $dbreads, $dbwrites, $timestart, $timeend);
        }
    }
}
