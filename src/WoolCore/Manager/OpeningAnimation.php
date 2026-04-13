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
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\entity\animation\TotemUseAnimation;
use WoolCore\Main;
use WoolCore\Task\RunUpdate;

class OpeningAnimation extends Animation
{
    protected float $yaw;
    protected float $pitch;
    protected Vector3 $pos;
    protected int $animationPhase = 0;

    public function doAnimation()
    {
        parent::doAnimation();

        $p = $this->getPlayer();
        if ($p === null) {
            return;
        }
        $p->setGamemode(GameMode::ADVENTURE());
        $p->extinguish();
        // sembunyikan player
        $p->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 100, 1, false));
        // delay lanjut animation berikutnya (RunUpdate)
        Main::getInstance()->getScheduler()->scheduleDelayedTask(
            new RunUpdate($p, 2),
            60
        );
        // set awal
        $this->yaw = 180;
        $this->pitch = -40;
        // paksa posisi awal
        $p->teleport($p->getPosition(), $this->yaw, $this->pitch);
        $this->animationPhase = 0;
    }

    public function onUpdate(): bool
    {
        $p = $this->getPlayer();
        if ($p === null) {
            return false;
        }
        switch ($this->animationPhase) {
            // 🎬 Phase 0: kamera naik (lihat ke depan)
            case 0:
                if ($this->pitch >= 0) {
                    $this->animationPhase = 1;
                    break;
                }
                $this->pitch += 0.5;
                $p->teleport(
                    $p->getPosition(),
                    $this->yaw,
                    $this->pitch
                );
                break;
                // 🎯 Phase 1: selesai → restore player
            case 1:
                $p->setGameRule('naturalregeneration', true);
                $p->getEffects()->clear();
                $p->setMode(0);
                $p->setGamemode(GameMode::SURVIVAL());
                $this->sendJoinUI($p);
                return false;
        }

        return true;
    }

    private function sendJoinUI(Player $p): void
    {
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
                $p->broadcastAnimation(new TotemUseAnimation($p));
                $p->getInventory()->removeItem($item);
                $p->getInventory()->setItemInHand($hand);
            }
        });
        $form->setTitle("§e§lWool Craft §cIndonesia");
        $info = Main::getInstance()->getDataConfig()->get("join-ui") ?? "";
        $info = str_replace(["[p]", "[-]"], [$p->getName(), "\n"], $info);
        $form->setContent($info);
        $p->sendForm($form);
    }
}
