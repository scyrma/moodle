# Changelog

## Unreleased
### Changed
- `job_time_and_tenant_select` now uses \tool_tenant\hierarchy::filter_own_or_sub_entities_sql() method to check the tenant in order to also include jobs from the tenant hierarchy (i.e. the Shared space).
