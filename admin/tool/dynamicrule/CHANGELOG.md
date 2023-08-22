# Changelog

## 4.1.5 Rolling - 2023-08-22
### Added
- Added outcome callback `is_scheduled_task()` which is designed to
  indicate that rule needs to be processed as scheduled task on cron regardless
  of rule conditions event related configuration. This is useful for slow
  performance actions as well as for the cases where admin permissions are
  required to execute action.

## 4.1 - 2023-01-11
### Changed
- Deprecated functions \tool_dynamicrule\api::generate_alias(), generate_param_name() and
  check_condition_sql(), please use functions from \core_reportbuilder\local\helpers\database instead

## 4.0 - 2022-10-14
### Changed
- The following filter types have been replaced with versions compatible with core_reportbuilder:
  `tool_dynamicrule\tool_reportbuilder\filter\condition` -> `tool_dynamicrule\reportbuilder\local\filters\condition`
  `tool_dynamicrule\tool_reportbuilder\filter\outcome` -> `tool_dynamicrule\reportbuilder\local\filters\outcome`

## 3.11 - 2021-06-08
### Changed
- `render_placeholders` in renderer is deprecated. This was used
  exclusively to pre-render notification placeholders in conditions and
  outcomes. The rendering was moved to relevant output methods, conditions and
  outcomes don't need to render placeholders.

## 3.10.1 - 2021-03-09
### Changed
- `permission::can_edit_cohort_condition` is deprecated. Necessary check is
  performed in cohort_member abstract class.

## 3.10 - 2020-12-01
### Changed
- Outcomes processing is now executed using `apply_to_user` method and
  `apply_to_users` is deprecated. Developers are advised to review the code
  and replace `apply_to_users` accordingly. If applying outcome is performance
  intensive, use `setup_for_applying` method to make necessary preparation.
  [WP-2353](https://tracker.moodle.org/browse/WP-2353)

## Previous versions
Changelog was not maintained before version 3.10
