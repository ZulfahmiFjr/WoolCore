<?php

namespace WoolCore\Manager;

use pocketmine\player\Player;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Effect;
use pocketmine\item\VanillaItems;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\player\GameMode;
use WoolCore\Main;
use WoolCore\Task\RunUpdate;

class OpeningAnimation extends Animation
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
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new RunUpdate($p, 2), 60);
        $p->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 999, 10, false));
        $this->animationPhase = 0;
        $this->pos = $p->getPosition()->asVector3()->add(0, 3, 0);
        $this->yaw = 180;
        $this->pitch = -40;
        $this->move();
    }

    public function onUpdate(): bool
    {
        if (($p = $this->getPlayer()) === null) {
            return false;
        }
        switch ($this->animationPhase) {
            case 0:{
                $defaultLevel = Main::getInstance()->getServer()->getWorldManager()->getDefaultWorld();
                if ($defaultLevel->getFolderName() !== $p->getWorld()->getFolderName()) {
                    $this->animationPhase = 1;
                    break;
                }
                if ($this->pitch >= 0) {
                    $this->animationPhase = 1;
                    break;
                }
                $this->pitch = $this->pitch + 0.3;
                $this->move();
                break;
            }
            case 1:{
                $p->setGameRule('naturalregeneration', true);
                $p->getEffects()->clear();
                $p->setMode(0);
                $p->setGamemode(GameMode::SURVIVAL());
                $form = new SimpleForm(function (Player $p, $result) {
                    if ($result === null) {
                        if (!$p->isOp()) {
                            $p->sendMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§omasuk server§r§f!");
                        } else {
                            $p->sendMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§omasuk server§r§f! §l(§9Staff§f)");
                        }
                        $item = VanillaItems::TOTEM();
                        $hand = $p->getInventory()->getItemInHand();
                        if (!$hand->isNull()) {
                            $p->getInventory()->removeItem($hand);
                        }
                        $p->getInventory()->setItemInHand($item);
                        $p->broadcastEntityEvent(ActorEventPacket::CONSUME_TOTEM);
                        $p->getInventory()->removeItem($item);
                        $p->getInventory()->setItemInHand($hand);
                        return;
                    }
                });
                $form->setTitle("§e§lWool Craft §cIndo§fnesia");
                if (Main::getInstance()->getDataConfig()->get("join-ui") !== null) {
                    $info = str_replace(["[p]", "[-]"], [$p->getName(), "\n"], Main::getInstance()->getDataConfig()->get("join-ui"));
                }
                $form->setContent((string) $info);
                $p->sendForm($form);
                return false;
            }
        }
        return true;
    }

}
