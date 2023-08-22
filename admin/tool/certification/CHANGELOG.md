# Changelog

## 4.2 - 2023-05-30
### Changed
- These deprecated methods have been removed:
  - api::send_certification_user_allocated_notification()
  - api::translate_relativedate_string()
  - create_tenant_and_user() in the tests generator class
- The deprecated class local/helpers/tags.php has been removed
- The deprecated observers on_certification_completed and user_allocation_deleted have been removed
- The method condition_base::get_allocation_duedate has been deprecated. Please use
  condition_base::get_certification_user_duedate() function instead.
- File admin/tool/certification/progress.php has been replaced with:
    - admin/tool/certification/certification_report.php - report for an individual certification

  File admin/tool/certification/report.php has been refactored and split into several reports:
    - admin/tool/certification/user_report.php - certifications report for an individual user
    - admin/tool/certification/certification_report.php - report for an individual certification
    - admin/tool/certification/report.php - report for all users and certifications

  The redirects have been added to the old files to redirect to the new ones.

## 4.1 - 2023-01-11
### Changed
- `api::deallocate_users_after_grace_period_end` and `api::reallocate_user_into_initial_program` have a new argument
   $rollbackinitialcertdates that indicates if users need to be reallocated to initial program after
   recertification period end even with the original allocation dates.
- `api::get_user_allocation_status` return array now contains 'status' and 'stringid' only.
- The following deprecated Behat steps have now been removed:
  * `the following tool certification data "X" exist`
  * `the following users allocations to certifications exist`

## 3.11 - 2021-06-08
### Changed
- `api::send_certification_user_allocated_notification` is deprecated. Please use
  api::send_certification_user_allocation_created_notification function instead.
- `api::translate_relativedate_string` is deprecated. Please use
  tool_wp\local\helpers\string_helper::translate_relativedate_string() function
  instead.

## Previous versions
Changelog was not maintained before version 3.11
