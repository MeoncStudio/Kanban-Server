<?php


namespace App;

use Flight;
use Throwable;

use App\Kanban;

class Events{

    public static $uid = 0;

    public $id = 0;
    public $board_id = 0;
    public $column_id = 0;
    public $title = "";
    public $note = "";

    function __construct($id, $title, $note, $board_id, $column_id){

        $this->id = (int)$id;
        $this->board_id = $board_id;
        $this->column_id = $column_id;
        $this->title = $title;
        $this->note = $note;

        Kanban::$dictionary['events'][(string)$this->id] = Array(
            "board_id" => $this->board_id,
            "column_id" => $this->column_id,
        );

    }

    public function get(){
        return get_object_vars($this);
    }


}