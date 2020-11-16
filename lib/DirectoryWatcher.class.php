<?php

/**
 * This class watches for files being created in a directory, and imports
 * tasks from them.
 *
 * Its primary use is to ingest changes that are pushed via WebDAV, in a
 * safer way than overwriting the main todo.txt file.
 */

namespace RichardGomer\todosync;
use Psr\Log;

class DirectoryWatcher {
    public function __construct($dirname) {
        if(!file_exists($dirname) || !is_dir($dirname)) {
            throw new \Exception("Can't watch $dirname for updates, it isn't a directory");
        }

        $this->dirname = $dirname;
    }

    /**
     * We'll only process files with names that match a filemask, by default *.txt,
     * but it can be modified using setMask()
     */
    private $fm = "*.txt";
    public function setMask($fm) {
        $this->fm = $fm;
    }

    public function getMask($fm) {
        return $this->fm;
    }

    /**
     * Look for files that match
     */
    public function getFiles() {
        $fout = array();
        $files = scandir($this->dirname);
        foreach($files as $f) {
            $ff = $this->dirname.'/'.$f;

            if(is_file($ff) && $this->match($f)) {
                $fout[] = new TodoTxtFile($ff);
            }
        }

        return $fout;
    }

    protected function match($f) {

        foreach(explode('|', $this->fm) as $m) {
            if(fnmatch($m, $f)) {
                return true;
            }
        }

        return false;
    }
}
