<?php declare(strict_types=1);

namespace tool_fileslist\external;

use core\external\exporter;
use stdClass;
use moodle_url;

final class user_exporter extends exporter {
    private $user;

    public function __construct(stdClass $user, array $related = []) {
        $this->user = $user;

        parent::__construct(
            (object)[
                'fullname' => fullname($this->user),
                'profile' => (new moodle_url('/user/profile.php', ['id' => $this->user->id]))->out(true),
                'id' => $this->user->id
            ],
            $related
        );
    }

    protected static function define_properties() : array {
        return [
            'fullname' => ['type' => PARAM_TEXT],
            'id' => ['type' => PARAM_INT],
            'profile' => ['type' => PARAM_URL]
        ];
    }

    protected static function define_related() : array {
        return ['context' => 'context'];
    }
}