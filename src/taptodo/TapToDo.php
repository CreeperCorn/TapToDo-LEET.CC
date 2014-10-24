<?php
namespace taptodo;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class TapToDo extends PluginBase implements CommandExecutor, Listener{
    public $s, $b, $config;
    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->s = [];
        $this->config = new Config($this->getDataFolder() . "blocks.yml", Config::YAML, array());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->parseBlockData();
    }
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){
        if($cmd->getName() == "tr"){
            if(isset($args[1])){
                if($sender->hasPermission("taptodo.command." . $args[1])){
                    switch($args[1]){
                        case "add":
                            $i = 0;
                            $name = array_shift($args);
                            array_shift($args);
                            foreach($this->getBlocksByName($name) as $block){
                                $block->addCommand(implode(" ", $args));
                                $i++;
                            }
                            $sender->sendMessage("Added commmand to $i blocks.");
                            return true;
                            break;
                        case "del":
                            $i = 0;
                            $name = array_shift($args);
                            array_shift($args);
                            foreach($this->getBlocksByName($name) as $block){
                                if(($block->delCommand(implode(" ", $args))) !== false){
                                    $i++;
                                }
                            }
                            $sender->sendMessage("Deleted commmand from $i blocks.");
                            return true;
                            break;
                        case "delall":
                            $i = 0;
                            foreach($this->getBlocksByName($args[0]) as $block){
                                $this->removeBlock($block);
                                $i++;
                            }
                            $sender->sendMessage("Deleted $i blocks.");
                            return true;
                            break;
                        case "name":
                        case "rename":
                            $i = 0;
                            foreach($this->getBlocksByName($args[0]) as $block){
                                $block->nameBlock($block);
                                $i++;
                            }
                            $sender->sendMessage("Renamed $i blocks.");
                            return true;
                            break;
                        case "list":
                            $i = 0;
                            foreach($this->getBlocksByName($args[0]) as $block){
                                $pos = $block->getPos();
                                $sender->sendMessage("Commands for block at X:" . $pos->getX() . " Y:" . $pos->getY() . " Z:" . $pos->getY() . " Level:" . $pos->getLevel()->getName());
                                foreach($block->getCommands() as $cmd){
                                    $sender->sendMessage("- $cmd");
                                }
                                $i++;
                            }
                            $sender->sendMessage("Listed $i blocks.");
                            return true;
                            break;
                    }
                }
                else{
                    $sender->sendMessage("You don't have permission.");
                    return true;
                }
            }
        }
        else{
            if($sender instanceof Player){
                if(isset($args[1])){
                    if($sender->hasPermission("taptodo.command." . $args[1])){
                        $this->s[$sender->getName()] = $args;
                        $sender->sendMessage("Tap a block to complete action...");
                        return true;
                    }
                    else{
                        $sender->sendMessage("You don't have permission to perform that action.");
                        return true;
                    }
                }
            }
            else{
                $sender->sendMessage("Please run this command in game.");
                return true;
            }
        }
    }
    public function onInteract(PlayerInteractEvent $event){
        if(isset($this->s[$event->getPlayer()->getName()])){
            $args = $this->s[$event->getPlayer()->getName()];
            switch($args[0]){
                case "add":
                    if(isset($args[1])){
                        if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block){
                            array_shift($args);
                            $b->addCommand(implode(" ", $args));
                            $event->getPlayer()->sendMessage("Command added.");
                        }
                        else{
                            array_shift($args);
                            $this->addBlock($event->getBlock(), implode(" ", $args));
                            $event->getPlayer()->sendMessage("Command added.");
                        }
                    }
                    else{
                        $event->getPlayer()->sendMessage("You must specify a command.");
                    }
                    break;
                case "del":
                    if(isset($args[1])){
                        if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block){
                            array_shift($args);
                            if(($b->delCommand(implode(" ", $args))) !== false){
                                $event->getPlayer()->sendMessage("Command removed.");
                            }
                            else{
                                $event->getPlayer()->sendMessage("Couldn't find command.");
                            }

                        }
                        else{
                            $event->getPlayer()->sendMessage("Block does not exist.");
                        }
                    }
                    else{
                        $event->getPlayer()->sendMessage("You must specify a command.");
                    }
                    break;
                case "delall":
                    if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block){
                        $this->removeBlock($b);
                        $event->getPlayer()->sendMessage("Block removed.");
                    }
                    else{
                        $event->getPlayer()->sendMessage("Block doesn't exist.");
                    }
                    break;
                case "name":
                    if(isset($args[1])){
                        if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block){
                            $b->nameBlock($args[1]);
                            $event->getPlayer()->sendMessage("Block named.");
                        }
                        else{
                            $event->getPlayer()->sendMessage("Block doesn't exist.");
                        }
                    }
                    else{
                        $event->getPlayer()->sendMessage("You need to specify a name.");
                    }
                    break;
                case "list":
                    if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block){
                        foreach($b->getCommands() as $cmd){
                            $event->getPlayer()->sendMessage($cmd);
                        }
                    }
                    else{
                        $event->getPlayer()->sendMessage("Block doesn't exist.");
                    }
                    break;
            }
            unset($this->s[$event->getPlayer()->getName()]);
        }
        else{
            if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block && $event->getPlayer()->hasPermission("taptodo.tap")){
                $b->runCommands($event->getPlayer());
            }
        }
    }
    public function onLevelLoad(LevelLoadEvent $event){
        $this->getLogger()->info("Reloading blocks due to level loaded...");
        $this->parseBlockData();
    }
    public function getBlocksByName($name){
        $ret = [];
        foreach($this->b as $block){
            if($block->getName() === $name) $ret[] = $block;
        }
        return $ret;
    }
    public function getBlock($x, $y, $z, $level){
        if($x instanceof Position) return (isset($this->b[$x->getX() . ":" . $x->getY() . ":" . $x->getZ() . ":" . $x->getLevel()->getName()]) ? $this->b[$x->getX() . ":" . $x->getY() . ":" . $x->getZ() . ":" . $x->getLevel()->getName()] : false);
        else return (isset($this->b[$x . ":" . $y . ":" . $z . ":" . $level]) ? $this->b[$x . ":" . $y . ":" . $z . ":" . $level] : false);
    }
    public function parseBlockData(){
        $this->b = [];
        foreach($this->config->getAll() as $i => $block){
            if($this->getServer()->isLevelLoaded($block["level"])){
                $pos = new Position($block["x"], $block["y"], $block["z"], $this->getServer()->getLevelByName($block["level"]));
                if(isset($block["name"])) $this->b[$pos->__toString()] = new Block($pos, $block["commands"], $this->config, $block["name"]);
                else $this->b[$block["x"] . ":" . $block["y"] . ":" . $block["z"] . ":" . $block["level"]] = new Block($pos, $block["commands"], $this, $i);
            }
            else{
                $this->getLogger()->warning("Could not load block in level " . $block["level"] . " because that level is not loaded.");
            }
        }
    }
    public function removeBlock(Block $block){
        $this->config->remove($block->id);
        $this->config->save();
        $this->parseBlockData();
    }
    public function addBlock(Position $p, $cmd){
        $block = new Block(new Position($p->getX(), $p->getY(), $p->getZ(), $p->getLevel()), [$cmd], $this, count($this->config->getAll()));
        $this->saveBlock($block);
        $this->config->save();
        return $block;
    }
    public function saveBlock(Block $block){
        $this->b[$block->getPos()->getX() . ":" . $block->getPos()->getY() . ":" . $block->getPos()->getZ() . ":" . $block->getPos()->getLevel()->getName()] = $block;
        $this->config->set($block->id, $block->toArray());
    }
    public function onDisable(){
        $this->getLogger()->info("Saving blocks...");
        foreach($this->b as $block){
            $this->saveBlock($block);
        }
        $this->config->save();
    }
}