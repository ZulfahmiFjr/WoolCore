<?php

namespace WoolCore;

use pocketmine\Player;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;

use WoolCore\Manager\Animation;
use WoolCore\Task\RemoveScreen;

class PlayerSession extends Player{

    public $loopTicks = 0;
    public $reveseLoops = false;
    public $delayTicks = 0;
    public $frame = 0;
    public $stage = 0;
    public $mode = 1;
    public $startPos = 0;
    public $startAnimation = false;

    public function setMode($mode){
     $this->mode = $mode;
    }

    public function getMode(){
     return $this->mode;
    }

    public function actionAnimation(Animation $animation){
     $this->setMode(1);
     $animation->doAnimation();
    }

    public function setGameRule($gamerule, $boolean){
     $pk = new GameRulesChangedPacket;
     $pk->gameRules = [$gamerule => [1, $boolean]];
     $this->dataPacket($pk);
    }

    public function saveTeleport(Position $position){
     $this->teleport(Main::getInstance()->getServer()->getLevelByName("transfare")->getSafeSpawn());
     $pk = new ChangeDimensionPacket();
     $pk->position = Main::getInstance()->getServer()->getLevelByName("transfare")->getSafeSpawn();
     $pk->dimension = DimensionIds::THE_END;
     $pk->respawn = true;
     $this->sendDataPacket($pk);
     Main::getInstance()->getScheduler()->scheduleDelayedTask(new RemoveScreen($this, $position), 20);
    }

    public function onUpdate($tick):bool{
     parent::onUpdate($tick);
     $pl = Main::getInstance();
     if($this->getMode() === 0){
      $pl->addBossBar($this);
      if($this->startPos !== 0 || $this->startAnimation === true){
       $this->startPos = 0;
       $this->startAnimation = false;
      }
      $defaultLevel = $pl->getServer()->getDefaultLevel();
      if($defaultLevel->getFolderName() === $this->getLevel()->getFolderName()){
       if($this->getGamemode() !== 2){
        $this->setGamemode(2);
       }
       if(!$this->reveseLoops){
        $this->loopTicks += 1;
        if($this->loopTicks >= 100){
         $this->reveseLoops = true;
        }
       }else{
        $this->loopTicks -= 1;
        if($this->loopTicks <= 0){
         $this->reveseLoops = false;
        }
       }
       if($this->delayTicks >= 3){
        $frames = $pl->data->get("text");
        if($this->stage === 10){
         if($this->frame === -1){
          $this->stage = 11;
          $this->frame = 1;
          $pl->sendBossPacket($this, "        §e§lWool Craft §fS2 §cIndo§fnesia§r\n\n".str_replace("{player}", "Hai ".$this->getName(), $frames[$this->frame]), $this->loopTicks, 100);
         }
        }
        if($this->frame > array_reverse(array_keys($frames))[0]){
         $this->frame -= 2;
         $this->stage = 10;
        }else if($this->stage === 10){
         $pl->sendBossPacket($this, "        §e§lWool Craft §fS2 §cIndo§fnesia§r\n\n".str_replace("{player}", "Hai ".$this->getName(), $frames[$this->frame]), $this->loopTicks, 100);
         $this->frame--;
        }else{
         $pl->sendBossPacket($this, "        §e§lWool Craft §fS2 §cIndo§fnesia§r\n\n".str_replace("{player}", "Hai ".$this->getName(), $frames[$this->frame]), $this->loopTicks, 100);
         $this->frame++;
        }
        $this->delayTicks = 0;
       }
       $this->delayTicks += 1;
       $pl->sendBossPacket($this, '', $this->loopTicks, 100, 4);
      }else{
       if($this->getGamemode() !== 0 && !$this->isOp() && !$this->hasPermission("feature.creative")){
        $this->setGamemode(0);
       }
       $percent = round(($pl->getExp($this) / $pl->getExpCount($this)) * 100);
       $space = 4;
       if(strlen($percent) === 1){
        $space = 3;
       }
       $pl->sendBossPacket($this, str_repeat(" ", $space)."§e§lLevel Bar§r\n\n§6§oLevel§r§f: ".$pl->getLevel($this)." | §6§oExp§r§f: ".$percent."%%%%", $pl->getExp($this), $pl->getExpCount($this));
       $pl->sendBossPacket($this, '', $pl->getExp($this), $pl->getExpCount($this), 4);
      }
     }else{
      if($this->startAnimation === false){
       $this->startPos = $this->y;
       $this->startAnimation = true;
      }
      $pl->sendBossPacket($this, "§e§oTeleporting§r§f...", $this->y - $this->startPos, 23);
      $pl->sendBossPacket($this, '', $this->y - $this->startPos, 23, 4);
     }
     return true;
    }

}