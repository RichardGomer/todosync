<?php

namespace RichardGomer\todosync;

use JsonPath\JsonObject;

/**
 * A todosync Task Source that pulls tasks from KOLOLA e-portfolios using the
 * linked-data interchange API
 *
 * Limitations:
 *  - Cannot create or update tasks (the KOLOLA intx api is currently read-only)
 *  - Projects are not yet supported (KOLOLA needs to add something like event->event mapping as an evidence type)
 *  - Contexts are not yet supported
 */
class KololaSource implements Source {

    /**
     * Required options:
     *      domain: domain of the eportfolio
     *      query: API search query
     *      key: API key
     *      status_uri: URI of the evidence type that contains the task status
     *
     * Optional options:
     *
     *      due_uri: URI of the evidence type that contains the task due date
     *
     *      JSON Paths are used to map data from the intx API into task fields
     *      jp_id: json path to retrieve task ID
     *      jp_title: json path to retrieve task title
     *      jp_status: json path to retrieve status
     *      jp_due: json path to retrieve due date
     *      jp_created: json path to retrieve creation date
     *      jp_priority: json path to retrieve priority
     *      jp_context: json path to retrieve context
     *      jp_projects: json path to retrieve project
     *
     *      map_status: an array that maps strings from kolola (found via jp_status) to
     *                  the status strings you want to use in todo.txt
     */
    public static function factory($options) {

        return new KololaSource($options);

    }

    private function __construct($options) {
        $options = $this->fillOpts($options,
        array('domain', 'query', 'key', 'status_uri'),
        array(
            'due_uri'=>false,
            'map_status'=>array()
        ));

        // Fill opts again, to build based on previously set ones
        $options = $this->fillOpts($options, array(), array(
            'jp_id'=>'$._id',
            'jp_title'=>'$.data.name',
            'jp_status'=>'$.references[\''.self::T_EVENTEVIDENCE.'\'][?(@.data.evidenceid.uri=="'.$options['status_uri'].'")].data.value',
            'jp_due'=>$options['due_uri'] ? '$.references[\''.self::T_EVENTEVIDENCE.'\'][?(@.data.evidenceid.uri=="'.$options['due_uri'].'")].data.value' : '',
            'jp_created'=>'$.data.startdate',
            'jp_completed'=>'$.data.enddate',
            'jp_priority'=>'',
            'jp_context'=>'',
            'jp_projects'=>'',
        ));

        $this->opts = $options;

        $this->refresh();
    }

    protected function fillOpts($opts, $require, $defaults) {

        // Check that all required options exist
        foreach($require as $r) {
            if(!array_key_exists($r, $opts))
                throw new \Exception("Required option $r is missing for KololaSource");
        }

        // Merge in the defaults; supplied options take priority
        $opts = array_merge($defaults, $opts);

        return $opts;
    }

    public function canUpdate() {
        return false;
    }

    public function canCreate() {
        return false;
    }

    // Fetch the remote records into our local cache
    private $tasks = array();
    private $lastsync = 0;
    protected function refresh() {
        $set = new KOLOLAIntxSet($this->opts);
        $this->tasks = $set->getTasks();
	$this->lastsync = time();
    }

    public function refreshIfStale() {
	if(time() - $this->lastsync > 120) {
		$this->refresh();
	}
    }

    public function getAll() {
	$this->RefreshIfStale();
        return $this->tasks;
    }

    public function get($id) {
	$this->refreshIfStale();
        if(array_key_exists($id, $this->tasks)) {
            return $this->tasks[$id];
        }

        throw new \Exception("Task $id does not exist");
    }

    public function update($id, Task $t) {
        throw new \Exception("KololaSource::update() is not implemented");
    }

    public function create(Task $t) {
        throw new \Exception("KololaSource::create() is not implemented");
    }

    /**
     * Data types are identified by URIs, these constants are easier to work with!
     */

    // Events/Activities
    public const T_EVENT = 'http://schema.kolola.net/kolola/1/event';

    // Links & Attachments
    public const T_LINK = 'http://schema.kolola.net/kolola/1/link';
    public const T_ATTACHMENT = 'http://schema.kolola.net/kolola/1/attachment';

    // Participants
    public const T_PERSON = 'http://schema.kolola.net/kolola/1/person';
    public const T_EVENTPARTICIPANT = 'http://schema.kolola.net/kolola/1/eventparticipant';

