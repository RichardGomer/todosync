Todo sync
=========

An API for synchronising TODO.txt with other TODO sources.

It's common for tasks to originate and be stored across many systems - GitHub,
corporate groupware, personal todo list, Trello. This tool aims to provide
a simple framework for aggregating multiple TODO sources into the todo.txt format,
and to convert changes to todo.txt back to updates in the original sources.

Basic Operating Rules
=====================

Multiple todo sources are defined. Each source is configured to use a connector;
it should be easy to create new connectors.

A todo.txt file is created and watched by the service. That would usually be the
same file that you're accessing with the official CLI tool.

A done.txt is watched (but not updated) in order to catch task completion via the
official CLI tool.

Updates to the todo.txt file are caught and persisted
- Tasks that can be identified are updated in the original source
- Tasks that appear to be new are created in a default task source

Tasks are identified using the extended metadata that's part of the todo.txt
spec. Additional fields, xsid and xssrc (sync id, sync source) are added to the
task.

Last update always wins. There is perhaps scope to introduce locking in the
future. For now, just be careful and refresh local todo files often! That said,
it's safe to push incomplete lists because we don't use non-listedness as a 
signal to update anything (just mark the task as completed!)

The service itself is stateless and designed to be run locally.


Interfaces
==========

* **todo.txt**: The created todo.txt file is the main interface to the service; it serves as the
means of creating, reading and updating tasks.

* **done.txt**: This is an input-only file - lines from here are read and used to update task records,
but the content is never modified. This exists mostly to catch completed tasks from tools that move
them straight to done.txt

* **WebDAV**: There is also a WebDAV interface, which will serve up the two files (the todo
file and the done file, and only those two files) to WebDAV clients.  This needs
to be served via a web server, and you almost certainly want to put some HTTP
authentication in front of it to avoid sharing your todo list with the world!
Just have a PHP-enabled web server serve up the www directory, rewriting all requests
to dav.php (an .htaccess file is provided for Apache).


Connectors
==========

* `local-txt`: Another local todo.txt file. There should usually be one of these,
and it will usually be set as the default source, so that new tasks not originating
in another source can be stored somewhere.
* `kolola`: Pull tasks from a kolola.net e-portfolio. The portfolio needs evidence
requirements set up in the strategy - support (at) kolola.net can advise.


Running
=======

The sync script needs to be running to pick up changes. `run.sh` can be used to
start it, and is safe to call from a regular cronjob (it will just exit if the
script is already running). Changes that are made to todo.txt or done.txt while
the script is not running will NOT be picked up!

WebDAV is also dependent on the sync script running.


Dependencies
============

Dependencies are managed using composer. `composer install`



Apps/Scripts
====

Sync a local todo file from the WebDAV interface:

```
#!/bin/bash
CURLOPTS="-u username:password"
curl $CURLOPTS -T todo.txt 'http://todo.mydomain.net/'
curl $CURLOPTS -T done.txt 'http://todo.mydomain.net/'
sleep 1
curl $CURLOPTS http://todo.mydomain.net/todo.txt -o todo.txt
# Don't fetch done, it's only really an input route into todosync
```

Android:
* Install FolderSync
* Set up a paired folder, no auto sync
* Install Tasker
* Use tasker to run the job TWICE on a schedule. First sync pushes, second sync pulls the updated list back (i.e. with IDs on newly added tasks).
* Use Simpletask Cloudless to view/edit todo.txt


The extra attributes can be a bit messy in the command line client; set up a function 
in bashrc (or similar) to filter them out of the terminal output!

```
function td () {
    todo-txt "$@" | sed -e "s/\(xsid\|xssrc\):[a-zA-Z0-9-]\+//g"
}
```
