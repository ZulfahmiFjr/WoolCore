<?php

namespace WoolCore\Manager;

use pocketmine\player\Player;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\item\ItemFactory;
use pocketmine\player\GameMode;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\world\Position;
use WoolCore\Main;
use WoolCore\Task\SendTitle;

class JumpAnimation extends Animation
{
    protected $pos;
    protected float $yaw;
    protected float $pitch;
    protected float $startY;
    protected int $animationPhase = 0;

    public function doAnimation()
    {
        parent::doAnimation();
        $p = $this->getPlayer();
        $p->setGamemode(GameMode::ADVENTURE());
        $p->extinguish();
        $p->addTitle("§e§oTeleporting§r§f...", "");
        $p->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 999, 10, false));
        $this->pos = $p->getPosition()->asVector3()->add(0, 1, 0);
        $this->startY = $this->pos->y;
        $this->yaw = $p->getLocation()->getYaw();
        $this->pitch = $p->getLocation()->getPitch();
        $this->animationPhase = 0;
    }

    public function onUpdate(): bool
    {
        $p = $this->getPlayer();
        if ($p === null) {
            return false;
        }
        switch ($this->animationPhase) {
            case 0:
                $this->pos->y += 0.25;
                if ($this->pos->y >= $this->startY + 3) {
                    $this->animationPhase = 1;
                }
                break;
            case 1:
                $this->pos->y -= 0.25;
                if ($this->pos->y <= $this->startY) {
                    $this->animationPhase = 2;
                }
                break;
            case 2:
                $this->pos->y += 0.7;
                if ($this->pos->y >= $this->startY + 20) {
                    $p->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), 40, 1, false));
                }
                if ($this->pos->y >= $this->startY + 25) {
                    $this->animationPhase = 3;
                }
                break;
            case 3:
                $wm = Main::getInstance()->getServer()->getWorldManager();
                $wm->loadWorld("Survival");
                $target = $wm->getWorldByName("Survival")->getSafeSpawn();
                $p->saveTeleport($target);
                $p->setGameRule('naturalregeneration', true);
                $p->getEffects()->clear();
                $p->setMode(0);
                $p->setGamemode(GameMode::SURVIVAL());
                return false;
        }
        $p->teleport($this->pos, $this->yaw, $this->pitch);
        return true;
    }

}
