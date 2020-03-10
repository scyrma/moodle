-----------------------------------------------------------------------------
Appointment module for Moodle
Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
Copyright (C) 2014-2019 Catalyst IT (http://www.catalyst-eu.net)
Copyright (C) 2019 onwards Moodle for Workplace.

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
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
