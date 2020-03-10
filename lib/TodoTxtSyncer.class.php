<?php

namespace RichardGomer\todosync;
use Diff\Diff;
use Psr\Log;

/**
 * This is the main class for todosync; it receives changes from the output files
 * and persists them back to the storage handlers
 */
class TodoTxtSyncer extends Syncer {

    public function __construct($todofilename, $donefilename, $postdir, Log\LoggerInterface $log) {

        $this->setLogger($log);

        // Backup the existing todo file
        if(false && file_exists($todofilename)) {
            if(!copy($todofilename, $bfn = $todofilename.'.'.time())) {
                throw new \Exception("$todofilename already exists and could not be backed up");
            }

            $this->log->warning("$todofilename already exists, it has been backed up to $bfn");
        } else {
            file_put_contents($todofilename, "");
        }

        if(!file_exists($donefilename)) {
            $this->log->notice("$donefilename did not exist, it has been created");
            file_put_contents($donefilename, "");
        }

        $this->todofilename = $todofilename;
        $this->donefilename = $donefilename;

        $this->log->notice("Syncing changes to/from $todofilename");
        $this->todofile = new TodoTxtFile($todofilename, $log);
        $this->todofile->setLogger($log);

        $this->log->notice("Watching $donefilename for incoming changes");
        $this->donefile = new TodoTxtFile($donefilename, $log);
        $this->donefile->setLogger($log);

        // Add a directory watcher for POST'ed updates via WebDAV
        $this->log->notice("Watching directory $postdir for incoming changes");
        $this->postedfiles = new DirectoryWatcher($postdir, $log);
    }

    /**
    * Reload tasks from the sources and update the target file
    */
    public function updateFile($file) {
        $tasks = $this->getAllTasks();
        $n = count($tasks);
        $this->log->info("Write $n tasks to output file");
        $file->write($tasks);
        $this->lastUpdate = time();
    }

    protected function updateIfStale($file) {
        if(time() - $this->lastUpdate > 120) {
            $this->log->debug("Output file is stale");
            $this->updateFile($file);
        }
    }

    protected function handleChanges(TodoTxtFile $file) {
        if($c = $file->hasChanges()) {
            $changes = $file->getChanges();
            $this->pushToSources($changes);
        }

        return $c;
    }

    public function run() {

        // Write the initial todo file with tasks from our sources
        $lastupdate = time();
        $this->updateFile($this->todofile);

        while(true) {
            usleep(100000); // Sleep for 100ms

            $changed = $this->handleChanges($this->todofile);
            $changed = $changed || $this->handleChanges($this->donefile);

            // Check for new incoming files
            if(count($files = $this->postedfiles->getFiles()) > 0) {
                $fns = array();
                foreach($files as $f) {
                    $this->log->info("Process changes from {$f->getFilename()}");
                    $this->pushToSources($f->read());
                    $fns[] = $f->getFilename();
                }

                unset($files);

                foreach($fns as $fn) {
                    if(unlink($fn)) {
                        $this->log->info("Deleted $fn");
                    } else {
                        $this->log->warn("Can't delete $fn");
                    }
                }

                $changed = true;
            }

            // If we found changes, the output file should be rewritten
            if($changed) {
                $this->log->notice("Changes were found in watched files");
                $this->updateFile($this->todofile);
            }
            // Also do regular updates to find source updates, regardless of changes to our own files
            else {
                $this->updateIfStale($this->todofile);
            }
        }
    }

}
