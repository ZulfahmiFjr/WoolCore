<?php

namespace WoolCore\Manager;

use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use WoolCore\Main;

class FloatingText extends FloatingTextParticle
{
    private World $world;
    private Vector3 $pos;

    public function __construct(Main $pl, Vector3 $pos)
    {
        parent::__construct("", "");
        $this->world = $pl->getServer()->getWorldManager()->getDefaultWorld();
        $this->pos = $pos;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
        $this->update();
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function update(): void
    {
        $this->world->addParticle($this->pos, $this);
    }

}
