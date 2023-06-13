# moodle-tool_reportbuilder

Report builder tool allows **developers** to define datasources that can be used by **report creators** to configure custom reports.
Report creator then can give access to other users to view these reports.

Developer assumes that report creator is allowed to view any data **within their tenant**. It is also assumed that the report
creator gives access to view the built reports only to appropriate audience. However developer can help with permissions
checks on individual fields or actions.

Developer must ensure that **tenant check** is present for both report creator and report viewer.

Report builder tool can also be used to define and display pre-defined reports (**"System reports"**).

This is documentation **for the developers** of datasources and system reports.

# How to define datasource in a plugin

To add a new datasource that will be available as a source when creating custom reports,
create a class extending ```\tool_reportbuilder\datasource``` and place it
in the directory ```plugindir/classes/tool_reportbuilder/datasources/```.

A datasource allows a developer to build a custom SQL query and define how to format data
in the table. It is composed of the following:

* Main table
* Base joins and conditions that are always present in the SQL query
* List of columns that are available to the report creator, some columns may require additional JOINs
* List of filters and conditions that are available to the report creator
* List of entities that are used for grouping columns, filters and conditions in the report creator interface

Columns, filters and entities may require additional JOINs. The same JOIN will be only added once to the SQL query
even if there are several elements using it. JOINs will not be added if the column/condition was not added or
if a filter is not used.

# How to define a column format callback

To add custom formatting to a column's output you can call the ``add_callback()`` method
when defining the ``report_column``. You should pass a ``callable`` method as the first
parameter, following by an optional addition parameter to be passed to the callback.

# How to create a system report in a plugin

To create a system report in a plugin implement a class extending ```tool_reportbuilder\system_report```.
There are no restrictions or recommendations about the system reports namespace.
A plugin can implement any number of system reports.

Configuration of the system report is very similar to the configuration of the datasource except:
* It must have columns that are marked as default;
* If conditions are added they will be ignored;
* It must implement ``can_view()`` function to validate access to the report;
* It can have parameters

To display system report use:

```php
$report = system_report_factory::create(the_report_class::class, $parameters);
echo $report->output();

```

# How to define actions column

System reports often need a column with actions icons (edit, delete, etc.) for each row.
Any number of actions can be added using ``add_action`` method. Actions may have placeholders and individual permissions checks.
When a report has actions the column 'Actions' will be automatically displayed.

# How to define a new filter type

If common filter types (text search, date select, checkbox, dropdown) are not enough, plugis can create their own filter
types by adding classes extending ```tool_reportbuilder\filter_base```. They can be located anywhere.
