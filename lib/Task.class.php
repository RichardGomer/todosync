<?php

namespace RichardGomer\todosync;

use \Gravitask\Task\TaskItem;

/**
 * We extend TaskItem in order to give the flexibility to replace the Task
 * implementation, or add custom behaviour, in future
 */
class Task extends TaskItem {

    public static function convert(TaskItem $t, $raw=null) {
        $nt = new Task();

        $nt->setRaw($raw);

        $nt->setContexts($t->getContexts());
        $nt->setProjects($t->getProjects());
        $nt->setCreationDate($t->getCreationDate());
        $nt->setCompletionDate($t->getCompletionDate());
        $nt->setPriority($t->getPriority());
        $nt->setStatus($t->getStatus());

        // The underlying library doesn't parse arbitrary metadata, but we want to
        $string = $t->getTask();
        $p = '/([^\s:]+):([^\s]+)/i'; //Metadata regex
        preg_match_all($p, $string, $matches);
        $md = $t->getMetadata();
        if(array_key_exists(1, $matches)) {
            foreach($matches[1] as $i=>$k){
                $md[$k] = $matches[2][$i];
            }
        }
        $nt->setMetadata($md);

        $nt->setTask($task = preg_replace($p, '', $string));

        return $nt;
    }


    private $raw = null;
    public function getRaw() {
        return $this->raw;
    }

    protected function setRaw($raw) {
        $this->raw = $raw;
    }

    public function hasMetadata($k) {
        return array_key_Exists($k, $this->getMetadata());
    }

}
