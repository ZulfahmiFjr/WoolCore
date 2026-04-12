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

    public function move()
    {
        $pk = MovePlayerPacket::simple(
            $this->p->getId(),   // actor runtime id
            $this->pos,          // Vector3
            $this->pitch,
            $this->yaw,
            $this->yaw,          // headYaw
            MovePlayerPacket::MODE_NORMAL, // atau MODE_TELEPORT
            false, // onGround (biasanya 0/false)
            0, // riding actor id
            0  // teleport cause
        );
        $this->p->getNetworkSession()->sendDataPacket($pk);
    }

}
