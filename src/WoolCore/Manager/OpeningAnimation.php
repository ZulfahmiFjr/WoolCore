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
use pocketmine\network\mcpe\protocol\CameraInstructionPacket;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionRotation;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEase;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
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
        $p->setGamemode(GameMode::ADVENTURE());
        // $p->setImmobile(true);
        $p->extinguish();
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new RunUpdate($p, 2), 60);
        $p->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 999, 10, false));
        $this->yaw = 180;
        $this->pitch = -40;
        $this->pos = $p->getPosition()->asVector3()->add(0, 3, 0);
        $p->teleport($p->getPosition(), $this->yaw, -40);
        $this->sendCamera($p, $this->pos, $this->yaw, -40);
        Main::getInstance()->getScheduler()->scheduleDelayedTask(
            new \pocketmine\scheduler\ClosureTask(function () use ($p) {
                $this->sendCamera($p, $this->pos, $this->yaw, 0);
            }),
            10
        );
        $this->animationPhase = 0;
        // $this->move();
    }

    public function sendCamera(Player $p, Vector3 $pos, float $yaw, float $pitch): void
    {
        $rotation = new CameraSetInstructionRotation($yaw, $pitch);
        $ease = new CameraSetInstructionEase(
            0,   // ease type (linear)
            1.0  // duration (detik)
        );
        $set = new CameraSetInstruction(
            1,        // preset (0 = free camera)
            $ease,    // easing
            $pos,     // posisi kamera
            $rotation,// rotasi
            null,     // facing position
            null,     // view offset
            null,     // entity offset
            null,     // default
            false      // ignore starting values
        );
        $pk = CameraInstructionPacket::create(
            $set,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );
        $p->getNetworkSession()->sendDataPacket($pk);
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
                $this->pitch += 0.3;
                $p->teleport(
                    $p->getPosition(),
                    $this->yaw,
                    $this->pitch
                );
                // $this->sendCamera($p, $this->pos, $this->yaw, $this->pitch);
                // $this->move();
                break;
            }
            case 1:{
                $p->setGameRule('naturalregeneration', true);
                $p->getEffects()->clear();
                $p->setMode(0);
                // $p->setImmobile(false);
                $p->setGamemode(GameMode::SURVIVAL());
                $form = new SimpleForm(function (Player $p, $result) {
                    if ($result === null) {
                        if ($p->hasPermission("pocketmine.command.op")) {
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
