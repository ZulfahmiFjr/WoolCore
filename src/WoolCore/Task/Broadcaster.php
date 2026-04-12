<?php

namespace WoolCore\Task;

use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\Server;
use WoolCore\Main;

class Broadcaster extends Task
{
    private Main $pl;
    private int $length;

    public function __construct(Main $pl)
    {
        $this->pl = $pl;
        $this->length = -1;
    }

    public function onRun(): void
    {
        $data = (new Config($this->pl->getDataFolder()."data.yml", Config::YAML))->getAll();
        $this->length = $this->length + 1;
        $messages = $data["messages"];
        $messagekey = $this->length;
        $message = $messages[$messagekey];
        if ($this->length === count($messages) - 1) {
            $this->length = -1;
        }
        foreach (Server::getInstance()->getOnlinePlayers() as $allp) {
            if ($allp->getMode() === 0) {
                $allp->sendMessage("§f§l[§6WC§f] §r§e§o".$message);
            }
        }
    }

}
