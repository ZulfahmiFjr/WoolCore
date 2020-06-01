<?php

namespace WoolCore\Task;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\Server;

use WoolCore\Main;
use WoolVote\Task\GetTopVotersTask;

class RunUpdate extends Task{

    public function __construct(Player $p, $type = 0){
     $this->p = $p;
     $this->type = $type;
    }

    public function onRun($tick){
     $pl = Main::getInstance();
     if($this->type === 1){
      $particles = $pl->getParticles();
      foreach($particles as $type => $particle){
       $leaderboard = $pl->getLeaderBoard($type);
       $particle->setText($leaderboard);
      }
      $plvote = Server::getInstance()->getPluginManager()->getPlugin("WoolVote");
      if(!is_null($plvote)){
       $plvote->getServer()->getAsyncPool()->submitTask(new GetTopVotersTask($plvote->apiKey));
      }
     }else{
      $this->p->addTitle("§6§lWoolCore_v2", "§e§oWool Craft §fS2 §cIndo§fnesia");
     }
    }

}