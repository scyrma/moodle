# Changelog

## 4.2 - 2023-05-30
### Changed
- The following deprecated methods have been removed:
  - api::get_certifications_by_userid
  - api::get_programs_tree_progress
  - api::get_user_accessible_courses
  - api::get_user_courses_completion
  - api::get_user_courses_progress
  - api::get_last_course_access
  - api::get_all_user_course_startdates
  - api::send_program_user_allocated_notification
- The following deprecated classes have been removed:
  - my_programs_progress_exporter
  - my_program_progress_exporter
  - program_set_progress_exporter
  - program_course_progress_exporter
  - program_certification_exporter
  - program_overview_course_exporter
  - program_user_exporter
  - file_exporter
- The deprecated Web Services get_user_programs and get_user_learning_statuses have been removed
- The deprecated class local/helpers/tags.php has been removed
- The deprecated observers on_program_completed and user_allocation_deleted have been removed
- Files admin/tool/program/programsprogress.php and admin/tool/program/usersprograms.php have
  been replaced with the following files:
    - admin/tool/program/user_report.php - programs report for an individual user
    - admin/tool/program/program_report.php - report for an individual program
    - admin/tool/program/report.php - report for all users and programs

  The redirects have been added to the old files to redirect to the new ones.

## 4.1 - 2023-01-11
### Changed
- `api::get_user_allocation_statuses` return array now contains 'status' and 'stringid' only.
- The following deprecated Behat steps have now been removed:
  * `the following tool program data "X" exist`
  * `the following users allocations to programs exist`
  * `the following tool program user allocations are completed`
  * `the following program courses exist`
- The following unused templates have been removed:
  - programs_overview_view
  - programs_overview_recursive_view
  - programs_overview_view_program
  - programs_overview_view_course
  - programs_overview_set_view
  - programs_overview_course_view
  - programs_overview_view_course_info
  - programs_overview_view_course_name
  - programs_overview_view_program_info
  - programs_overview_view_progress_bar
  - programs_overview_view_progress_pie
  - programs_overview_view_progress_doughnut
- The following renderable classes have been removed:
  - programs_overview_view
  - program_progress_overview
- The following exporter classes have been removed:
  - program_overview_view_exporter
  - program_overview_program_exporter
  - program_tree_progress_exporter
- The following exporter classes have been deprecated:
  - my_programs_progress_exporter
  - my_program_progress_exporter
  - program_set_progress_exporter
  - program_course_progress_exporter
  - program_certification_exporter
  - program_overview_course_exporter
  - program_user_exporter
  - file_exporter
- The following api methods have been deprecated:
  - get_certifications_by_userid
  - get_programs_tree_progress
  - get_user_accessible_courses
  - get_user_courses_completion
  - get_user_courses_progress
  - get_last_course_access
  - get_all_user_course_startdates
- `tool_program_get_user_programs` web service function has been deprecated
- Web service `tool_program_get_user_learning_statuses` is deprecated and moved to `block_myteams`

## 4.0 - 2022-10-14
### Changed
- `api::get_programs_with_criteria_conditions` now uses completeddate instead of timecreated to query
   for programs completed. The method `program_tree_progress::save_set_as_completed()` always inserts 
   completeddate=time(), which means that completeddate will always be the same as timecreated, but
   in some edge cases it could differ maybe about 1 second.

## 3.11 - 2021-06-08
### Changed
- `api::send_program_user_allocated_notification` is deprecated. Please use 
  api::send_program_user_allocation_created_notification function instead.

## Previous versions
Changelog was not maintained before version 3.11
