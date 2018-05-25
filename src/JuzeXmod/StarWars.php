
<?php

namespace JuzeXmod\StarWars;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\utils\TextFormat as TE;
use pocketmine\utils\Config;

use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\player\PlayerQuitEvent;
use JuzeXmod\StarWars\ResetMap;
use pocketmine\entity\Effect;

class StarWars extends PluginBase implements Listener {
  
    public $prefix = TE::GREEN . "[" . TE::AQUA . TE::RED . "Star" . TE::AQUA . "Wars" . TE::RESET . TE::YELLOW . "]";
    public $mode = 0;
    public $arenas = array();
    public $currentLevel = "";
  
    public function onEnable()
    {
        $this->getLogger()->info("$this->prefix By JUZEXMOD");
        $this->getServer()->getPluginManager()->registerEvents($this ,$this);
        $this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if(!empty($this->economy))
        {
            $this->api = EconomyAPI::getInstance();
        }
        @mkdir($this->getDataFolder());
        $config2 = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
        $config2->save();
        $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
        if($config->get("money")==null)
        {
            $config->set("money",500);
        }
        if($config->get("arenas")!=null)
        {
            $this->arenas = $config->get("arenas");
        }
        foreach($this->arenas as $lev)
        {
            $this->getServer()->loadLevel($lev);
        }
        $items = array(array(1,0,30),array(1,0,20),array(3,0,15),array(3,0,25),array(4,0,35),array(4,0,15),array(260,0,5),array(261,0,1),array(262,0,6),array(267,0,1),array(268,0,1),array(272,0,1),array(276,0,1),array(283,0,1),array(297,0,3),array(298,0,1),array(299,0,1),array(300,0,1),array(301,0,1),array(303,0,1),array(304,0,1),array(310,0,1),array(313,0,1),array(314,0,1),array(315,0,1),array(316,0,1),array(317,0,1),array(320,0,4),array(354,0,1),array(364,0,4),array(366,0,5),array(391,0,5));
        if($config->get("chestitems")==null)
        {
            $config->set("chestitems",$items);
        }
        $config->save();
        $playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
        $playerlang->save();
        $lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
        if($lang->get("en")==null)
        {
            $messages = array();
            $messages["kill"] = "$this->prefix was killed by";
            $messages["cannotjoin"] = "$this->prefix You can't join.";
            $messages["seconds"] = "$this->prefix seconds to start";
            $messages["won"] = "$this->prefix §fWon StaeWars in arena: §b";
            $messages["deathmatchminutes"] = "$this->prefix minutes to DeathMatch!";
            $messages["deathmatchseconds"] = "$this->prefix seconds to DeathMatch!";
            $messages["chestrefill"] = "$this->prefix The chest have been refilled!";
            $messages["remainingminutes"] = "$this->prefix minutes remaining!";
            $messages["remainingseconds"] = "$this->prefix seconds remaining!";
            $messages["nowinner"] = "$this->prefix §fNo winner in arena: §b";
            $messages["moreplayers"] = "$this->prefix Wait Players joiin";
            $lang->set("en",$messages);
        }
        $lang->save();
        $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
        $slots->save();
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10);
    }
  
  public function onDeath(PlayerDeathEvent $event){
        $jugador = $event->getEntity();
        $map = $jugador->getLevel()->getFolderName();
        if(in_array($map,$this->arenas))
        {
            if($event->getEntity()->getLastDamageCause() instanceof EntityDamageByEntityEvent)
            {
                $asassin = $event->getEntity()->getLastDamageCause()->getDamager();
                if($asassin instanceof Player){
                    $event->setDeathMessage("");
                    foreach($jugador->getLevel()->getPlayers() as $pl){
                        $playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
                        $lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
                        $toUse = $lang->get($playerlang->get($pl->getName()));
                        $muerto = $jugador->getNameTag();
                        $asesino = $asassin->getNameTag();
                    }
                }
            }
            $jugador->setNameTag($jugador->getName());
        }
    }

  public function onMove(PlayerMoveEvent $event)
    {
        $player = $event->getPlayer();
        $level = $player->getLevel()->getFolderName();
        if(in_array($level,$this->arenas))
        {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $sofar = $config->get($level . "StartTime");
            if($sofar > 0)
            {
                $to = clone $event->getFrom();
                $to->yaw = $event->getTo()->yaw;
                $to->pitch = $event->getTo()->pitch;
                $event->setTo($to);
            }
        }
    }
  
  public function onLog(PlayerLoginEvent $event)
    {
        $player = $event->getPlayer();
        $playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
        if($playerlang->get($player->getName())==null)
        {
            $playerlang->set($player->getName(),"en");
            $playerlang->save();
        }
        if(in_array($player->getLevel()->getFolderName(),$this->arenas))
        {
            $player->getInventory()->clearAll();
            $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
            $this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $player->teleport($spawn,0,0);
        }
    }
  
  public function onQuit(PlayerQuitEvent $event)
    {
        $pl = $event->getPlayer();
        $level = $pl->getLevel()->getFolderName();
        if(in_array($level,$this->arenas))
        {
            $pl->removeAllEffects();
            $pl->getInventory()->clearAll();
            $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
            $pl->setNameTag($pl->getName());
            if($slots->get("slot1".$level)==$pl->getName())
            {
                $slots->set("slot1".$level, 0);
            }
            if($slots->get("slot2".$level)==$pl->getName())
            {
                $slots->set("slot2".$level, 0);
            }
            if($slots->get("slot3".$level)==$pl->getName())
            {
                $slots->set("slot3".$level, 0);
            }
            if($slots->get("slot4".$level)==$pl->getName())
            {
                $slots->set("slot4".$level, 0);
            }
            if($slots->get("slot5".$level)==$pl->getName())
            {
                $slots->set("slot5".$level, 0);
            }
            if($slots->get("slot6".$level)==$pl->getName())
            {
                $slots->set("slot6".$level, 0);
            }
            if($slots->get("slot7".$level)==$pl->getName())
            {
                $slots->set("slot7".$level, 0);
            }
            if($slots->get("slot8".$level)==$pl->getName())
            {
                $slots->set("slot8".$level, 0);
            }
            if($slots->get("slot9".$level)==$pl->getName())
            {
                $slots->set("slot9".$level, 0);
            }
            if($slots->get("slot10".$level)==$pl->getName())
            {
                $slots->set("slot10".$level, 0);
            }
            if($slots->get("slot11".$level)==$pl->getName())
            {
                $slots->set("slot11".$level, 0);
            }
            if($slots->get("slot12".$level)==$pl->getName())
            {
                $slots->set("slot12".$level, 0);
            }
            $slots->save();
        }
    }
  
  public function onBlockBreak(BlockBreakEvent $event)
    {
        $player = $event->getPlayer();
        $level = $player->getLevel()->getFolderName();
        $block = $event->getBlock()->getId();
        if(in_array($level,$this->arenas))
        {
            $event->setCancelled(true);
        }
  }
  
  public function onBlockPlace(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();
        $level = $player->getLevel()->getFolderName();
        if(in_array($level,$this->arenas))
        {
            $event->setCancelled(true);
        }
    }
  
  public function onDamage(EntityDamageEvent $event)
    {
        if($event instanceof EntityDamageByEntityEvent)
        {
            $player = $event->getEntity();
            $damager = $event->getDamager();
            if($player instanceof Player)
            {
                if($damager instanceof Player)
                {
                    $level = $player->getLevel()->getFolderName();
                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                    if($config->get($level . "PlayTime") != null)
                    {
                        if($config->get($level . "PlayTime") > 750)
                        {
                            $event->setCancelled(true);
                        }
                    }
                }
            }
        }
    }
  
  public function onCommand(CommandSender $player, Command $cmd, $label, array $args) {
        $lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
        switch($cmd->getName()){
            case "swdp":
                if($player->isOp())
                {
                    if(!empty($args[0]))
                    {
                        if($args[0]=="addarena")
                        {
                            if(!empty($args[1]))
                            {
                                if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1]))
                                {
                                    $this->getServer()->loadLevel($args[1]);
                                    $this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
                                    array_push($this->arenas,$args[1]);
                                    $this->currentLevel = $args[1];
                                    $this->mode = 1;
                                    $player->sendMessage($this->prefix . "§l§aTouch the spawn points!");
                                    $player->setGamemode(1);
                                    $player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
                                    $name = $args[1];
                                    $this->zipper($player, $name);
                                }
                                else
                                {
                                    $player->sendMessage($this->prefix . "§l§b$args[1] §c Not World.");
                                }
                            }
                            else
                            {
                                $player->sendMessage($this->prefix . "§l§cERROR missing parameters.");
                            }
                        }
                        else
                        {
                            $player->sendMessage($this->prefix . "§l§cThis not command.");
                        }
                    }
                    else
                    {
                        $player->sendMessage($this->prefix . "§l§aSkyOreDP Commands!");
                        $player->sendMessage($this->prefix . "§l§6/sodp addarena [world]: Create a so game!");
                        $player->sendMessage($this->prefix . "§l§6/ranksw [rank] [player]: ranks(so many)!");
                        $player->sendMessage($this->prefix . "§l§6/sostart: start the game");
                        $player->sendMessage($this->prefix . "§l§6/lang: Select language");
                    }
                }
                else
                {
                }
                return true;
            
            case "swdpstart":
                if($player->isOp())
                {
                    $player->sendMessage("§aStarting in 10 sec...");
                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                    $config->set("arenas",$this->arenas);
                    foreach($this->arenas as $arena)
                    {
                        $config->set($arena . "PlayTime", 780);
                        $config->set($arena . "StartTime", 10);
                    }
                    $config->save();
                }
                return true;
        }
    }
}
