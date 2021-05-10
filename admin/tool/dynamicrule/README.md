# moodle-tool_dynamicrule

The dynamic rules feature allows you to create **“if this then that”** rules
based on one or more user conditions to execute the selected actions. Each plug-in
can implement its own conditions and actions to be used in any dynamic rule.

Other Workplace components such as Programs or Certifications are using
dynamic rules to automate actions, like issuing badges or certificates, or
granting competencies. Those type of rules are reffered as "component rules",
they are not listed in Dynamic Rules interface, but may present at component
specific interface, e.g. in a a tab in Program settings.

Adding Dynamic rule conditions or actions is simple. One needs to create an
instance of respective class in the
`classes/tool_dynamicrule/[condition|outcome]/` plugin directory, and the
condition (or action) will become available in the system.

## Defining conditions in your plugin

To add a new condition create a class extending ```\tool_dynamicrule\condition_sql``` and place it
in the directory ```plugindir/classes/tool_dynamicrule/condition/```.

Please refer to existing conditions for examples.

## Defining actions in a plugin

In Dynamic rules code actions are called "outcomes". To add a new action create a class extending ```\tool_dynamicrule\outcome_base``` and place it
in the directory ```plugindir/classes/tool_dynamicrule/outcome/```.

Please refer to existing actions for examples.

## Note on permissions

It is important to define user permissions to use certain condition or action,
methods `user_can_add` and `user_can_edit` are designed for that. For the
conditions those permissions should represent user's ability to view/list
users who match the condition. While for action, they should reflect user's
ability to do something with selected users. For example for cohort condition
we check `moodle/cohort:view`, for cohort action we check `moodle/cohort:assign`.
The actual rule processing is performed by
system, so capabilities are ignored, therefore one should not allow user to
configure rule in a way to allow possible privelege escalation (e.g. user
can't allocate user to cohort directly, but might be able to do so via rule
action).
