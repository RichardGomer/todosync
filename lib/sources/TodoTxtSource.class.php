<?php

namespace RichardGomer\todosync;

/**
 * A source that reads from a todo.txt file.
 * Allows existing todo.txt files to be aggregated with tasks from other sources,
 * and possibly makes a good default source too
 */
class TodoTxtSource implements Source {


    public static function factory($options) {
        if(!array_key_exists('filename', $options)) {
            throw new \Exception("filename must be specified for ".__CLASS__);
        }

        $filename = $options['filename'];

        if(!file_exists($filename)) {
            throw new \Exception("$filename does not exist");
        }

        return new TodoTxtSource($filename);
    }


    private $fh, $file, $filename;
    private function __construct($filename) {
        $this->fh = fopen($filename, 'r+');
        $this->filename = $filename;

        $this->file = new TodoTxtFile($this->fh);
        $this->file->lock();

        $this->load();
    }

    public function canUpdate() {
        return true;
    }

    public function canCreate() {
        return true;
    }

    /**
     * Internally, tasks are held in memory; but we can push them to disk or
     * reload them as required
     */
    private $tasks;
    protected function load() {
        $tasks = $this->file->read();

        $this->tasks = array();
        foreach($tasks as $t) {

            if(!$t->hasMetadata(Syncer::METAIDFIELD))
                $t->addMetadata(Syncer::METAIDFIELD, $id = uniqid());
            else
                $id = $t->getMetadata()[Syncer::METAIDFIELD];

            $this->tasks[$id] = $t;
        }
    }

    // Push current tasks back to the file
    protected function save() {
        $this->file->write($this->tasks, array(Syncer::METASRCFIELD)); // Strip the source field when serializing
    }

    /**
     * These methods all work on the in-memory task list
     */
    public function getAll() {

	$out = array();

	// Hide hidden tasks
	foreach($this->tasks as $t) {
	    if(!$t->isCompleted()) 
		$out[] = $t;
	}

        return $out;
    }

    public function get($id) {
        $tasks = $this->getAll();

        if(array_key_exists($id, $tasks)) {
            return $tasks[$id];
        } else {
            throw new \Exception("Task $id was not found in $this->filename");
        }
    }

    public function update($id, Task $task) {
        $this->tasks[$id] = $task;
        $this->save();
    }

    public function create(Task $task) {

	// Avoid creating duplicates - this can happen before clients pick up a fresh list with an ID assigned
	foreach($this->tasks as $t) {
		if($t->getTask() == $t->getTask())
			return;
	}

        $id = uniqid(); // Create an ID
        $task->addMetadata(Syncer::METAIDFIELD, $id);
        $this->tasks[$id] = $task;
        $this->save();
    }

}
