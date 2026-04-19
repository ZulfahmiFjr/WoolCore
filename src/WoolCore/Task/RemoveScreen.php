<?php

namespace WoolCore\Task;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class RemoveScreen extends Task
{
    private Player $player;
    private Position $target;

    public function __construct(Player $player, Position $target)
    {
        $this->player = $player;
        $this->target = $target;
    }

    public function onRun(): void
    {
        if (!$this->player->isConnected()) {
            return;
        }
        $session = $this->player->getNetworkSession();
        $world = $this->target->getWorld();
        // teleport real
        $this->player->teleport($this->target);
        // sync data
        $session->syncGameMode($this->player->getGamemode());
        $session->syncAbilities($this->player);
        $session->syncAdventureSettings();
        $session->syncViewAreaRadius($this->player->getViewDistance());
        $session->syncPlayerSpawnPoint($this->player->getSpawn());
        $session->syncAvailableCommands();
        $session->onEnterWorld();
        $session->syncPlayerList($world->getPlayers());
        // balik ke overworld
        $session->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::OVERWORLD, $this->target, false, null));
        $blockPos = BlockPosition::fromVector3($this->target);
        // ACK
        $session->sendDataPacket(PlayerActionPacket::create($this->player->getId(), PlayerAction::DIMENSION_CHANGE_ACK, $blockPos, $blockPos, 0));
        // unlock movement
        $session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::PLAYER_SPAWN));
    }
}
