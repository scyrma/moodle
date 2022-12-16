# tool_reportbuilder

Report builder has been added to Moodle LMS 4.0 and this Workplace version is now outdated.

Since version 4.0 `tool_reportbuilder` remains in Moodle Workplace only for the following purposes:
- Define rules how the old data sources from `tool_reportbuilder` can be converted to the `core_reportbuilder`
- Display custom reports that can not yet be converted to the `core_reportbuilder`
- Display system reports in the add-on plugins that have not been converted to the `core_reportbuilder`
- Allow "Migrations" tool to import custom reports from version 3.11 and consecutively convert them to `core_reportbuilder` reports

# Converting system report to core reportbuilder

Developer needs to create datasources, entities and filters for the core reportbuilder and use them instead.
The base classes are very similar and it should not take long. Refer to the documentation
https://docs.moodle.org/dev/Report_builder_API

# Converting custom reports to core reportbuilder

First, developers need to re-create the same data sources in the `core_reportbuilder` (basically, create classes
in the subfolder `/classes/reportbuidler/` instead of `/classes/tool_reportbuilder/` and use a different base class).

After that they need to define conversion rules for each data source from `tool_reportbuilder` by overridding functions:
- `\tool_reportbuilder\datasource::convert_get_datasource_class()`
- `\tool_reportbuilder\datasource::convert_get_entity_name()`
- `\tool_reportbuilder\datasource::convert_get_column_unique_identifier()`
- `\tool_reportbuilder\datasource::convert_get_filter_unique_identifier()`
- `\tool_reportbuilder\datasource::convert_get_condition_unique_identifier()`
- `\tool_reportbuilder\audience_base::convert_get_audience_class()`
- `\tool_reportbuilder\entity_base::convert_get_entity_class()`
- `\tool_reportbuilder\entity_base::convert_get_column_name()`
- `\tool_reportbuilder\entity_base::convert_get_filter_name()`
- `\tool_reportbuilder\entity_base::convert_get_condition_name()`
- `\tool_reportbuilder\filter_base::convert_condition_values()`

See phpdocs for each of these functions and also examples in the reports in the `/classes/tool_reportbuilder/datasources/` folder.

When mapping is defined in the code the reports can be converted by selecting "Convert" action
in the custom reports list in the "Report builder outdated version".

# Original documentation

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
