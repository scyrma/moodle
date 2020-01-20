# Tenants #

This plugin adds multi-tenancy feature to Moodle sites. Please note that core modifications are
required for this plugin to work and it can not be used outside of Moodle Workplace suite.

Get current user's tenant:

    \tool_tenant\tenancy::get_tenant_id()

Retrieve list of users for the current tenant:

    $ualias = \tool_wp\db::generate_alias();
    list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql($ualias);
    $sql = "SELECT {$ualias}.* FROM {user} {$ualias} " . $join . ' WHERE ' . $where;
    $DB->get_records_sql($sql, $params);

Find more useful functions in the \tool_tenant\tenancy class. It is not recommended
to call methods from other plugin's classes.
