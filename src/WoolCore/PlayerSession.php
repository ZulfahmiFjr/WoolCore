<?php

namespace WoolCore;

use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\player\GameMode;
use pocketmine\world\Position;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use WoolCore\Manager\Animation;
use WoolCore\Task\RemoveScreen;

class PlayerSession extends Player
{
    private float $y = 0;
    private float $startPos = 0;
    private bool $startAnimation = false;

    public $loopTicks = 0;
    public $reveseLoops = false;
    public $delayTicks = 0;
    public $frame = 0;
    public $stage = 0;
    public $mode = 1;

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function actionAnimation(Animation $animation)
    {
        $this->setMode(1);
        $animation->doAnimation();
    }

    public function setGameRule(string $gamerule, bool $boolean): void
    {
        $pk = new GameRulesChangedPacket();
        $pk->gameRules = [
            $gamerule => new BoolGameRule($boolean, false)
        ];
        $this->getNetworkSession()->sendDataPacket($pk);
    }

    // public function saveTeleport(Position $target)
    // {
    //     $session = $this->getNetworkSession();
    //     $this->teleport(Main::getInstance()->getServer()->getWorldManager()->getWorldByName("transfare")->getSafeSpawn());
    //     $pk = new ChangeDimensionPacket();
    //     $pk->position = Main::getInstance()->getServer()->getWorldManager()->getWorldByName("transfare")->getSafeSpawn();
    //     $pk->dimension = DimensionIds::THE_END;
    //     $pk->respawn = false;
    //     $session->sendDataPacket($pk);
    //     $this->setNoClientPredictions(true);
    //     // Main::getInstance()->getScheduler()->scheduleDelayedTask(new RemoveScreen($this, $position), 20);
    //     Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($target, $session){
    //         // teleport setelah client siap
    //         $this->teleport($target);
    //         // STEP 3: balik dimension
    //         $pk = new ChangeDimensionPacket();
    //         $pk->dimension = DimensionIds::OVERWORLD;
    //         $pk->position = $target;
    //         $pk->respawn = false; // 🔥 WAJIB FALSE
    //         $session->sendDataPacket($pk);
    //         $this->setNoClientPredictions(false);
    //         // STEP 4: spawn (UNLOCK CLIENT)
    //         $session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::PLAYER_SPAWN));
    //     }), 10);
    // }


    public function saveTeleport(Position $target)
    {
        $session = $this->getNetworkSession();
        $world = $target->getWorld();
        $pos = $this->getPosition();
        $blockPos = BlockPosition::fromVector3($pos);
        // loading screen (END)
        $session->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::THE_END, $pos, false, null));
        // ACK
        $session->sendDataPacket(PlayerActionPacket::create($this->getId(), PlayerAction::DIMENSION_CHANGE_ACK, $blockPos, $blockPos, 0));
        // load chunk target
        $chunkX = $target->getFloorX() >> 4;
        $chunkZ = $target->getFloorZ() >> 4;
        $world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
            // berhasil
            function (Chunk $chunk) use ($target) {
                // delay biar loading kerasa
                Main::getInstance()->getScheduler()->scheduleDelayedTask(new RemoveScreen($this, $target), 40);
            },
            // gagal
            function () {
                if ($this->isConnected()) {
                    $this->kick("Chunk failed to load (anti stuck)");
                }
            }
        );
    }

    public function onUpdate($tick): bool
    {
        parent::onUpdate($tick);
        $pl = Main::getInstance();
        if ($this->getMode() === 0) {
            $pl->addBossBar($this);
            if ($this->startPos !== 0 || $this->startAnimation === true) {
                $this->startPos = 0;
                $this->startAnimation = false;
            }
            $defaultLevel = $pl->getServer()->getWorldManager()->getDefaultWorld();
            if ($defaultLevel->getFolderName() === $this->getWorld()->getFolderName()) {
                if ($this->getGamemode() !== 2) {
                    $this->setGamemode(GameMode::ADVENTURE());
                }
                if (!$this->reveseLoops) {
                    $this->loopTicks += 1;
                    if ($this->loopTicks >= 100) {
                        $this->reveseLoops = true;
                    }
                } else {
                    $this->loopTicks -= 1;
                    if ($this->loopTicks <= 0) {
                        $this->reveseLoops = false;
                    }
                }
                if ($this->delayTicks >= 3) {
                    $frames = $pl->getDataConfig()->get("text");
                    if ($this->stage === 10) {
                        if ($this->frame === -1) {
                            $this->stage = 11;
                            $this->frame = 1;
                            $pl->sendBossPacket($this, "        §e§lWool Craft §fS2 §cIndo§fnesia§r\n\n".str_replace("{player}", "Hai ".$this->getName(), $frames[$this->frame]), $this->loopTicks, 100);
                        }
                    }
                    if ($this->frame > array_reverse(array_keys($frames))[0]) {
                        $this->frame -= 2;
                        $this->stage = 10;
                    } elseif ($this->stage === 10) {
                        $pl->sendBossPacket($this, "        §e§lWool Craft §fS2 §cIndo§fnesia§r\n\n".str_replace("{player}", "Hai ".$this->getName(), $frames[$this->frame]), $this->loopTicks, 100);
                        $this->frame--;
                    } else {
                        $pl->sendBossPacket($this, "        §e§lWool Craft §fS2 §cIndo§fnesia§r\n\n".str_replace("{player}", "Hai ".$this->getName(), $frames[$this->frame]), $this->loopTicks, 100);
                        $this->frame++;
                    }
                    $this->delayTicks = 0;
                }
                $this->delayTicks += 1;
                $pl->sendBossPacket($this, '', $this->loopTicks, 100, 4);
            } else {
                if ($this->getGamemode() !== 0 && !$this->hasPermission("pocketmine.command.op") && !$this->hasPermission("feature.creative")) {
                    $this->setGamemode(GameMode::SURVIVAL());
                }
                $percent = round(($pl->getExp($this) / $pl->getExpCount($this)) * 100);
                $space = 4;
                if (strlen($percent) === 1) {
                    $space = 3;
                }
                $pl->sendBossPacket($this, str_repeat(" ", $space)."§e§lLevel Bar§r\n\n§6§oLevel§r§f: ".$pl->getWorld($this)." | §6§oExp§r§f: ".$percent."%%%%", $pl->getExp($this), $pl->getExpCount($this));
                $pl->sendBossPacket($this, '', $pl->getExp($this), $pl->getExpCount($this), 4);
            }
        } else {
            if ($this->startAnimation === false) {
                $this->startPos = $this->getPosition()->getY();
                $this->startAnimation = true;
            }
            $pl->sendBossPacket($this, "§e§oTeleporting§r§f...", $this->getPosition()->getY() - $this->startPos, 23);
            $pl->sendBossPacket($this, '', $this->getPosition()->getY() - $this->startPos, 23, 4);
        }
        return true;
    }

}
