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
        global $CFG;
        if (!in_array(get_class($task), $CFG->moodlecloud_blocked_task_logs ?? [])) {
            parent::store_log_for_task($task, $logpath, $failed, $dbreads, $dbwrites, $timestart, $timeend);
        }
    }
}
