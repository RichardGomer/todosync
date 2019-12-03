<?php

namespace RichardGomer\todosync;

use \Gravitask\Task as task;

/**
 * Format a TaskItem as a todo.txt line, including metadata fields
 * Optionally, $strip can contain a list of metadata fields to strip out
 */
class TodoTxtMetadataFormatter extends task\Formatter\TodoTxtFormatter {
    public function format(task\TaskItem $taskItem, $flags = null, $strip=array()) {
        $str = trim(parent::format($taskItem, $flags));

        // Add metadata at the end
        foreach($taskItem->getMetadata() as $k=>$v) {
            if(!in_array($k, $strip))
                $str .= " $k:$v";
        }

        return $str;
    }
}
