# Changelog

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
