# Changelog
All notable changes to the API will be documented in this file.

## 3.11.1 - 2021-07-20
### Changed
- Class tool_tenant\local\auth\oauth2\tenant_availability_form has been renamed to
  tool_tenant\local\auth\tenant_availability_form and it can work with different
  auth methods
- Some constants and methods were moved from the tool_tenant\local\auth\oauth2\manager
  class to the new class tool_tenant\local\auth\issuer_helper so that they can be reused
  for different authentication methods
- AMD module tool_tenant/auth_oauth2 has been moved to tool_tenant/auth and it also
  can work with different authentication methods

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
