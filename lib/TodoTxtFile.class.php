<?php

namespace RichardGomer\todosync;

use Psr\Log;

/**
 * A simple wrapper for working with todo.txt files
 */
class TodoTxtFile implements Log\LoggerAwareInterface {

    private $fh;
    public function __construct($filename) {

        $this->fn = $filename;
        $this->getFileHandle();
        $this->lastwrite = $this->readLines();
    }

    public function __destroy() {
        fclose($this->fh);
    }

    public function getFilename() {
        return $this->fn;
    }

    protected $log;
    public function setLogger(Log\LoggerInterface $log) {
        $this->log = $log;
    }

    /**
     * (Re)Acquire the file handle
     * And set up inotify watches for detecting changes
     */
    protected function getFileHandle() {

        $locked = false;
        if($this->isLocked()) {
            $locked = true;
            $this->unlock();
        }

        $this->fh = fopen($this->fn, 'r+');
        $this->inotify = inotify_init();
        $i_write = inotify_add_watch($this->inotify, $this->fn, IN_CLOSE_WRITE | IN_MOVE_SELF);
        stream_set_blocking($this->inotify, 0);

        if($this->fh === false) {
            throw new \Exception("Couldn't open {$this->fn}");
        }

        if($locked) {
            $this->lock();
        }
    }


    /**
     * Check for changes.
     * If changes are detected, store them in the change queue.
     * For efficiency, we use inotify to detect changes and file moves.
     * In theory, you could do it via polling (inc. checking that the file
     * hasn't been renamed!)
     */
    protected $pendingChanges = array();
    public function hasChanges() {

        // Do non-blocking checks for file changes
        $events = inotify_read($this->inotify);

        $moved = false;
        $changed = false;

        if($events !== false && count($events) > 0) {

            $this->log->debug("inotify events were found on ".\basename($this->fn));

            foreach($events as $e) {
                $this->log->debug("Event mask: ".$e['mask']);
                $moved = $moved || (($e['mask'] & IN_MOVE_SELF) > 0);
                $changed = $changed || (($e['mask'] & IN_CLOSE_WRITE) > 0);
            }
        }

        // If the file has moved, read changes then reacquire the file handle
        // on the new 'live' version
        if($moved) {
            $this->log->debug(\basename($this->fn)." has moved");
            $changes = $this->getChangedLines();
            $this->pendingChanges = array_merge($this->pendingChanges, $changes);
            $this->getFileHandle();
        }
        // If there are (just) changes, read them
        else if($changed) {
            $this->log->debug(\basename($this->fn)." has changed");
            $changes = $this->getChangedLines();
            $this->pendingChanges = array_merge($this->pendingChanges, $changes);
        }

        return count($this->pendingChanges) > 0;
    }


    /**
     * Get any outstanding changes and clear the change queue
     * You should call hasChanges() to actually poll for changes!
     */
    public function getChanges() {
        $changes = $this->pendingChanges;
        $this->pendingChanges = array();
        return $changes;
    }


    protected function convert($lines) {
        $tasks = array();
        $parser = new \Gravitask\Task\Parser\TodoTxtParser();
        foreach($lines as $l) {
            $tasks[] = Task::convert($parser->parse($l), $l);
        }
        return $tasks;
    }

    protected function readLines() {
        $lines = array();
        rewind($this->fh);
        $lines = array();
        while(($l = fgets($this->fh)) !== false) {
            $l = trim($l);

            if(strlen($l) > 0)
                $lines[] = $l;
        }
        return $lines;
    }

    // Read the whole file as Task objects
    public function read() {
        return $this->convert($this->readLines());
    }

    // Read changed lines as Task objects
    protected function getChangedLines() {
        $lines = $this->readLines();
        $last = $this->lastwrite;
        $differ = new \Diff\Differ\ListDiffer();
        $diff = $differ->doDiff($last, $lines);

        //var_dump($this->lastwrite, $lines, $diff);

        $clines = array();
        foreach($diff as $d) {
            // Adds and changes are lines that we want to return
            if($d instanceof \Diff\DiffOp\DiffOpAdd || $d instanceof \Diff\DiffOp\DiffOpChange) {
                $clines[] = $d->getNewValue();
            }

            // TODO: What to do about deleted lines?
            if($d instanceof \Diff\DiffOp\DiffOpRemove) {
                $clines [] = "";
            }
        }

        return $this->convert($clines);
    }


    /**
     * Write the given tasks to the file.
     * Existing tasks are discarded.
     */
    private $lastwrite = false;
    public function write($tasks, $strip=array()) {
        $unlock = $this->lock();

        $f = new TodoTxtMetadataFormatter();
        $txt = array();
        foreach($tasks as $i=>$t) {
            $txt[] = $line = trim($f->format($t, null, $strip));
        }

        if($this->hasChanges()) {
            throw new \Exception("Won't write() to {$this->fn} - there are outstanding changes");
        }

        // If there are no changes in the file contents, just return
        if($txt !== $this->lastwrite)
        {
            $this->lastwrite = $txt; // Cache the last value for diff'ing later on

            ftruncate($this->fh, 0);
            fseek($this->fh, 0);
            fwrite($this->fh, implode("\n", $this->lastwrite)."\n"); // A trailing newline is important! The official todo.txt CLI assumes one
        }

        if($unlock) $this->unlock();
    }

    private $locked = false;
    public function lock() {
        flock($this->fh, LOCK_EX | LOCK_NB, $wouldblock);
        if($wouldblock) {
            throw new \Exception("File cannot be locked");
        }

        $previous = $this->locked;
        $this->locked = true;

        return $previous;
    }

    public function isLocked() {
        return $this->locked;
    }

    public function unlock() {
        if($this->locked) {
            flock($this->fh, LOCK_UN);
        }
    }
}
