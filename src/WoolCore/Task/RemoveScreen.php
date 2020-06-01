<?php

namespace WoolCore\Task;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;

use WoolCore\Main;

class RemoveScreen extends Task{

    protected $p;
    protected $pos;

    public function __construct(Player $p, $pos = false){
     $this->p = $p;
     $this->pos = $pos;
    }

    public function onRun($tick){
     $pk = new PlayStatusPacket();
     $pk->status = 3;
     $this->p->sendDataPacket($pk);
     if($this->pos instanceof Position){
      $spawn = $this->pos;
      $this->p->teleport($spawn);
      $pk = new ChangeDimensionPacket();
      $pk->position = $this->pos;
      $pk->dimension = DimensionIds::OVERWORLD;
      $pk->respawn = true;
      $this->p->sendDataPacket($pk);
      Main::getInstance()->getScheduler()->scheduleDelayedTask(new RemoveScreen($this->p), 40);
     }
    }

}