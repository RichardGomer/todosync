<?php

namespace RichardGomer\todosync;
use Diff\Diff;

class TodoTxtSyncer extends Syncer {

    public function __construct($todofilename, $donefilename) {

        // Backup the existing todo file
        if(false && file_exists($todofilename)) {
            if(!copy($todofilename, $bfn = $todofilename.'.'.time())) {
                throw new \Exception("$todofilename already exists and could not be backed up");
            }

            echo "$todofilename already exists, it has been backed up to $bfn\n";
        } else {
            file_put_contents($todofilename, "");
        }

        $this->todofilename = $todofilename;
        $this->donefilename = $donefilename;

        $this->todofile = new TodoTxtFile(fopen($todofilename, 'r+'));
        $this->donefile = new TodoTxtFile(fopen($donefilename, 'r'));
    }

    /**
     * Reload tasks from the sources and update the target file
     */
    public function updateFile($file) {
        $tasks = $this->getAll();
        $file->write($tasks);
    }

    private function handleChanges($inotify, $filename, $file) {

        // Do non-blocking checks for file changes
        $events = inotify_read($inotify);

        if($events !== false && count($events) > 0) {

            $moved = false;
            $changed = false;

            foreach($events as $e) {
                $moved = $moved || (($e['mask'] & IN_MOVE_SELF) > 0);
                $changed = $changed || (($e['mask'] & IN_CLOSE_WRITE) > 0);
            }

            if($moved) {
                echo date('H:i:s ').\basename($filename)." has been recreated; syncing contents & updating handle\n";

                // Sync changes from the old file before closing it
                $tasks = $file->readChanges();
                $this->push($tasks);

                $old = $file->getFile();
                $file->setFile(fopen($filename, 'r+'));
                fclose($old);

                // Update watchers
                $i_write = inotify_add_watch($inotify, $filename, IN_CLOSE_WRITE | IN_MOVE_SELF);
            }

            if($changed) {
                echo date('H:i:s')." Changes detected\n";
                $tasks = $file->readChanges();
                $this->push($tasks);
            }

            inotify_read($inotify); // Discard any events we triggered ourselves!
        }
    }

    public function run() {

        // Write the todo file
        $lastupdate = time();
        $this->updateFile($this->todofile);

        $in_todo = inotify_init();
        $i_write = inotify_add_watch($in_todo, $this->todofilename, IN_CLOSE_WRITE | IN_MOVE_SELF);
        stream_set_blocking($in_todo, 0);

        $in_done = inotify_init();
        $i_write = inotify_add_watch($in_done, $this->donefilename, IN_CLOSE_WRITE | IN_MOVE_SELF);
        stream_set_blocking($in_done, 0);


        while(true) {

            usleep(100000); // Sleep for 100ms

            $this->handleChanges($in_done, $this->donefilename, $this->donefile);
            $this->handleChanges($in_todo, $this->todofilename, $this->todofile);

            // Periodically update the local file with remote changes
            if(time() - $lastupdate > 120) {
                echo date('H:i:s')." Syncing remote changes\n";
                $this->updateFile($this->todofile);
                $lastupdate = time();
            }

        }


    }

}
