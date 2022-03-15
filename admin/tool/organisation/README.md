# Organisation structure #

### User jobs and permissions ###

Examples of receiving information about user jobs and permissions:

    use tool_organisation\organisation;

1 . User jobs:

    $user = organisation::get_user_with_jobs();
    $jobs = $user->get_jobs();

2 . Does current user have manager permission XYZ anywhere:

    $user = organisation::get_user_with_jobs();
    $canallocate = $user && $user->is_manager(organisation::PERM_ALLOCATE_PROGRAMS);

3 . List of users the current user have manager permission XYZ over:

    $user = organisation::get_user_with_jobs();
    list($where, $params) = $manager->get_managed_users_select('u', organisation::PERM_VIEW_REPORTS);
    $DB->get_records_sql("SELECT * FROM {user} u WHERE $where", $params);

4 . Relationship between manager user with id $managerid and a user with id $userid:

    $manager = organisation::get_user_with_jobs($managerid);
    $user = organisation::get_user_with_jobs($userid);
    // Jobs that the $user has that make him a subordinate of $manager:
    $jobs = $user->get_relevant_jobs($manager);
    // Jobs that the manager has that make him a manager of the $user:
    $managerjobs = $manager->get_relevant_manager_jobs($user);

### List of departments or positions ###

List of departments/positions where current user has subordinates:

    $manager = organisation::get_user_with_jobs($managerid);
    // Departments space-padded to show hierarchy, groupped by framework:
    organisation::get_managed_users_relevant_departments_menu($manager);
    // List of positions without hierarchy but still groupped by framework:
    organisation::get_managed_users_positions_menu($manager);

This can be used in a report builder department filter:

    use tool_organisation\tool_reportbuilder\filter\department_select;
    $f = new report_filter(department_select::class,
        'userdepartment',
        'u',
        'u.id',
        [],
        get_string('department', 'tool_organisation')
    );
    $f->set_default(true);
    $f->set_options(organisation::get_managed_users_departments_menu($this->manager, 0,
        ['' => get_string('anydepartment', 'tool_organisation')]));
    $this->add_filter($f);

Similar way to build a position filter.

### Behat and unit tests generators ###

Behat tests to generate positions, departments and jobs (see generator.feature for examples):

    Given the following departments exist in organisation structure:
    Given the following positions exist in organisation structure:
    Given the following job assignments exist in organisation structure:

Unit tests generators:

    /** @var tool_organisation_generator $generator */
    $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    $generator->create_department([...]); // Specify tenantid or parentid.
    $generator->create_position([...]); // Specify tenantid or parentid.
    $generator->assign_job(['userid' => ..., 'positionid' => ..., 'departmentid' => ...]);
