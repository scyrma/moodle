# Changelog
All notable changes to the API will be documented in this file.

## 3.10 - 2020-12-01
### Added
- function \tool_tenant\tenancy::get_users_subquery() has a new argument $withsubtenants that allows to return
users in subtenants as well as in the current tenant; it is true by default. Tenants hierarchy is not available
yet however the "Shared space" is treated as a parent tenant to all other tenants
- callbacks extend_tenant_edit_css_form, validate_tenant_edit_css_form and process_tenant_edit_css_requests added 
to extend adding css form elements to the plugin.

### Changed
- function \tool_tenant\tenancy::get_users_subquery() no longer returns guest user
-  for external methods assign_tenant_admin_roles() and unassign_tenant_admin_roles the `tenantid` param is now 
deprecated and is now calculated automatically for each given user.

## Previous versions
Changelog was not maintained before version 3.10
