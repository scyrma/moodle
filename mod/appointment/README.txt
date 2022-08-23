-----------------------------------------------------------------------------
Appointment module for Moodle
Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
Copyright (C) 2014-2019 Catalyst IT (http://www.catalyst-eu.net)
Copyright (C) 2019 onwards Moodle for Workplace.

Moodle Workplace License, distribution is restricted, contact support@moodle.com
-----------------------------------------------------------------------------

Description
------------

Appointment activities are used to keep track of in-person trainings which
require advance booking.

Each activity is offered in one or more identical sessions.  These sessions
can be given over multiple days.

Reminder messages are sent to users and their managers a few days before the
session is scheduled to start.  Confirmation messages are sent when users
sign-up for a session or cancel.

Requirements
-------------

* Moodle for Workplace 3.7+

Installation
-------------

1- Unpack the module into your moodle install in order to create a
   mod/appointment directory.

2- Visit the /admin/index.php page to trigger the database installation.

3- (Optional) Change the default options in the activity modules
   configuration.

Previous maintainer(s)
-----------------------

This is a fork of [mod_facetoface](https://github.com/catalyst/moodle-mod_facetoface) maintained by Catalyst IT.

  Alastair Munro <alastair.munro@totaralms.com>
  Aaron Barnes <aaronb@catalyst.net.nz>
  Francois Marier <francois@catalyst.net.nz>
  Stacey Walker <stacey@catalyst-eu.net>

Original design and development
--------------------------------

  Jonathan Newman <jonathan.newman@catalyst.net.nz>
