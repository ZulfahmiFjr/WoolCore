<?php

namespace WoolCore\Manager;

use pocketmine\player\Player;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Effect;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use WoolCore\Main;
use WoolCore\Task\RunUpdate;

class OpeningAnimation extends Animation
{
    public function doAnimation()
    {
        parent::doAnimation();
        $p = $this->getPlayer();
        $p->setGameMode(3);
        $p->extinguish();
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new RunUpdate($p, 2), 60);
        $p->addEffect(new EffectInstance(Effect::getEffect(Effect::INVISIBILITY), 999, 10, false, false));
        $this->animationPhase = 0;
        $this->pos = $p->asVector3()->add(0, 3, 0);
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
                $defaultLevel = Main::getInstance()->getServer()->getDefaultLevel();
                if ($defaultLevel->getFolderName() !== $p->getLevel()->getFolderName()) {
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
                $p->removeAllEffects();
                $p->setMode(0);
                $p->setGamemode(0);
                $form = new SimpleForm(function (Player $p, $result) {
                    if ($result === null) {
                        if (!$p->isOp()) {
                            $p->sendMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§omasuk server§r§f!");
                        } else {
                            $p->sendMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§omasuk server§r§f! §l(§9Staff§f)");
                        }
                        $item = ItemFactory::get(ItemIds::TOTEM);
                        $hand = $p->getInventory()->getItemInHand();
                        if ($hand !== ItemIds::AIR) {
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
                if (Main::getInstance()->data->get("join-ui") !== null) {
                    $info = str_replace(["[p]", "[-]"], [$p->getName(), "\n"], Main::getInstance()->data->get("join-ui"));
                }
                $form->setContent((string) $info);
                $p->sendForm($form);
                return false;
            }
        }
        return true;
    }

}
