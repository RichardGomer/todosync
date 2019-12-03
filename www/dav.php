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

    public function __construct($todopath) {
        $this->todofile = $todopath;
    }

    function getChildren() {
        return array(new TodoFile($this->todofile));
    }

    function getChild($name) {
        if($name !== 'todo.txt') {
            throw new DAV\Exception\NotFound('Access denied');
        }

        return new TodoFile($this->todofile);
    }

    function childExists($name) {
        return $name == 'todo.txt';
    }

    function getName() {
        return "todosync";
    }
}

class TodoFile extends DAV\File {

    public function __construct($path) {
        $this->path = $path;
    }

    function getName() {
        return "todo.txt";
    }

    function get() {
        return fopen($this->path,'r');
    }

    function put($stream) {
        $data = stream_get_contents();
        file_put_contents($this->path, $data);
    }

    function getSize() {
        return filesize($this->path);
    }

    function getETag() {
        return '"' . md5_file($this->path) . '"';
    }

}


// Expose the todo file using our virtual filesystem
$publicDir = new TodoDir($conf['todofile']);
$server = new DAV\Server($publicDir);


$plugin = new DAV\Browser\Plugin();
$server->addPlugin($plugin);

// Base URI assumes rewriting is configured so that all requests go to /dav.php
$server->setBaseUri('/');

// Handle the request
$server->exec();
