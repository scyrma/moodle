# Changelog

## 4.3 - 2023-11-09
### Changed
- Deprecated external class `tool_organisation\external\unassign_manager` service `tool_organisation_unassign_manager`.
  Instead, use `tool_organisation\external\unassign_managers` service `tool_organisation_unassign_managers` which now use
  the "next generation" WS that are suitable for both AJAX and standalone execution.

## 4.2.3 Rolling - 2023-10-10
### Added
- New method `get_all_direct_managed_users()` as a helper to retrieve all direct subordinates of a user.
- Events `user_manager_created`,`user_manager_updated`,`user_manager_deleted` in order to record logs in each manually assigned manager CRUD operation.
- Class `tool_organisation\local\helpers\user_manager` as a helper of manually assigned manager processes.
- New method `is_manually_assigned_manager()` and `ismanuallyassignedmgr` property in the exporter class `user_with_jobs` to check if some user has any manually assigned manager relation.
- New method `get_user_manually_assigned_managers_sql()` in class `tool_organisation\helper` used by `organisation::get_user_all_direct_managers()` to include users that have some manually assigned manager.

### Changed
- The method `user_with_jobs::is_manager()` now checking manually assigned managers.
- The event listener `tool_organisation\observer.php` to remove all relevant records of manually assigned managers.
- The method `helper::get_users_with_jobs_sql()` now retrieve a boolean row `ismanuallyassignedmgr` used in `user_with_jobs` exporter.

## 4.2.2 Rolling - 2023-08-22
### Added
- Reporting lines are now cached. After any changes to the jobs or positions/departments
  the reindexing ad-hoc task needs to be scheduled, see:
  `tool_organisation\local\helper\reporting::schedule_reporting_line_reindex($tenantid)`
- Added support for reporting lines with mixed type. If userA is a position manager over userB
  and userB is a department lead over userC, the userA will see userC as reporting to them (not directly).

### Changed
- Deprecated methods `tool_organisation\helper::get_managed_users_select()` and
  `tool_organisation\helper::get_direct_managed_users_select()`. Instead
  use `tool_organisation\output\user_with_jobs::get_managed_users_select()` that now has
  additional parameter `$directonly` to return only direct sub-ordinates.
- Deprecated method `tool_organisation\helper::get_managed_users_with_jobs_sql()` without replacement.

## 4.2 - 2023-05-30
### Changed
- The following classes have been removed:
 * `tool_organisation\reportbuilder\local\systemreports\managed_users`
 * `tool_organisation\output\renderer`
 * `tool_organisation\output\managed_users_view`
- The following templates have been removed:
 * `tool_organisation/dashboard_team_user`
 * `tool_organisation/managed_users_view`
- The following permission has been removed:
 * `can_view_user_programs_overdue`

## 4.1 - 2023-01-11
### Changed
- The following deprecated Behat steps have now been removed:
  * `user X has a global manager position over users Y with permissions Z`
  * `user X has a department manager position over users Y with permissions Z`
- Web Services `tool_organisation_get_managed_users` and
  `tool_organisation_get_teams_tab_filters` are deprecated, they are moved
  to the `block_myteams` plugin.

## 4.0 - 2022-10-14
### Removed
- Removed AMD module `tool_organisation/modal_save_cancel_delete` and template `tool_organisation/modal_save_cancel_delete` as they are no longer used.

## 3.11.5 - 2022-01-18
### Changed
- `job_time_and_tenant_select` now uses \tool_tenant\hierarchy::filter_own_or_sub_entities_sql() method to check the tenant in order to also include jobs from the tenant hierarchy (i.e. the Shared space).
