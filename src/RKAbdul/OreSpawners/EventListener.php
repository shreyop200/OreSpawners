<?php

declare(strict_types=1);

namespace RKAbdul\OreSpawners;

use pocketmine\block\Block;
use pocketmine\tile\Tile;
use pocketmine\level\sound\FizzSound;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as TF;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;

use RKAbdul\OreSpawners\libs\denisgladkikh\tile\SimpleTile;

class EventListener implements Listener {

    /** @var Main */
    private $plugin;

    private $cfg;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->cfg = $this->plugin->getConfig()->getAll();
    }

    public function onBlockUpdate(BlockUpdateEvent $event): void {
        $block = $event->getBlock();
        $bbelow = $block->getLevel()->getBlock($event->getBlock()->floor()->down());
        $blocks = array_values($this->plugin->getConfig()->get("ore-generator-blocks"));

        if (in_array($bbelow->getId(), $blocks)) {
            $tile = $event->getBlock()->getLevel()->getTile($bbelow);
            if (!$tile instanceof SimpleTile) {
                return;
            }

            $ore = $this->checkBlock($bbelow);
            $delay = $this->getDelay($bbelow);
            if (!$event->isCancelled()) {
                $event->setCancelled(true);
                if ($event->getBlock()->getId() == $ore->getId()) {
                    return;
                }

                $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($event, $ore): void {
                    if ($event->getBlock()->getLevel() !== null) {
                        $event->getBlock()->getLevel()->setBlock($event->getBlock()->floor(), $ore, false, true);
                        if ($this->cfg["fizz-sound"] == true) {
                            $event->getBlock()->getLevel()->addSound(new FizzSound($event->getBlock()->asVector3()));
                        }
                    }
                }), intval($delay));
            }
        }
    }

    public function checkBlock(Block $bbelow): ?Block {
        $bbid = $bbelow->getId();
        $ores = [
            "coal" => Block::COAL_ORE,
            "iron" => Block::IRON_ORE,
            "gold" => Block::GOLD_ORE,
            "diamond" => Block::DIAMOND_ORE,
            "emerald" => Block::EMERALD_ORE,
            "lapis" => Block::LAPIS_ORE,
            "redstone" => Block::REDSTONE_ORE,
        ];

        if (isset($ores[$bbid])) {
            return Block::get($ores[$bbid]);
        }

        return null;
    }

    public function getDelay(Block $block): float {
        $tile = $block->getLevel()->getTile($block->asVector3());
        $stacked = $tile->stacked ?? 1;
        $base = intval($this->cfg["base-delay"]);
        return ($base / $stacked) * 20;
    }

    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $block = $event->getBlock();
        $item = $event->getItem();
        $blocks = [];

        foreach (array_values($this->plugin->getConfig()->get("ore-generator-blocks")) as $blockID) {
            array_push($blocks, $blockID);
        }

        if (in_array($block->getId(), $blocks)) {
            if ($item->getNamedTag()->hasTag("orespawner")) {
                $tile = $event->getPlayer()->getLevel()->getTile($event->getBlock()->asVector3());
                if (!$tile instanceof SimpleTile) {
                    $tileinfo = new TileInfo($event->getBlock(), ["id" => "simpleTile", "stacked" => 1]);
                    new SimpleTile($event->getPlayer()->getLevel(), $tileinfo);
                }
            }
        }
        
        $block = $event->getBlock();
        $bbelow = $block->getLevel()->getBlock($event->getBlock()->floor()->down(1));
        if ($this->checkBlock($bbelow)) {
            $event->setCancelled(true);
            $event->getPlayer()->sendMessage(Tf::RED . "You can not place blocks over an OreSpawner!");
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): bool
    {
        if ($this->cfg["stacking"] == false || $event->isCancelled()) return false;
        $item = $event->getItem();
        $player = $event->getPlayer();
        $blocks = [];
        foreach (array_values($this->plugin->getConfig()->get("ore-generator-blocks")) as $blockID) {
            array_push($blocks, $blockID);
        }
        if (in_array($event->getBlock()->getId(), $blocks)) {
            $tile = $event->getPlayer()->getLevel()->getTile($event->getBlock());
            if ($tile instanceof SimpleTile) {
                if (!$player->getGamemode() == 1) {
                    $stacked = $tile->getData("stacked")->getValue();
                    if (in_array($item->getId(), $blocks) && $item->getNamedTag()->hasTag("orespawner")) {
                        if ($event->getBlock()->getId() == $item->getId()) {
                            if (!($stacked >= intval($this->cfg["max"]))) {
                                $event->setCancelled(true);
                                $tile->setData("stacked", $stacked + 1);
                                $item->setCount($item->getCount() - 1);
                                $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item);
                                $player->sendMessage(str_replace("&", "§", $this->cfg["gen-added"] ?? "&aSuccessfully stacked a orespawner"));
                                return true;
                            }
                            $player->sendMessage(str_replace("&", "§", $this->cfg["limit-reached"] ?? "&cYou can't stack anymore orespawners, you have reached the limit"));
                            return false;
                        }
                        $player->sendMessage("§cPlease hold the right type of OreSpawner to stack");
                        return false;
                    }
                    $player->sendMessage("§aThere are currently " . TF::YELLOW . $stacked . " §astacked OreSpawners");
                    return false;
                }
                $player->sendMessage(TF::RED . "You can only using stacking system in Survival.");
                return false;
            }
            return false;
        }
        return false;
    }

    public function getTile(Vector3 $pos): ?Tile
    {
        return $this->getTileAt((int)floor($pos->x), (int)floor($pos->y), (int)floor($pos->z));
    }

    public function onBlockBreak(BlockBreakEvent $event)
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $bbelow = $block->getLevel()->getBlock($event->getBlock()->floor()->down(1));
        $blocks = [];
        foreach (array_values($this->plugin->getConfig()->get("ore-generator-blocks")) as $blockID) {
            array_push($blocks, $blockID);
        }
        if ($event->isCancelled()) return;
        if (in_array($event->getBlock()->getId(), $blocks)) {
            $tile = $event->getBlock()->getLevel()->getTile($block);
            if (!$tile instanceof SimpleTile) return;
            $tile = $player->getLevel()->getTile($block->asVector3());
            $type = $this->checkSpawner($block);
            $count = $tile instanceof SimpleTile ? $tile->getData("stacked")->getValue() : 1;
            $orespawner = $this->plugin->createOreSpawner($type, $count);
            $drops = array();
            $drops[] = $orespawner;
            $event->setDrops($drops);
        } else if (in_array($bbelow->getId(), $blocks)) {
            if ($this->cfg["drop-xp"] == false) {
                $event->setXpDropAmount(0);
            }
        }
    }

    public function checkSpawner(Block $bbelow)
    {
        $bbid = $bbelow->getId();
        $coalid = intval($this->cfg["ore-generator-blocks"]["coal"]);
        $ironid = intval($this->cfg["ore-generator-blocks"]["iron"]);
        $goldid = intval($this->cfg["ore-generator-blocks"]["gold"]);
        $diamondid = intval($this->cfg["ore-generator-blocks"]["diamond"]);
        $emeraldid = intval($this->cfg["ore-generator-blocks"]["emerald"]);
        $lapizid = intval($this->cfg["ore-generator-blocks"]["lapis"]);
        $redstoneid = intval($this->cfg["ore-generator-blocks"]["redstone"]);
        switch ($bbid) {
            case $coalid:
                $ore = "coal";
                break;
            case $ironid:
                $ore = "iron";
                break;
            case $goldid:
                $ore = "gold";
                break;
            case $diamondid:
                $ore = "diamond";
                break;
            case $emeraldid:
                $ore = "emerald";
                break;
            case $lapizid:
                $ore = "lapis";
                break;
            case $redstoneid:
                $ore = "redstone";
                break;
        }
        if (isset($ore)) {
            return $ore;
        }
        return false;
    }
}
