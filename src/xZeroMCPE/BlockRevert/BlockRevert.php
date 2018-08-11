<?php
/**
 * Created by PhpStorm.
 * User: xZero
 * Date: 8/10/2018
 * Time: 11:10 PM
 */

namespace xZeroMCPE\BlockRevert;


use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class BlockRevert extends PluginBase implements Listener
{

    public $blockTicks = [];
    public $config;

    public static $instance;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if (file_exists($this->getDataFolder() . "config.yml")) {
            $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            if ($this->config->exists('Data')) {
                $this->blockTicks = json_decode($this->config->get("Data"), true);
            }
        } else {
            $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $this->config->set("Interval in seconds", 10);
            $this->config->set("Affected blocks {Empty = all}", []);
            $this->config->set("Level to work on", ['world']);
        }
        self::$instance = $this;

        // You never knew you could do that? But yeah, you can. Making a class file for this would be useless. I'm not you, creating useless files... :P
        $class = new class extends Task
        {

            public function onRun(int $currentTick)
            {
                if (count(BlockRevert::getInstance()->blockTicks) != 0) {
                    foreach (BlockRevert::getInstance()->blockTicks as $index => $data) {
                        if ($data['Time'] != 0) {
                            BlockRevert::getInstance()->blockTicks[$index]['Time']--;
                        } else {
                            $loc = explode(":", $data['Location']);
                            BlockRevert::getInstance()->getServer()->getLevelByName($data['Level'])->setBlock(new Vector3((int)$loc[0], (int)$loc[1], (int)$loc[2]), BlockFactory::get($data['Block'], $data['Damage']));
                            unset(BlockRevert::getInstance()->blockTicks[$index]);
                        }
                    }
                }
            }
        };
        $this->getScheduler()->scheduleRepeatingTask($class, 20);
    }

    public function onDisable()
    {
        if (count($this->blockTicks) != 0) {
            $this->config->set('Data', json_encode($this->blockTicks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->config->remove('Data');
        }
        $this->config->save();
    }

    public static function getInstance(): BlockRevert
    {
        return self::$instance;
    }

    public function onPlace(BlockPlaceEvent $event)
    {

        $player = $event->getPlayer();

        if (in_array($player->getLevel()->getName(), $this->config->get("Level to work on"))) {
            if (count($this->config->get('Affected blocks {Empty = all}')) == 0) {
                $this->blockTicks[] = [
                    "Block" => $event->getBlockReplaced()->getId(),
                    "Damage" => $event->getBlock()->getDamage(),
                    "Location" => $event->getBlock()->getX() . ":" . $event->getBlock()->getY() . ":" . $event->getBlock()->getZ(),
                    "Level" => $event->getBlock()->getLevel()->getName(),
                    "Time" => $this->config->get("Interval in seconds")
                ];
            } else {
                if (in_array($event->getBlock()->getId(), $this->config->get("Affected blocks {Empty = all}"))) {
                    $this->blockTicks[] = [
                        "Block" => $event->getBlockReplaced()->getId(),
                        "Damage" => $event->getBlock()->getDamage(),
                        "Location" => $event->getBlock()->getX() . ":" . $event->getBlock()->getY() . ":" . $event->getBlock()->getZ(),
                        "Level" => $event->getBlock()->getLevel()->getName(),
                        "Time" => $this->config->get("Interval in seconds")
                    ];
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event)
    {

        $player = $event->getPlayer();

        if (in_array($player->getLevel()->getName(), $this->config->get("Level to work on"))) {
            if ($player->getInventory()->getItemInHand()->getId() == Item::BUCKET) {
                $y = $event->getBlock()->getY() +1;
                $this->blockTicks[] = [
                    "Block" => 0,
                    "Damage" => 0,
                    "Location" => $event->getBlock()->getX() . ":" . $y . ":" . $event->getBlock()->getZ(),
                    "Level" => $player->getLevel()->getName(),
                    "Time" => $this->config->get("Interval in seconds")
                ];
            }
        }
    }
}