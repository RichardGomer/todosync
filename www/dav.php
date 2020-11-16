<?php

/**
 * Expose the todo.txt file via WebDAV
 * This is currently built on top of the actual todo.txt file - in theory it
 * could go straight into the sync'ing logic and skip that read/write step
 */

namespace TodoDav;
use Sabre\DAV;

require '../vendor/autoload.php';

require '../lib/loadconfig.php';

class TodoDir extends DAV\Collection {

    public function __construct($todopath, $donepath) {
        $this->todofile = $todopath;
        $this->donefile = $donepath;
    }

    function getChildren() {
        return array(   new TodoFile($this->todofile, 'todo.txt'),
                        new TodoFile($this->donefile, 'done.txt'));
    }

    function createFile($name, $data = null) {

        if(preg_match('/\.(tmp|tacitpart)$/', $name)) {
            $fpath = dirname(__FILE__).'/temp/'.$name;
            touch($fpath);
            chmod($fpath, 0777);
        }

        $f = $this->getChild($name);
        $f->put($data);
    }

    function getChild($name) {

        // Temp files get called .tacitpart by clients that do safe uploads
        if(preg_match('/\.(tmp|tacitpart)$/', $name)) {
            $fpath = dirname(__FILE__).'/temp/'.$name;

            if(file_exists($fpath)) {
                return new DAV\FS\File($fpath);
            } else {
                throw new DAV\Exception\NotFound("Temp file $name does not exist");
            }
        }

        if($name == 'todo.txt') {
            return new TodoFile($this->todofile, 'todo.txt');
        }
        else if($name == 'done.txt') {
            return new TodoFile($this->donefile, 'done.txt');
        }
        else {
            throw new DAV\Exception\NotFound("Invalid file name $name");
        }
    }

    function childExists($name) {
        try {
            $this->getChild($name);
            return true;
        } catch (DAV\Exception\NotFound $e) {
            return false;
        }
    }

    function getName() {
        return "todosync";
    }
}

class TodoFile extends DAV\File {

    public function __construct($path, $name) {
        $this->path = $path;
        $this->name = $name;
    }

    function getName() {
        return $this->name;
    }

    function get() {
        return fopen($this->path,'r');
    }

    function put($data) {
        file_put_contents($this->path, $data);
	chmod(0777, $this->path);
    }

    function getSize() {
        return filesize($this->path);
    }

    function getETag() {
        return '"' . md5_file($this->path) . '"';
    }

    function delete() {
        return true;
    }

    function getLastModified() {
        return filemtime($this->path);
    }

}


// Expose the todo file using our virtual filesystem
$publicDir = new TodoDir($conf['todofile'], $conf['donefile']);
$server = new DAV\Server($publicDir);

// Nice browser UI
$plugin = new DAV\Browser\Plugin();
$server->addPlugin($plugin);

// File locking
$locksBackend = new DAV\Locks\Backend\File(dirname(__FILE__).'/davlocks');
$locksPlugin = new DAV\Locks\Plugin($locksBackend);
$server->addPlugin($locksPlugin);


// Base URI assumes rewriting is configured so that all requests go to /dav.php
$server->setBaseUri('/');

// Handle the request
ob_start();
$server->exec();
sleep(1); // Time for sync process to process changes
// TODO: Wait for some kind of confirmation?
ob_end_flush();
