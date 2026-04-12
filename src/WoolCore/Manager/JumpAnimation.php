<?php

namespace WoolCore\Manager;

use pocketmine\player\Player;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Effect;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\level\Position;
use WoolCore\Main;
use WoolCore\Task\SendTitle;

class JumpAnimation extends Animation
{
    public function doAnimation()
    {
        parent::doAnimation();
        $p = $this->getPlayer();
        $p->setGameMode(3);
        $p->extinguish();
        $p->addTitle("§e§oTeleporting§r§f...", "");
        $p->addEffect(new EffectInstance(Effect::getEffect(Effect::INVISIBILITY), 999, 10, false, false));
        $this->animationPhase = 0;
        $this->pos = $p->asVector3()->add(0, 1, 0);
        $this->y = $this->pos->y;
        $this->yaw = $p->yaw;
        $this->pitch = $p->pitch;
        $this->move();
    }

    public function onUpdate(): bool
    {
        if (($p = $this->getPlayer()) === null) {
            return false;
        }
        switch ($this->animationPhase) {
            case 0:{
                if ($this->pos->y >= $this->y + 3) {
                    $this->animationPhase = 1;
                    return true;
                }
                $this->pos->y = $this->pos->y + 0.2;
                $this->move();
                break;
            }
            case 1:{
                if ($this->pos->y <= $this->y) {
                    $this->animationPhase = 2;
                    return true;
                }
                $this->pos->y = $this->pos->y - 0.2;
                $this->move();
                break;
            }
            case 2:{
                if ($this->pos->y >= $this->y + 25) {
                    $this->animationPhase = 3;
                    return true;
                }
                if ($this->pos->y >= $this->y + 20) {
                    $p->addEffect(new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 999, 1, false, false));
                }
                $this->pos->y = $this->pos->y + 0.6;
                $this->move();
                break;
            }
            case 3:{
                Main::getInstance()->getServer()->loadLevel("Survival");
                $p->saveTeleport(Main::getInstance()->getServer()->getLevelByName("Survival")->getSafeSpawn());
                $p->setGameRule('naturalregeneration', true);
                $p->removeAllEffects();
                $p->setMode(0);
                $p->setGamemode(0);
                return false;
            }
        }
        return true;
    }

}
