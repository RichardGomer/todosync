<?php

namespace RichardGomer\todosync;

/**
 * A simple wrapper for working with todo.txt files
 */
class TodoTxtFile {

    private $fh;
    public function __construct($filehandle) {
        $this->fh = $filehandle;

        $this->lastwrite = $this->readLines();
    }

    public function __destroy() {
        fclose($this->fh);
    }

    /**
     * Replace the file handle
     * This is necessary when, ofr instance, another client moves todo.txt to
     * todo.txt~ and writes a new todo.txt
     * Replacing the filehandle rather than e-instantiating a new TodoTxtFile
     * means that change diff'ing will still work
     */
    public function setFile($fh) {

        if($this->isLocked()) {
            $locked = true;
            $this->unlock();
        }

        $this->fh = $fh;

        if($locked) {
            $this->lock();
        }
    }

    public function getFile() {
        return $this->fh;
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
    public function readChanges() {
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

            }
        }

        return $this->convert($clines);
    }

    private $lastwrite = array();
    public function write($tasks, $strip=array()) {
        $unlock = $this->lock();

        $f = new TodoTxtMetadataFormatter();
        $txt = array();
        foreach($tasks as $i=>$t) {
            $txt[] = $line = trim($f->format($t, null, $strip));
        }

        $this->lastwrite = $txt; // Cache the last value for diff'ing later on

        ftruncate($this->fh, 0);
        fseek($this->fh, 0);
        fwrite($this->fh, implode("\n", $this->lastwrite)."\n"); // A trailing newline is important! The official todo.txt CLI assumes one

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
