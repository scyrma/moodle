<?php

require_once(__DIR__ . '/../../config.php');

$PAGE->set_url(
    '/local/moodlecloud/notifications.php'
);

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');

$PAGE->set_title("{$SITE->shortname}: " . get_string('sitenotifications', 'local_moodlecloud'));
$PAGE->set_heading(get_string('sitenotifications', 'local_moodlecloud'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlecloud/notification_area', [
    'urls' => [
        'nocourses' => $OUTPUT->image_url('courses', 'block_myoverview')->out()
    ]
]);
echo $OUTPUT->footer();
