<?php

namespace RichardGomer\todosync;

use Psr\Log;

/**
 * The core syncer logic
 */
class Syncer implements Log\LoggerAwareInterface  {

    const METASRCFIELD = 'xssrc';
    const METAIDFIELD = 'xsid';

    protected $log;
    public function setLogger(Log\LoggerInterface $log) {
        $this->log = $log;
    }


    private $default;
    public function setDefaultSource(Source $source) {

        if(!$source->CanCreate()){
            throw new \Exception("The default source must be able to create new tasks, supplied source reports that it can't");
        }

        if(!in_array($source, $this->sources)) {
            throw new \Exception("A source must be bound to the Syncer before it can be made the default source");
        }

        $this->default = $source;
    }

    public function addSource($id, Source $s) {
        $this->sources[$id] = $s;
    }

    private $sources = array();
    public function getSources() {
        return $this->sources;
    }

    public function getSource($id) {
        if(!array_key_exists($id, $this->sources)) {
            throw new \Exception("Source '$id' is not defined");
        }

        return $this->sources[$id];
    }

    public function getDefault() {
        return $this->default;
    }

    /**
     * Get all tasks
     */
    public function getAllTasks() {
        $all = array();

        foreach($this->sources as $srcid => $s) {

            $this->log->info("Fetch tasks from source $srcid");

            $tasks = $s->getAll();
            if(!is_array($tasks)) {
                $this->log->warn("The source '$srcid' did not return an array");
                continue;
            }

            $added = 0;
            foreach($tasks as $t) {

                if(!$t instanceof Task) {
                    $this->log->warn("The source $srcid returned something that isn't a Task object");
                    continue;
                }

                // Set the source ID metadata on the task
                $t->setMetadata(array_merge($t->getMetadata(), array(self::METASRCFIELD => $srcid)));

                // Check that the task has been assigned an ID by the source
                if(!array_key_exists(self::METAIDFIELD, $t->getMetadata())) {
                    $this->log->warn("The source '$srcid' returned a task without an ID, it has been discarded");
                    continue;
                }

                $added++;
                $all[] = $t;
            }

            $this->log->info("Got $added tasks from '$srcid'");
        }

        return $all;
    }

    /**
     * Given a set of tasks, push them out to the appropriate sources
     * That means either the source they came from originally - or, in the case
     * of new tasks, to the source specified in the new task's metadata or the
     * default source
     */
    public function pushToSources($tasks) {
        foreach($tasks as $i=>$t) {
                $this->pushTaskToSource($t);
        }
    }

    protected function pushTaskToSource(Task $t) {
        // Find the source
        $md = $t->getMetadata();

        if(!array_key_exists(self::METASRCFIELD, $md)) {

            $this->log->notice("Task has no source ID, creating it in the default source");

            if(!$this->getDefault()) {
                $this->log->warn("There's no default source, new items can't be added");
                return;
            }

            $this->getDefault()->create($t);
            return;
        }

        $sid = $md[self::METASRCFIELD];
        $source = $this->getSource($sid);

        if (!array_key_exists(self::METAIDFIELD, $md)) {

            $this->log->notice("Task has source ID, but no task ID, creating a new task in '$sid'");

            if(!$source->canCreate()) {
                $this->log->warn("Tasks cannot be created in source '$sid'");
                return;
            }
            $source->create($t);
        } else {

            $tid = $md[self::METAIDFIELD];

            echo "  + task $tid, source $sid\n";

            if(!$source->canUpdate()) {
                $this->log->warning("Tasks cannot be updated in source '$sid'");
                return;
            }

            $source->update($tid, $t);
        }
    }

}
