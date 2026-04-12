<?php

namespace WoolCore;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\entity\Effect;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use WoolCore\Manager\FloatingText;
use WoolCore\Task\Broadcaster;
use WoolCore\Task\CallbackUpdate;
use WoolCore\Manager\SimpleForm;
use WoolCore\Manager\JumpAnimation;
use specter\network\SpecterPlayer;
use WoolCore\Manager\OpeningAnimation;
use WoolCore\Task\RunUpdate;

class Main extends PluginBase implements Listener
{
    private static $instance;

    private $updates = [];
    private $leaderboardset = [];
    private $particles = [];
    private $breakTimes = [];

    protected function onEnable(): void
    {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("data.yml");
        $this->entityId = Entity::$entityCount++;
        $this->data = new Config($this->getDataFolder()."/data.yml", Config::YAML);
        $this->stats = new Config($this->getDataFolder()."/stats.yml", Config::YAML, array());
        if (!empty($this->data->getAll()["leaderboard"])) {
            foreach ($this->data->getAll()["leaderboard"] as $type => $pos) {
                $this->particles[$type] = new FloatingText($this, new Vector3($pos[0], $pos[1], $pos[2]));
            }
        }
        $level = $this->getServer()->getWorldManager()->getDefaultWorld();
        $level->setTime(7000);
        $level->stopTime();
        if (!$this->getServer()->loadLevel("transfare")) {
            $this->getServer()->generateLevel("transfare");
        }
        $this->getScheduler()->scheduleRepeatingTask(new Broadcaster($this), 30 * 20);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackUpdate([$this, "onUpdate"], []), 1);
    }

    public function onUpdate(): void
    {
        foreach ($this->updates as $key => $update) {
            if (!$update->onUpdate()) {
                unset($this->updates[$key]);
            }
        }
    }

    public function scheduleUpdate($updater)
    {
        $this->updates[] = $updater;
    }

    public static function getInstance(): Main
    {
        return self::$instance;
    }

    public function onCommand(CommandSender $p, Command $commad, string $label, array $args): bool
    {
        $notp = "§f§l[§6WC§f] §r§e§oHarap gunakan command ini di dalam game§r§f!";
        $notperm = "§f§l[§6WC§f] §r§e§oAnda tidak ada izin untuk menggunakan command ini§r§f!";
        switch (strtolower($commad->getName())) {
            case "profile":{
                if (!$p instanceof Player) {
                    $p->sendMessage($notp);
                    return false;
                }
                $plwpay = $this->getServer()->getPluginManager()->getPlugin("WoolPay");
                if (is_null($plwpay)) {
                    $money = "N/A";
                } else {
                    $money = $plwpay->getMoney($p->getName());
                }
                $plwrank = $this->getServer()->getPluginManager()->getPlugin("WoolRank");
                if (is_null($plwrank)) {
                    $rank = "N/A";
                    $online = "N/A";
                } else {
                    $rank = $plwrank->getPlayerRank($p->getName())->getName();
                    $online = $plwrank->getMinutes($p->getName());
                }
                $form = new SimpleForm(function (Player $p, $result) {
                });
                $form->setTitle("§e§lYour Profile");
                $form->setContent("§l§9»» §r§e§oName§r§f: {$p->getName()}\n\n§l§9»» §r§e§oMoney§r§f: {$money}"."§l§9$"."§r\n\n§l§9»» §r§e§oRank§r§f: {$rank}\n\n§l§9»» §r§e§oOnline Time§r§f: {$online} minutes§r\n\n§l§9»» §r§e§oLevel§r§f: {$this->getWorld($p)}\n\n§l§9»» §r§e§oKill§r§f: {$this->getKill($p)}\n\n§l§9»» §r§e§oDeath§r§f: {$this->getDeath($p)}\n\n§l§9»» §r§e§oBuild§r§f: {$this->getBuild($p)}\n\n§l§9»» §r§e§oBreak§r§f: {$this->getBreak($p)}");
                $p->sendForm($form);
                break;
            }
            case "survivaltp":{
                $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
                if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName()) {
                    if ($p->getMode() === 0) {
                        $p->setGameRule('naturalregeneration', false);
                        $p->actionAnimation(new JumpAnimation($p));
                    }
                    return true;
                }
                $this->getServer()->loadLevel("Survival");
                $p->teleport($this->getServer()->getWorldManager()->getWorldByName("Survival")->getSafeSpawn());
                break;
            }
            case "lobby":{
                if (!$p instanceof Player) {
                    $p->sendMessage($notp);
                    return false;
                }
                $p->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                $p->sendMessage("§f§l[§6WC§f] §r§e§oAnda berhasil teleport ke Lobby server§r§f.");
                break;
            }
            case "reward":{
                if (!$p instanceof Player) {
                    $p->sendMessage($notp);
                    return false;
                }
                $stat = $this->stats->getAll()[strtolower($p->getName())];
                $time = 86400 - (time() - $stat["time"]);
                if ((time() - $stat["time"]) < 86400) {
                    $hour = floor($time / 60 / 60);
                    $minute = floor($time / 60 - ($hour * 60));
                    $second = $time - ($hour * 60 * 60 + $minute * 60);
                    $p->sendMessage("§f§l[§6WC§f] §r§e§oAbsen harian anda masih cooldown harap tunggu§r§f ".$hour." jam : ".$minute." menit : ".$second." detik!");
                    return false;
                }
                $item1 = new Item(264, 0, 10);
                $item2 = new Item(364, 0, 32);
                if (!$p->getInventory()->canAddItem($item1) || !$p->getInventory()->canAddItem($item2)) {
                    $p->sendMessage("§f§l[§6WC§f] §r§e§oInventory anda saat ini full harap kurangi dan absen kembali§r§f!");
                    return false;
                }
                $p->getInventory()->addItem($item1);
                $p->getInventory()->addItem($item2);
                $this->stats->setNested(strtolower($p->getName()).".time", (time() + 86400));
                $this->stats->save();
                $p->sendMessage("§f§l[§6WC§f] §r§e§oAnda berhasil mengambil hadiah dari absen harian§r§f.");
                break;
            }
            case "rankinfo":{
                if (!$p instanceof Player) {
                    $p->sendMessage($notp);
                    return false;
                }
                $p->addTitle("§6§oComing Soon§r§f...", "");
            }
            case "leaderboard":{
                if (!$p instanceof Player) {
                    $p->sendMessage($notp);
                    return false;
                }
                if (!$p->isOp()) {
                    $p->sendMessage($notperm);
                    return false;
                }
                if (!isset($args[0])) {
                    $p->sendMessage("§f§l[§6WC§f] §r§e§oHarap gunakan command§r§f: /leaderboard [type].");
                    return false;
                }
                switch ($args[0]) {
                    default:{
                        $p->sendMessage("§f§l[§6WC§f] §r§e§oType leaderboard tidak ditemukan§r§f.");
                        break;
                    }
                    case "online":
                    case "money":
                    case "kill":
                    case "level":
                        $this->leaderboardset[$p->getName()] = $args[0];
                        $p->sendMessage("§f§l[§6WC§f] §r§e§oHarap tentukan lokasi leaderboard ".ucfirst(strtolower($args[0]))."§r§f!");
                        break;
                }
            }
        }
        return true;
    }

    public function onPlayerCreation(PlayerCreationEvent $e)
    {
        $e->setPlayerClass(PlayerSession::class);
    }

    public function onPlayerJoin(PlayerJoinEvent $e)
    {
        $p = $e->getPlayer();
        $e->setJoinMessage("");
        $p->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
        if (!$p instanceof SpecterPlayer) {
            $p->setGameRule('naturalregeneration', false);
            $p->actionAnimation(new OpeningAnimation($p));
        }
        $name = $p->getName();
        foreach ($this->getServer()->getOnlinePlayers() as $allp) {
            if ($allp->getName() !== $p->getName()) {
                if (!$p->isOp()) {
                    $allp->sendMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§omasuk server§r§f!");
                } else {
                    $allp->sendMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§omasuk server§r§f! §l(§9Staff§f)");
                }
            }
        }
        $lowerName = strtolower($name);
        if (!$this->stats->exists($lowerName)) {
            $stats = $this->stats;
            $stats->setNested($lowerName.".level", 1);
            $stats->setNested($lowerName.".exp", 0);
            $stats->setNested($lowerName.".expcount", 47);
            $stats->setNested($lowerName.".kill", 0);
            $stats->setNested($lowerName.".death", 0);
            $stats->setNested($lowerName.".build", 0);
            $stats->setNested($lowerName.".break", 0);
            $stats->setNested($lowerName.".time", time());
            $stats->save();
        }
        $this->setTag($p);
        $particles = $this->getParticles();
        foreach ($particles as $type => $particle) {
            $leaderboard = $this->getLeaderBoard($type);
            $particle->setText($leaderboard);
        }
    }

    public function onPlayerMove(PlayerMoveEvent $e)
    {
        $p = $e->getPlayer();
        if ($p instanceof Player) {
            $this->removeScoreboard($p, "objektName");
            if ($p->getMode() === 0) {
                $plwpay = $this->getServer()->getPluginManager()->getPlugin("WoolPay");
                if (is_null($plwpay)) {
                    $money = "N/A";
                } else {
                    $money = $plwpay->getMoney($p->getName());
                }
                $plwrank = $this->getServer()->getPluginManager()->getPlugin("WoolRank");
                if (is_null($plwrank)) {
                    $rank = "N/A";
                } else {
                    $rank = $plwrank->getPlayerRank($p->getName())->getName();
                }
                $level = $this->getWorld($p);
                if ($level === null) {
                    $level = "N/A";
                }
                $this->createScoreboard($p, "§9§k|||§r §e§lWool Craft §fS2§r §9§k|||", "objektName");
                $this->setScoreboardEntry($p, 0, "§9", "objektName");
                $this->setScoreboardEntry($p, 1, "§•§fName: §a".$p->getName(), "objektName");
                $this->setScoreboardEntry($p, 2, "§•§fMoney: §a".$money."§9§l$", "objektName");
                $this->setScoreboardEntry($p, 3, "§•§fRank: §a".$rank, "objektName");
                $this->setScoreboardEntry($p, 4, "§•§fLevel: §a".$level, "objektName");
                $this->setScoreboardEntry($p, 5, "§c", "objektName");
                $this->setScoreboardEntry($p, 6, "§•§fPing: §a".$p->getPing(), "objektName");
                $this->setScoreboardEntry($p, 7, "§•§fX: §a".floor($p->getX())." §fY: §a".floor($p->getY())." §fZ: §a".floor($p->getZ()), "objektName");
                $this->setScoreboardEntry($p, 8, "§•§fPlayers: §a".count($this->getServer()->getOnlinePlayers())."/".$this->getServer()->getMaxPlayers(), "objektName");
                $this->setScoreboardEntry($p, 9, "§1", "objektName");
                $this->setScoreboardEntry($p, 10, "§•§awool-craft-s2.tk", "objektName");
                $this->setScoreboardEntry($p, 11, "§•§a19213", "objektName");
            }
            $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
            if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName()) {
                if ($p->getY() < -10) {
                    $p->teleport($defaultLevel->getSafeSpawn()->add(0.5, 1, 0.5));
                }
            }
        }
    }

    public function onPlayerRespawn(PlayerRespawnEvent $e)
    {
        $e->getPlayer()->addTitle("§l§cAnda Mati§f!!!", "§6§oTelah Direspawn Ulang§r§f!");
    }

    public function onPlayerDeath(PlayerDeathEvent $e)
    {
        $p = $e->getPlayer();
        $name = $p->getName();
        $e->setKeepInventory(true);
        if ($p instanceof Player) {
            $stats = $this->stats;
            $stats->setNested(strtolower($p->getName()).".death", $stats->getAll()[strtolower($p->getName())]["death"] + 1);
            $cause = $p->getLastDamageCause();
            if ($cause instanceof EntityDamageByEntityEvent) {
                $killer = $cause->getDamager();
                if ($killer instanceof Player) {
                    $e->setDeathMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§odibunuh oleh§r§f {$killer->getName()}!");
                    $this->addExp($killer, 10);
                    $stats->setNested(strtolower($killer->getName()).".kill", $stats->getAll()[strtolower($killer->getName())]["kill"] + 1);
                } else {
                    $e->setDeathMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§otelah mati§r§f!");
                }
            } else {
                $e->setDeathMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§otelah mati§r§f!");
            }
            $stats->save();
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $e)
    {
        $p = $e->getPlayer();
        if (!$p->isOp()) {
            $e->setQuitMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§okeluar server§r§f!");
        } else {
            $e->setQuitMessage("§f§l[§6WC§f] §r§f{$p->getName()} §e§okeluar server§r§f! §l(§9Staff§f)");
        }
        $this->removeBossBar($p);
        unset($this->breakTimes[$p->getRawUniqueId()]);
    }

    public function onEntityDamage(EntityDamageEvent $e)
    {
        if (($p = $e->getEntity()) instanceof Player) {
            $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
            if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName()) {
                $e->setCancelled();
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $e)
    {
        $stats = $this->stats;
        $p = $e->getPlayer();
        $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName() && !$p->isOp()) {
            $e->setCancelled();
        }
        if (!$e->isCancelled()) {
            $this->addExp($p);
            $stats->setNested(strtolower($p->getName()).".build", $stats->getAll()[strtolower($p->getName())]["build"] + 1);
            $stats->save();
        }
    }

    public function onBlockBreak(BlockBreakEvent $e)
    {
        $stats = $this->stats;
        $p = $e->getPlayer();
        if (!$e->getInstaBreak()) {
            do {
                if (!isset($this->breakTimes[$uuid = $p->getRawUniqueId()])) {
                    foreach ($this->getServer()->getOnlinePlayers() as $op) {
                        if ($op->isOp()) {
                            $op->sendPopup("§c§oPlayer dengan nama §r§f{$p->getName()} §c§omencoba untuk menghancurkan tanpa event break§r§f, §e§oharap curigai§r§f!");
                        }
                    }
                    $e->setCancelled();
                    break;
                }
                $target = $e->getBlock();
                $item = $e->getItem();
                $expectedTime = ceil($target->getBreakTime($item) * 20);
                if ($p->hasEffect(Effect::HASTE)) {
                    $expectedTime *= 1 - (0.2 * $p->getEffect(Effect::HASTE)->getEffectLevel());
                }
                if ($p->hasEffect(Effect::MINING_FATIGUE)) {
                    $expectedTime *= 1 + (0.3 * $p->getEffect(Effect::MINING_FATIGUE)->getEffectLevel());
                }
                $expectedTime -= 1;
                $actualTime = ceil(microtime(true) * 20) - $this->breakTimes[$uuid = $p->getRawUniqueId()];
                if ($actualTime < $expectedTime) {
                    foreach ($this->getServer()->getOnlinePlayers() as $op) {
                        if ($op->isOp()) {
                            $op->sendPopup("§c§oPlayer dengan nama §r§f{$p->getName()} §c§omencoba untuk menghancurkan block terlalu cepat§r§f, §c§oharap curigai§r§f!");
                        }
                    }
                    $e->setCancelled();
                    break;
                }
                unset($this->breakTimes[$uuid]);
            } while (false);
        }
        $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName() && !$p->isOp()) {
            $e->setCancelled();
        }
        if (!$e->isCancelled()) {
            $this->addExp($p);
            $stats->setNested(strtolower($p->getName()).".break", $stats->getAll()[strtolower($p->getName())]["break"] + 1);
            $stats->save();
        }
    }

    public function onPlayerBucketEmpty(PlayerBucketEmptyEvent $e)
    {
        $stats = $this->stats;
        $p = $e->getPlayer();
        $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName()) {
            $e->setCancelled();
        }
    }

    public function onEntityExplode(EntityExplodeEvent $e): void
    {
        $entity = $e->getEntity();
        $world = $entity->getWorld();
        $defaultWorld = $this->getServer()->getWorldManager()->getDefaultWorld();
        if ($world->getFolderName() === $defaultWorld->getFolderName()) {
            $e->setCancelled(true);
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $e)
    {
        $stats = $this->stats;
        $p = $e->getPlayer();
        $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName() && !$p->isOp()) {
            $e->setCancelled();
        }
    }

    public function onPlayerExhaust(PlayerExhaustEvent $e)
    {
        $stats = $this->stats;
        $p = $e->getPlayer();
        $defaultLevel = $this->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultLevel->getFolderName() === $p->getWorld()->getFolderName()) {
            $e->setCancelled();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $e)
    {
        $p = $e->getPlayer();
        if ($e->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
            $this->breakTimes[$p->getRawUniqueId()] = floor(microtime(true) * 20);
        }
        $block = $e->getBlock();
        if (isset($this->leaderboardset[$p->getName()])) {
            $data = $this->data;
            $data->setNested("leaderboard.".$this->leaderboardset[$p->getName()], [$block->getX() + 0.5, $block->getY() + 3, $block->getZ() + 0.5]);
            $data->save();
            $p->sendMessage("§f§l[§6WC§f] §r§e§oAnda berhasil menentukan lokasi leaderboard ".ucfirst(strtolower($this->leaderboardset[$p->getName()]))."§r§f.");
            unset($this->leaderboardset[$p->getName()]);
            $e->setCancelled();
        }
    }

    public function onLevelChange(EntityLevelChangeEvent $e)
    {
        if (($p = $e->getEntity()) instanceof Player) {
            if ($e->getTarget()->getFolderName() === $this->getServer()->getWorldManager()->getDefaultWorld()->getFolderName()) {
                $this->getScheduler()->scheduleDelayedTask(new RunUpdate($p, 1), 100);
            }
        }
    }

    public function addBossBar(Player $p)
    {
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->type = "minecraft:slime";
        $pk->metadata = [
         Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, ((1 << Entity::DATA_FLAG_INVISIBLE) | (1 << Entity::DATA_FLAG_IMMOBILE))],
         Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, '']
        ];
        $pk->position = new Vector3();
        $p->sendDataPacket($pk);
        $this->sendBossPacket($p, '', 0, 100, 0);
    }

    public function removeBossBar(Player $p)
    {
        $this->sendBossPacket($p, '', 0, 100, 2);
        $pk = new RemoveActorPacket();
        $pk->entityUniqueId = $this->entityId;
        $p->sendDataPacket($pk);
    }

    public function sendBossPacket(Player $p, $title, $loop, $max, $type = 5)
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = $type;
        if ($type !== 4) {
            $pk->title = $title;
        }
        if ($type === 0 || $type === 4) {
            $pk->healthPercent = $loop / $max;
            if ($type === 0) {
                $pk->unknownShort = $pk->color = $pk->overlay = 0;
            }
        }
        $p->sendDataPacket($pk);
    }

    public function getColor($level)
    {
        $color = "a";
        if ($level >= 5) {
            $color = "e";
        }
        if ($level >= 10) {
            $color = "6";
        }
        if ($level >= 25) {
            $color = "9";
        }
        if ($level >= 50) {
            $color = "1";
        }
        if ($level >= 100) {
            $color = "c";
        }
        return $color;
    }

    public function setTag(Player $p)
    {
        $color = $this->getColor($this->getWorld($p));
        $p->setDisplayName("§f§l".$this->getWorld($p)." §r§".$color.$p->getName());
        $p->save();
    }

    public function getWorld(Player $p)
    {
        if ($this->stats->exists(strtolower($p->getName()))) {
            return $this->stats->getAll()[strtolower($p->getName())]["level"];
        }
        return null;
    }

    public function addExp(Player $p, $exp = 1)
    {
        $stats = $this->stats;
        $lowerName = strtolower($p->getName());
        $stats->setNested($lowerName.".exp", $stats->getAll()[$lowerName]["exp"] + $exp);
        $stats->save();
        $this->runLevel($p);
    }

    public function runLevel(Player $p)
    {
        $stats = $this->stats;
        $lowerName = strtolower($p->getName());
        $exp = $this->getExp($p);
        $expcount = $this->getExpCount($p);
        if ($exp >= $expcount) {
            $stats->setNested($lowerName.".level", $stats->getAll()[$lowerName]["level"] + 1);
            $stats->setNested($lowerName.".exp", 0);
            $stats->setNested($lowerName.".expcount", $stats->getAll()[$lowerName]["expcount"] + 32);
            $stats->save();
            $this->setTag($p);
            $p->addTitle("§l§eLEVEL UP§r§f!!!", "§6§oLevel ".$this->getWorld($p), 1, 100, 50);
        }
    }

    public function getExp(Player $p)
    {
        return $this->stats->getAll()[strtolower($p->getName())]["exp"];
    }

    public function getExpCount(Player $p)
    {
        return $this->stats->getAll()[strtolower($p->getName())]["expcount"];
    }

    public function getKill(Player $p)
    {
        return $this->stats->getAll()[strtolower($p->getName())]["kill"];
    }

    public function getDeath(Player $p)
    {
        return $this->stats->getAll()[strtolower($p->getName())]["death"];
    }

    public function getBuild(Player $p)
    {
        return $this->stats->getAll()[strtolower($p->getName())]["build"];
    }

    public function getBreak(Player $p)
    {
        return $this->stats->getAll()[strtolower($p->getName())]["break"];
    }

    public function setScoreboardEntry(Player $p, $score, $msg, $objName)
    {
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $objName;
        $entry->type = 3;
        $entry->customName = " $msg   ";
        $entry->score = $score;
        $entry->scoreboardId = $score;
        $pk = new SetScorePacket();
        $pk->type = 0;
        $pk->entries[$score] = $entry;
        $p->sendDataPacket($pk);
    }

    public function createScoreboard(Player $p, $title, $objName, $slot = "sidebar", $order = 0)
    {
        $pk = new SetDisplayObjectivePacket();
        $pk->displaySlot = $slot;
        $pk->objectiveName = $objName;
        $pk->displayName = $title;
        $pk->criteriaName = "dummy";
        $pk->sortOrder = $order;
        $p->sendDataPacket($pk);
    }

    public function removeScoreboard(Player $p, $objName)
    {
        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = $objName;
        $p->sendDataPacket($pk);
    }

    public function getLeaderBoard($type): string
    {
        $prefix = "";
        switch ($type) {
            case "online":{
                $plwrank = $this->getServer()->getPluginManager()->getPlugin("WoolRank");
                if (!is_null($plwrank)) {
                    $data = $plwrank->time;
                    $prefix = " minutes";
                } else {
                    $data = null;
                }
                break;
            }
            case "money":{
                $plwpay = $this->getServer()->getPluginManager()->getPlugin("WoolPay");
                if (!is_null($plwpay)) {
                    $data = $plwpay->money->getAll();
                    $prefix = "§l§9$"."§r";
                } else {
                    $data = null;
                }
                break;
            }
            case "kill":{
                $data = [];
                foreach ($this->stats->getAll() as $name => $stats) {
                    $data[$name] = $stats["kill"];
                }
                $prefix = "§r§ax";
                break;
            }
            case "level":{
                $data = [];
                foreach ($this->stats->getAll() as $name => $stats) {
                    $data[$name] = $stats["level"];
                }
                break;
            }
            default:{
                $data = null;
            }
        }
        if ($data !== null) {
            $message = "";
            $title = "§f§l§oLEADERBOARD TOP ".strtoupper($type)."\n§e§l>>>>>>>>>>>>>>><<<<<<<<<<<<<<<§r\n";
            if (count($data) > 0) {
                arsort($data);
                $i = 0;
                foreach ($data as $name => $many) {
                    $message .= "§r§e".($i + 1)."§f). §6§o".$name." §f=> ".$many.$prefix."\n";
                    if ($i >= 9) {
                        break;
                    }
                    ++$i;
                }
            }
            $return = (string) $title.$message."\n§e§l>>>>>>>>>>>>>>><<<<<<<<<<<<<<<";
            return $return;
        }
        return "";
    }

    public function getParticles(): array
    {
        return $this->particles;
    }

}
