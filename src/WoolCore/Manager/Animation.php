<?php

namespace WoolCore\Manager;

use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use WoolCore\Main;

class Animation
{
    protected $p = null;

    public function __construct(Player $p)
    {
        $this->p = $p;
        $this->pos = new Vector3(0, 0, 0);
        $this->yaw = 0;
        $this->pitch = 0;
    }

    public function getPlayer(): ?Player
    {
        return $this->p->isClosed() ? null : $this->p;
    }

    public function doAnimation()
    {
        $this->scheduleUpdate();
    }

    public function scheduleUpdate(): void
    {
        Main::getInstance()->scheduleUpdate($this);
    }

    public function move(Player $p, Vector3 $pos, float $yaw, float $pitch): void
    {
        $p->teleport($pos, $yaw, $pitch);
    }

}
