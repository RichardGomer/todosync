<?php

namespace RichardGomer\todosync;

// Load composer libraries
require 'vendor/autoload.php';

// Load our local libraries
require 'lib/Task.class.php';
require 'lib/TodoTxtFile.class.php';
require 'lib/TodoTxtMetadataFormatter.class.php';
require 'lib/Syncer.class.php';
require 'lib/TodoTxtSyncer.class.php';
require 'lib/Source.interface.php';

require 'lib/sources/TodoTxtSource.class.php';
require 'lib/sources/KololaSource.class.php';


require 'lib/loadconfig.php';



// Create the syncer
$syncer = new TodoTxtSyncer($conf['todofile'], $conf['donefile']);

// Add configured sources
foreach($conf['sources'] as $s) {
    if(!array_key_exists('id', $s) || !array_key_exists('type', $s)) {
        echo "All sources must have an id and type\n";
        exit;
    }

    $class = 'RichardGomer\todosync\\'.$s['type'];
    if(!class_exists($class) || !in_array('RichardGomer\todosync\Source', $imps = class_implements($class))) {
        echo "Source type {$s['type']} is not defined\n";
        print_r($imps);
        exit;
    }

    $opts = array_key_exists('options', $s) ? $s['options'] : array();

    $source = $class::factory($opts);
    $syncer->addSource($s['id'], $source);

    if(array_key_exists('default', $s) && $s['default'] == true) {
        $syncer->setDefaultSource($source);
    }
}

echo date('H:i:s ')."Sync daemon started\n";
echo "Tasks are in ".getcwd()."/todo.txt\n";

// TODO: Probably want to implement process control stuff to catch signals
// and exit gracefully

// Run the syncer
$syncer->run();
