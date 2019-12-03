<?php

namespace RichardGomer\todosync;

/**
 * The core syncer logic
 */
class Syncer {

    const METASRCFIELD = 'xssrc';
    const METAIDFIELD = 'xsid';

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
    public function getAll() {
        $all = array();

        foreach($this->sources as $srcid => $s) {

            echo "Fetch tasks from source $srcid\n";

            $tasks = $s->getAll();
            if(!is_array($tasks)) {
                echo "  [ WARN ]  The source did not return an array\n";
                continue;
            }

            $added = 0;
            foreach($tasks as $t) {

                if(!$t instanceof Task) {
                    echo "  [ WARN ]  The source returned something that isn't a Task object\n";
                    continue;
                }

                // Set the source ID metadata on the task
                $t->setMetadata(array_merge($t->getMetadata(), array(self::METASRCFIELD => $srcid)));

                // Check that the task has been assigned an ID by the source
                if(!array_key_exists(self::METAIDFIELD, $t->getMetadata())) {
                    echo "  [ WARN ]  The source returned a task without an ID, it has been discarded\n";
                    continue;
                }

                $added++;
                $all[] = $t;
            }

            echo "  [ DONE ]  ".$added." tasks added\n";
        }

        return $all;
    }

    /**
     * Given a set of tasks, push them out to the appropriate sources
     * That means either the source they came from originally - or, in the case
     * of new tasks, to the source specified in the new task's metadata or the
     * default source
     */
    public function push($tasks) {

        foreach($tasks as $i=>$t) {
            try {
                echo "  Push task $i   ";
                $this->pushTask($t);
                echo "    [  OK  ]\n    {$t->getRaw()}\n";
            } catch (\Exception $e) {
                echo "    [FAILED]   {$e->getMessage()}\n    {$t->getRaw()}\n";
            }
        }
    }

    protected function pushTask(Task $t) {
        // Find the source
        $md = $t->getMetadata();

        if(!array_key_exists(self::METASRCFIELD, $md)) {

            //echo "  + New task, default source\n";

            if(!$this->getDefault()) {
                throw new \Exception("There's no default source, new items can't be added");
            }

            $this->getDefault()->create($t);
            return;
        }

        $sid = $md[self::METASRCFIELD];
        $source = $this->getSource($sid);

        if (!array_key_exists(self::METAIDFIELD, $md)) {

            //echo "  + New task, source $sid\n";

            if(!$source->canCreate()) {
                throw new \Exception("Cannot create new tasks in source '$sid'");
            }
            $source->create($t);
        } else {

            $tid = $md[self::METAIDFIELD];
            //echo "  + task $tid, source $sid\n";

            if(!$source->canUpdate()) {
                throw new \Exception("Cannot update tasks in source '$sid'");
            }

            $source->update($tid, $t);
        }
    }

}
