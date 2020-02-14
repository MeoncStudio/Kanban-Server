<?php


namespace App;

use Flight;
use Throwable;
use App;


class Kanban{

    public static $current = null;
    public static $nodes = null; // for current user
    public static $dictionary = null;
    public static $typeList = Array(
        1 => "board",
        2 => "column",
        3 => "event",
    );
    public static $typeDictionary = Array(
        "board" => Array(
            "column" => [],
            "event" => [],
        ),
        "column" => Array(
            "board_id" => 0,
            "event" => [],
        ),
        "event" => Array(
            "board_id" => 0,
            "column_id" => 0,
        )
    );

    public static function fetch(){
        self::$nodes = [];
        self::$dictionary = Array( // dictionary of relationships
            "board" => [],
            "column" => [],
            "event" => [],
        );

        $user_id = self::$current->id;
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id'  ", true);
        foreach($ret as $board){
            self::$nodes[$board->id] = new Boards($board->id, null, $board->title, $board->note, $board->user_id);
        }

        self::save();
        return true;
    }

    public static function save(){
        $_SESSION['kanban'] = serialize(self::$nodes);
        $_SESSION['dictionary'] = serialize(self::$dictionary);
    }

    public static function print(){
        $arr['boards'] = [];
        foreach (self::$nodes as $board) {
            $arr['boards'][] = $board->print();
        }
        return $arr;
    }

    public static function find($type, $id){
        if(!isset(self::$nodes)){
            self::fetch(self::$current->id);
        }
        if(!array_key_exists($type, self::$dictionary)){
            return false;
        }else if(!array_key_exists($id, self::$dictionary[$type])){
            return false;
        }
        $node = self::$dictionary[$type][$id];
        
        if($type == "board"){
            $board_id = $id;
        }else if($type == "column"){
            $board_id = $node['board_id'];
            $column_id = $id;
        }else if($type == "event"){
            $board_id = $node['board_id'];
            $column_id = $node['column_id'];
            $event_id = $id;
        }

        $ret = null;
        if(isset($board_id)){
            $ret = self::child($board_id);
        }
        if(isset($column_id) && $ret !== false){
            $ret = $ret->child($column_id);
        }
        if(isset($event_id) && $ret !== false){
            $ret = $ret->find($event_id);
        }
        return $ret;
    }

    public static function child($id){
        if(array_key_exists($id, self::$nodes)){
            return self::$nodes[$id];
        }else{
            return false;
        }
    }

    public static function getParentType($type, $level){
        $value = array_flip(Kanban::$typeList)[$type];
        return (array_key_exists($value - $level, Kanban::$typeList)) ? Kanban::$typeList[$value - $level] : false;
    }

    public static function getChildrenType($type, $level = 1){
        $value = array_flip(Kanban::$typeList)[$type];
        return (array_key_exists($value + $level, Kanban::$typeList)) ? Kanban::$typeList[$value + $level] : false;
    }

    

    public static function Kanban(){
        if(!isset(self::$nodes) || isset(Flight::request()->query->force)){
            if(!self::fetch()){
                Flight::ret(540, "Service Error", Flight::db()->error);
                return;
            }
        }
        $result = self::print();
        Flight::ret(200, "OK", $result);
    }

}