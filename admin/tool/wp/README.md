Components
==========

Icons
-----

See pix/ dir for icons - archive, restorearchived, etc

User selector
-------------

Add to the form:

    $options = array(
        'ajax' => 'tool_wp/form-potential-user-selector',
        'multiple' => true,
        'data-component' => '{PLUGINNAME}',
        'data-area' => '{AREA}',
        'data-itemid' => {ITEMID}
    );
    $mform->addElement('autocomplete', '{ELEMENTNAME}', get_string(...), [], $options);

Define callback in lib.php

    function {PLUGINNAME}_potential_users_selector(string $area, int $itemid): array {
        require_capability(...); // Always validate access!
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u');
        // ... additional user queries, for example, exclude users who are already selected ...
        return [$join, $where, $params];
    }

Tabs
----

Create one class per tab that should extend tool_wp\output\tab . These classes will define
tab label, template name, access check, as well as export_for_template() method.

Display tabs:

    $attributes = ['{ATTRIBUTE}' => '{VALUE}']; // Any additional data you need, for example, current entity id.
    $tabsoutput = new \tool_wp\output\tabs($attributes);
    $tabsoutput->add_tab(new TABCLASSNAME($attributes)); // Repeat for all tabs.
    echo $OUTPUT->render_from_template('tool_wp/tabs', $tabsoutput->export_for_template($OUTPUT));

The attributes are stored as data- properties on the &lt;div class="wptabs"&gt; element and can be changed in javascript

Example of the template with a heading that sets an action for the "Add" button and loads a tab:

    {{> tool_wp/tab_heading }}
    <!-- more content goes here -->
    {{#js}}
        require(['tool_wp/tabs'], function(Tabs) {
            Tabs.addButtonOnClick(function(e) {
                e.preventDefault();
                // Load content in the current tab, pass additional arguments.
                Tabs.loadTab(null, {action: "addform"});
            });
        });
    {{/js}}

Template tool_ws/simple_tab can be used for simple contents such as form or report.

Relevant behat step:

    When I click on "Schedule" "tool_wp > Tab"
    And I should see "Rule1" in the "Archived" "tool_wp > Tab content"
    And "Users" "tool_wp > Active tab" should exist

The last example is verifying the current active tab (supposed to be "Users"
in this case). This is useful when workflow brings you to certain tab at some
step and you want to check it is the one you expected.

Modal form
----------

Define the form extending \tool_wp\modal_form, for example:

    namespace pluginname;
    class formname extends \tool_wp\modal_form { ... }

Example of usage in javascript:

    require(['tool_wp/modal_form'], function(ModalForm) {
        $(selector).on('click', function(e) {
            var modal = new ModalForm({
                formClass: 'pluginname\\formname',
                args: {entityid: entityid},
                modalConfig: {title: Str.get_string('editentity', 'pluginname')},
                triggerElement: $(e.currentTarget)
            });
            // If necessary extend functionality by overriding class methods, for example:
            modal.onSubmitSuccess = function(response) { ... };
        });
    });

Relevant behat step:

    I press "Submit" in the modal form dialogue
    I open the autocomplete suggestions list in the "Modal form name" "dialogue"

Form inside a tab
-----------------

Define the form extending \tool_wp\modal_form

Create a tab class extending \tool_wp\output\tab_form and referencing your form.

The data you pass in tab constructor will be passed to form constructor as
$ajaxformdata parameter on tab rendering.

Example of a tab template:

    {{> tool_wp/tab_heading }}
    {{> tool_wp/tab_form }}
    {{#js}}
        require(['tool_wp/tabs'], function(Tabs) {
            Tabs.initForm(function(data) {
                // Implement what to do on form submission, for example, load the next tab.
            });
        });
    {{/js}}

Form with AJAX submission
-------------------------

Define the form extending \tool_wp\modal_form

In the DOM each form has to be placed in a container element that has no other contents except for the form.
The form itself can be either rendered together with the page (this is what happens on tab_form) or loaded in
AJAX request later.

Example of usage in javascript:

    require(['tool_wp/ajax_form'], function(AjaxForm) {
        // Initialise the form with class name \myplugin\myform to be displayed in the '#container' element.
        var form = new AjaxForm($('#container'), 'myplugin\myform');
        // To load the form (if needed):
        form.load(args);
        // To specify what to do when form is submitted and both client- and server-side validation succeeded:
        form.onSubmitSuccess = function(data) { ... };
        // To specify what to do when form is cancelled:
        form.onCancel = function(data) { ... };
    });


Tree displayed as a table
-------------------------

Create a class extending \tool_wp\output\table_tree

Example of a template:

    {{> tool_wp/table_tree }}

Relevant behat step:

    I click on "Edit" "link" in the "Node name" "tool_wp > Table tree node"

Notifications
-------------

The *tool_wp/notification* AMD module is a wrapper around the *core/notification* module and should be
used to display notification dialogs to users.

Example of usage in Javascript:

    require(['tool_wp/notification'], function(WpNotification) {
        WpNotification.addNotification({
            type: 'success',
            message: 'Good morning'
        }); 
    });

Example of usage in PHP:

    $notification = (object) [
        'type' => 'success',
        'message' => 'Good morning',
    ];

    $PAGE->requires->js_call_amd('tool_wp/notification', 'addNotification', [$notification]);

Processing
----------

The *tool_wp/processing* AMD module can be used to show a spinner icon over a container.

Example of usage in Javascript

    <div class="somecontainer">
        <div>Content</div>
    </div>
    {{js}}
    require(['tool_wp/processing', 'tool_wp/events', 'core/pubsub'], function(Processing, WpEvents, pubSub) {
        Processing.init('.somecontainer');

        // Show a overlay over the container with a spinner icon.
        pubSub.publish(WpEvents.LOADER_START);

        // Remove the overlay.
        pubSub.publish(WpEvents.LOADER_STOP);
    });
    {{/js}}


Content with header
-------------------

Use template *tool_wp/content_with_header* and the exporter *tool_wp\output\content_with_header* to create
pages that have header, "+" button and content (usually form or system report). Javascript can be added to
such pages using *$PAGE->requires->js_call_amd()*

Admin setting page with an access check callback (instead of capabilities)
--------------------------------------------------------------------------

Pass callable methods instead of list of capabilitis to an admin_externalpage()

    $ADMIN->add('courses', new \tool_wp\admin_externalpage('programs', get_string('programs', 'tool_program'),
        $url, [\tool_program\permission::class, 'check_access']));

Button to use in page heading
-----------------------------

Add a button to the page heading with the possibility to attach JS to it

    $edit = new \tool_wp\output\page_header_button(get_string('editreportdetails', 'tool_reportbuilder'),
        ['data-action' => 'editdetais', 'data-id' => $reportid]);
    $PAGE->set_button($edit->render($OUTPUT) . $PAGE->button);

Copy to clipboard icon
----------------------

    echo $OUTPUT->render_from_template('tool_wp/copy_to_clipboard', ['text' => 'Text to copy']);

This will also load javascript, however if used in a modal form you may need to override the render() function and call:

    $PAGE->requires->js_call_amd('tool_wp/copy_to_clipboard', 'init');
