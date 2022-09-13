# Changelog

## UNRELEASED
### Removed
- Removed AMD module `tool_organisation/modal_save_cancel_delete` and template `tool_organisation/modal_save_cancel_delete` as they are no longer used.

## 3.11.5 (2022011800)
### Changed
- `job_time_and_tenant_select` now uses \tool_tenant\hierarchy::filter_own_or_sub_entities_sql() method to check the tenant in order to also include jobs from the tenant hierarchy (i.e. the Shared space).
