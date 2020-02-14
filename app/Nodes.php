<?php

namespace App;


use Flight;
use Throwable;
use ReflectionClass;
use ReflectionProperty;

use App\Kanban;

class Nodes{

    public $id = 0;
    protected $parent_id = null;
    protected $grandparent_id = null;
    public $title = "";
    public $note = "";
    private $nodes = [];
    private $type = ""; // whether board, column or event
    private $class = "";

    function __construct($id, $parent_id, $title = "", $note = ""){
        $this->id = (int)$id;
        $this->class = get_class($this);
        $this->type = rtrim(strtolower(explode('\\',$this->class)[1]), "s");
        $this->parent_id = isset($parent_id) ? (int)$parent_id : null;
        $this->set($title, $note);
        $this->setDictionary();
        $this->fetch();
    }

    public function set($title, $note){
        $this->title = $title;
        $this->note = $note;
        Kanban::save();
    }

    private function setDictionary(){
        // Add into category
        Kanban::$dictionary[$this->type][$this->id] = Kanban::$typeDictionary[$this->type];

        // Add into parent
        $parent = $this->getParentType();
        if($parent !== false && isset($this->parent_id)){
            Kanban::$dictionary[$this->type][$this->id][$parent."_id"] = $this->parent_id;
            Kanban::$dictionary[$parent][$this->parent_id][$this->type][] = $this->id;
        }

        $grandparent = $this->getParentType(2);
        if($grandparent !== false && isset($this->grandparent_id)){
            Kanban::$dictionary[$this->type][$this->id][$grandparent."_id"] = $this->grandparent_id;
            Kanban::$dictionary[$grandparent][$this->grandparent_id][$this->type][] = $this->id;
        }

        Kanban::save();
    }

    private function getParentType($level = 1){
        return Kanban::getParentType($this->type, $level);
    }

    private function getChildrenType($level = 1){
        return Kanban::getChildrenType($this->type, $level);
    }

    public function fetch($childOnly = true){
        if(!$childOnly){
            $ret = Flight::sql("SELECT `title`, `note` FROM `{$this->type}` WHERE `id` ='{$this->id}'   ", true);
            $this->set($ret->title, $ret->note);
        }

        $this->nodes = [];
        $tableName = $this->getChildrenType();
        $nodesClass = "App\\" . $this->getChildrenType() . "s";

        if($tableName === false){
            return;
        }

        $ret = Flight::sql("SELECT * FROM `$tableName` WHERE `{$this->type}_id` ='{$this->id}'   ", true);
        foreach ($ret as $node) {
            $this->nodes[$node->id] = new $nodesClass($node->id, $this->id, $node->title, $node->note, $this->parent_id);
        }
    }

    public function child($id = null){
        if(array_key_exists($id, $this->nodes)){
            return $this->nodes[$id];
        }else if(!isset($id)){
            return $this->nodes;
        }else{
            return false;
        }
    }

    public function print($node_id = null){
        if(isset($node_id)){
            if (array_key_exists($node_id, $this->nodes)) {
                return $this->nodes[$node_id]->print();
            } else {
                return false;
            }
        }
        
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $arr = [];
        foreach($properties as $property){
            $key = $property->name;
            $arr[$key] = $this->$key;
        }

        $nodesType = $this->getChildrenType();
        if($nodesType !== false){
            $nodesType .= "s";
            $arr[$nodesType] = [];
            foreach ($this->nodes as $node) {
                $arr[$nodesType][] = $node->print();
            }
        }
        
        return $arr;
    }

    protected static function gets($data){
        return [StatusCodes::OK, "OK", null];
    }

    protected static function creates($data){
        return [StatusCodes::OK, "OK", null];
    }

    protected static function updates($data){
        return [StatusCodes::OK, "OK", null];
    }

    protected static function deletes($data){
        return [StatusCodes::OK, "OK", null];
    }

    
    public static function Nodes($method, $node_id)
    {
        $func = null;
        $args = array();

        switch ($method) {
            case "GET":
                $func = "gets";
                break;
            case "POST":
                $func = "creates";
                $args = ["title"];
                break;
            case "PATCH":
                $func = "updates";
                $args = ["node_id"];
                break;
            case "DELETE":
                $func = "deletes";
                $args = ["node_id"];
                break;
        }

        if($func == null){
            Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
            return;
        }

        $miss = [];
        $data = Flight::request()->data;
        $data->node_id = $node_id;
        $data->user_id = Kanban::$current->id;
        
        foreach ($args as $key => $param) {
            if (!isset($data->$param)) {
                array_push($miss, $param);
            }
        }

        // Escape
        foreach($data as $key => $each){
            $data->$key = addslashes($each);
        }

        if (!empty($miss)) {
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Missing Params", array("missing" => $miss));
            return;
        }

        list($code, $message, $array) = self::$func($data);
        Flight::ret($code, $message, $array);
        
    }

}