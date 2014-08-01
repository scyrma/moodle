MOODLE-SPECIFIC PEAR MODIFICATIONS
==================================

Auth/RADIUS
===========

1/ Changed static call to correct alternative (MDL-38373):
    - From: PEAR::loadExtension('radius'); (in global scope)
    - To: $this->loadExtension('radius'); (in constructor)

XML/Parser
=================
1/ changed ereg_ to preg_
* https://github.com/moodle/moodle/commit/695933097930bc095f3c1e22d0437139582863d3#diff-22e7ae4a1e2f08c50135a64a27628ede


Quickforms
==========
Full of our custom hacks, no way to upgrade to latest upstream.
Most probably we will stop using this library in the future.

MDL-20876 - replaced split() with explode() or preg_split() where appropriate
MDL-40267 - Moodle core_text strlen functions used for range rule rule to be utf8 safe.
