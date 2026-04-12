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
    protected int $animationPhase = 0;

    public function doAnimation()
    {
        parent::doAnimation();
        $p = $this->getPlayer();
        $p->setGamemode(GameMode::SPECTATOR());
        $p->extinguish();
        $p->addTitle("§e§oTeleporting§r§f...", "");
        $p->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 999, 10, false));
        $this->animationPhase = 0;
        $this->pos = $p->getPosition()->asVector3()->add(0, 1, 0);
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
                    $p->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), 999, 1, false, false);
                }
                $this->pos->y = $this->pos->y + 0.6;
                $this->move();
                break;
            }
            case 3:{
                $wm = Main::getInstance()->getServer()->getWorldManager();
                $wm->loadWorld("Survival");
                $p->saveTeleport(Main::getInstance()->getServer()->getWorldManager()->getWorldByName("Survival")->getSafeSpawn());
                $p->setGameRule('naturalregeneration', true);
                $p->getEffects()->clear();
                $p->setMode(0);
                $p->setGamemode(GameMode::SURVIVAL());
                return false;
            }
        }
        return true;
    }

}
