Trappola - a modular SNMP trap handler
======================================

Trappola is a simple, modular SNMP Trap handler. It consists of three
components:

* a Trap reciever running embedded with the Net-SNMP snmptrapd daemon
* an OID/MIB lookup component
* an Icinga Web 2 module visualizing traps and issues

Installation
------------

Like any Icinga Web 2 module, please extract or clone this repository
to it's corresponding module folder, usually:

    /usr/share/icingaweb2/modules/trappola

Enable the module as usual. It expects it's config in

    /etc/icingaweb2/modules/trappola/config.ini

That file should at least point to a related DB database resource configured
through with Icinga Web 2:

```ini
[db]
resource = trappola
```

Spooling Traps
--------------

The trap reciever uses Redis to spool incoming traps. The code embedded
into snmptrapd should receive traps as fast as possible with minimal
processing involved. Redis makes a perfect fit for this use-case.

Former versions used a file-based spool, also that one used to be pretty
fast. And in theory for most small environments we could perfectly do
the whole trap processing in a blocking way. It would be fast enough. To
keep things simple we decided however to start with one way of running
Trappola for all environments. In case you have a good reason for running
Trappola without Redis or you think different about all this please let
us know. But for now, Redis is a hard requirement.

MIB/OID lookups
---------------

It often happens that new unknown traps arrive and you realize that you
didn't install the related MIB files yet. That can easily be fixed, but
your historic views are usually not cleaned up and continue to show OIDs
instead of human-readable identifiers.

Not so for Trappola. Internally, all OIDs are always stored as plain OIDs,
name lookups happens always when information is shown or rendered. This
allows you to really "fix" old traps by just installing new MIB files,
even after you recieved your first traps.

Trap processing
---------------

There are multiple trap processors available and the whole trap handling
has been implemented in a hookable way. This allows you to write your
very own processors for specific trap types.

Shipped Trap handlers
---------------------

### Trap history

This handler puts traps in a history table an makes that history available
in the web frontend.

### Oracle Enterprise Manager

A handler written for traps sent by the Oracle Enterprise Manager. It
is able to correlate Incidents based on the Oracle event ID.

Database cleanup
----------------

Acknowledged outdated events can be removed with

    icingacli trappola trap cleanup

Please add `--verbose` in case you want to have a notice on STDOUT or in
your syslog telling you how many traps have been purged:

    icingacli trappola trap cleanup --verbose

It is a good advise to run this at least once a day as a cronjob. Per default
it purges only acknowledged traps older than 6 months. You can adjust this in
your `config.ini` via the `purge_before` setting in the `db` section:

```ini
[db]
purge_before = "-3 month"
```

Future plans
------------

### MIB parser

While the current SNMP MIB lookup component works pretty well, we have
other plans for the future. We want to have a system able to deal with
nested MIB table structures. This is not possible without knowing how
your tables are defined and there is no library in the whole net-snmp
ecosystem able provide that information. At least not as far as I have
been able to figure out.

We wrote some code prototypes able to do what we want to achieve, but
there is more work to do. It will for example involve quite some DB
schema changes. That would result in a lot of changes to the Web module.
As a result of all this we could end up with a very nice web-based MIB
browser, allowing you to upload custom MIB files. By the end this should
be a component allowing for lot's of new features for active and passive
SNMP-based monitoring.

