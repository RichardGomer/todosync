<?php

namespace RichardGomer\todosync;

/**
 * Sources are where todo items are found, and where they are pushed back to
 * when changed
 */

interface Source {

    /**
     * Different sources may have different capabilities; these methods should
     * return true or false to indicate whether updating tasks and creating
     * new tasks are supported.
     */
    public function canUpdate();
    public function canCreate();

    /**
     * Sources are created using a factory method, which is given an associative
     * array of options, as defined in sources.json (an empty array if no options)
     * were specified
     */
    public static function factory($options);

    /**
     * Get all the tasks in the source.
     * Sources may choose only to return inomcplete tasks, if that makes sense,
     * or could make that behaviour configurable
     */
    public function getAll();

    /**
     * Get a specific task by ID. Sources are responsible for interpreting their
     * own IDs, but IDs must be persistent
     */
    public function get($id);

    /**
     * The methods below can throw an exception if updates or creation are not
     * supported; but the syncer should avoid calling those methods if canCreate
     * and/or canUpdate return false, as documented above.
     */

    /**
     * Update a task identified by $id, with the details from $task
     * Sources may choose to copy all fields from $task, or attempt to determine
     * which have been updated.
     */
    public function update($id, Task $task);

    /**
     * Store the given task.
     */
    public function create(Task $task);

}
