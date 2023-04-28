<?php

declare(strict_types=1);

namespace RKAbdul\OreSpawners;

use DenielWorld\EzTiles\EzTiles;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase
{
    public const VERSION = 4;

    private $cfg;
    
    /**
     * Disables the plugin if config version is below const VERSION.
     *
     * @return void
     */
    public function onEnable(): void
    {
        $this->cfg = $this->getConfig()->getAll();

        if ($this->cfg["version"] < self::VERSION) {
            $this->getLogger()->error("Config Version is outdated! Please delete your current config file!");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        EzTiles::register($this);
        EzTiles::init();
    }
    
    /**
     * Checks if the OreSpawner command is run.
     *
     * @param  CommandSender $sender
     * @param  Command $command
     * @param  string $label
     * @param  array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "orespawner") {
            $typesArray = ["coal", "lapis", "iron", "gold", "diamond", "emerald", "redstone"];
            if (!$sender->hasPermission("orespawner.give")) {
                $sender->sendMessage(TF::RED . "You do not have permission to use this command!");
                return false;
            }

            if (!isset($args[0])) {
                $sender->sendMessage(TF::RED . "You must provide some arguments!");
                return false;
            } elseif (isset($args[2]) && !$this->getServer()->getPlayer($args[2])) {
                $sender->sendMessage(TF::RED . "You must provide a valid player!");
                return false;
            } elseif (!isset($args[0]) || !in_array(strtolower($args[0]), $typesArray, true)) {
                $sender->sendMessage(TF::RED . "You must enter a valid ore type!");
                return false;
            } elseif (isset($args[1]) && !is_numeric($args[1])) {
                $sender->sendMessage(TF::RED . "You must provide a valid amount!");
                return false;
            }
            $player = isset($args[2]) ? $this->getServer()->getPlayer($args[2]) : $sender;
            if (!$player instanceof Player) {
                return false;
            }
            $ore = strtolower($args[0]);
            $amount = isset($args[1]) ? intval($args[1]) : 1;
            $orespa = $this->createOreSpawner($ore, $amount);
            $player->getInventory()->addItem($orespa);
            return true;
        }
        return false;
    }
    
    /**
     * Creates OreSpawners from given arguments.
     *
     * @param  string $ore
     * @param  int $amount
     * @return object
     */
    public function createOreSpawner(string $ore, int $amount)
    { 
        $gen = $this->cfg["ore-generator-blocks"][$ore];
        $gencreated = ItemFactory::get(intval($gen), 0, $amount);
        $name = str_replace(["{ore}", "&"], [$ore, "§"], $this->cfg["ore-generators-name"] ?? "&a$ore ore spawner");
        $gencreated->setCustomName($name);
        $lore = str_replace(["{ore}", "&"], [$ore, "§"], $this->cfg["ore-generators-lore"] ?? "Place it down, and ore blocks will spawn above it");
        $gencreated->setLore([$lore]);
        $gencreated->setNamedTagEntry(new StringTag("orespawner", "true"));
        return $gencreated;
    }
}