    // Features, types, categories
    public const T_EVENTFEATURE = 'http://schema.kolola.net/kolola/1/eventfeature';
    public const T_FEATURE = 'http://schema.kolola.net/kolola/1/feature';
    public const T_TYPE = 'http://schema.kolola.net/kolola/1/type';
    public const T_CATEGORY = 'http://schema.kolola.net/kolola/1/category';

    // Evidence
    public const T_EVENTEVIDENCE = 'http://schema.kolola.net/kolola/1/eventevidence';
    public const T_EVIDENCE = 'http://schema.kolola.net/kolola/1/evidence';

}

/**
 * Represent a set of Interchange records
 * $json is raw (unparsed) JSON from the endpoint
 * $paths is an array of JSON paths for looking up fields, in the format array('fieldname'=>'$.json.path')
 */
class KOLOLAIntxSet {
    public function __construct($options) {
        $this->options = $options;

        $q = urlencode($options['query']);
        $k = urlencode($options['key']);

        $url = "https://{$options['domain']}/api/intx/?search&q=$q&token=$k";
        $json = file_get_contents($url);
        $this->import($json);
    }

    protected function import($json) {
        $data = json_decode($json, true);
        if(!$data) {
            throw new \Exception("Did not receive valid JSON from KOLOLA Intx endpoint");
        }

        $this->records = $data['result']['records']['records'];

        // Set up references to foreign records
        foreach($this->records as &$r) {

            // JSONpath doesn't like fields with @ in them... work around it
            $r['_id'] = &$r['@id'];

            foreach($r['data'] as &$field) {
                if($field['type'] === 'pointer') {
                    // Add a reference from the current record to to the foreign record
                    $foreign = &$this->findRecord($field['uri']);
                    $field['foreign'] = &$foreign;

                    // And add a reverse reference from the foreign record to this record
                    if($foreign !== false) {
                        if(!array_key_exists('references', $foreign)) {
                            $foreign['references'] = array();
                        }

                        if(!array_key_exists($r['@type'], $foreign['references']))
                            $foreign['references'][$r['@type']] = array();

                        $foreign['references'][$r['@type']][] = &$r;
                    }
                }
            }
        }
    }

    protected function &findRecord($uri) {
        foreach($this->records as &$r) {
            if($r["@id"] == $uri) {
                return $r;
            }
        }

        $o = false;
        return $o;
    }

    /**
     * Get all activities in the record set as Task objects
     */
    public function getTasks() {
        foreach($this->records as $r){
            if($r['@type'] == KololaSource::T_EVENT) {

                // Use JSONPaths to extract Task information
                $jo = new ExtendedJsonObject($r);

                $uri = $jo->get($this->options['jp_id']);
                $id = @array_pop(explode('/', $uri));
                $title = $jo->get($this->options['jp_title']);
                $status = $jo->get($this->options['jp_status']);
                $due = $jo->get($this->options['jp_due']);
                $created = $jo->get($this->options['jp_created']);
                $priority = $jo->get($this->options['jp_priority']);
                //$contexts = array($jo->get($this->options['jp_context']));
                //$projects = array($jo->get($this->options['jp_projects']));

                $tasks[] = $t = new Task();
                $t->setTask($title);
                $t->setStatus($status);
                $t->setCreationDate(\DateTime::createFromFormat('Y-m-d', $created));
                // Due goes in metadata, below
                if($priority == true && strlen($priority) > 0) $t->setPriority($priority);
                //$t->setContexts($contexts);
                //$t->setProjects($projects);

                // Other structured data goes into the metadata
                $md = array(
                    'href'=>"//".$this->options['domain']."/#event:$id",
                    Syncer::METAIDFIELD=>$id
                );

                if($due && strlen($due) > 0) {
                    $md['due']  = $due;
                }

                $t->setMetadata($md);
            }
        }

        return $tasks;
    }
}

class ExtendedJsonObject extends JsonObject {
    public function get($path) {

        // Blank strings do nothing
        if(strlen($path) < 1)
            return "";

        // Raw strings are just returned
        if(preg_match('/^".*"$/', $path))
            return substr($path, 1, strlen($path)-2);

        $val = parent::get($path);
        $val = $this->val($val);

        if($val === null || $val === false) {
            echo "$path\n => ";
            var_dump($val);

        }

        return $val;
    }

    protected function val($item) {
        if(is_array($item) && array_key_exists(0, $item))
            return $this->val($item[0]);

        if(is_array($item) && array_key_exists('type', $item) && $item['type'] == 'data')
            return $item['value'];

        return $item;
    }
}
