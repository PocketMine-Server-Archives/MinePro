<?php

namespace PTK\VotePE;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\item\Item;

class Main extends PluginBase {

  private $message = "";
  private $items = [];
  private $commands = [];
  private $debug = false;
  public $queue = [];

  public function onLoad() {
    if(file_exists($this->getDataFolder() . "config.yml")) {
      $c = $this->getConfig()->getAll();
      if(isset($c["API-Key"])) {
        if(trim($c["API-Key"]) != "") {
          if(!is_dir($this->getDataFolder() . "Lists/")) {
            mkdir($this->getDataFolder() . "Lists/");
          }
          file_put_contents($this->getDataFolder() . "Lists/minecraftpocket-servers.com.vrc", "{\"website\":\"http://minecraftpocket-servers.com/\",\"check\":\"http://minecraftpocket-servers.com/api-vrc/?object=votes&element=claim&key=" . $c["API-Key"] . "&username={USERNAME}\",\"claim\":\"http://minecraftpocket-servers.com/api-vrc/?action=post&object=votes&element=claim&key=" . $c["API-Key"] . "&username={USERNAME}\"}");
          rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.old.yml");
          $this->getLogger()->info("§eConverting API key to VRC file...");
        } else {
          rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.old.yml");
          $this->getLogger()->info("§eSetting up new configuration file...");
        }
      }
    }
  }

  public function onEnable() {
    $this->reload();
  }

  public function reload() {
    $this->saveDefaultConfig();
    if(!is_dir($this->getDataFolder() . "Lists/")) {
      mkdir($this->getDataFolder() . "Lists/");
    }
    $this->lists = [];
    foreach(scandir($this->getDataFolder() . "Lists/") as $file) {
      $ext = explode(".", $file);
      $ext = (count($ext) > 1 && isset($ext[count($ext) - 1]) ? strtolower($ext[count($ext) - 1]) : "");
      if($ext == "vrc") {
        $this->lists[] = json_decode(file_get_contents($this->getDataFolder() . "Lists/$file"),true);
      }
    }
    $this->reloadConfig();
    $config = $this->getConfig()->getAll();
    $this->message = $config["Message"];
    $this->items = [];
    foreach($config["Items"] as $i) {
      $r = explode(":", $i);
      $this->items[] = new Item($r[0], $r[1], $r[2]);
    }
    $this->commands = $config["Commands"];
    $this->debug = isset($config["Debug"]) && $config["Debug"] === true ? true : false;
  }

  public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
    switch(strtolower($command->getName())) {
      case "vote":
        if(isset($args[0]) && strtolower($args[0]) == "reload") {
          if(Utils::hasPermission($sender, "votepe.command.reload")) {
            $this->reload();
            $sender->sendMessage("§b[§c❤§eVote-System§c❤§b]§a Tất Cả Cài Đặt Đã Được Tải Lại.");
            break;
          }
          $sender->sendMessage("You do not have permission to use this subcommand.");
          break;
        }
        if(!$sender instanceof Player) {
          $sender->sendMessage("This command must be used in-game.");
          break;
        }
        if(!Utils::hasPermission($sender, "votepe.command.vote")) {
          $sender->sendMessage("You do not have permission to use this command.");
          break;
        }
        if(in_array(strtolower($sender->getName()), $this->queue)) {
          $sender->sendMessage("§b[§c❤§eVote-System§c❤§b]§a Xin Hãy Chờ 1 Chút! Chúng Tôi Đang Kiểm Tra Tài Khoản Của Bạn!");
          break;
        }
        $this->queue[] = strtolower($sender->getName());
        $requests = [];
        foreach($this->lists as $list) {
          if(isset($list["check"]) && isset($list["claim"])) {
            $requests[] = new ServerListQuery($list["check"], $list["claim"]);
          }
        }
        $query = new RequestThread(strtolower($sender->getName()), $requests);
        $this->getServer()->getScheduler()->scheduleAsyncTask($query);
        break;
      default:
        $sender->sendMessage("Invalid command.");
        break;
    }
    return true;
  }

  public function rewardPlayer($player, $multiplier) {
    if(!$player instanceof Player) {
      return;
    }
    if($multiplier < 1) {
      $player->sendMessage("§b[§c❤§eVote-System§c❤§b]§c Bạn Chưa Vote Cho Máy Chủ! Hãy Truy Cập bit.do/mineprovote Để Vote Cho Máy Chủ!!!");
      return;
    }
    $clones = [];
    foreach($this->items as $item) {
      $clones[] = clone $item;
    }
    foreach($clones as $item) {
      $item->setCount($item->getCount() * $multiplier);
      $player->getInventory()->addItem($item);
    }
    foreach($this->commands as $command) {
      $this->getServer()->dispatchCommand(new ConsoleCommandSender, str_replace(array(
        "{USERNAME}",
        "{NICKNAME}",
        "{X}",
        "{Y}",
        "{Y1}",
        "{Z}"
      ), array(
        $player->getName(),
        $player->getDisplayName(),
        $player->getX(),
        $player->getY(),
        $player->getY() + 1,
        $player->getZ()
      ), Utils::translateColors($command)));
    }
    if(trim($this->message) != "") {
      $message = str_replace(array(
        "{USERNAME}",
        "{NICKNAME}"
      ), array(
        $player->getName(),
        $player->getDisplayName()
      ), Utils::translateColors($this->message));
      foreach($this->getServer()->getOnlinePlayers() as $p) {
        $p->sendMessage($message);
      }
      $this->getServer()->getLogger()->info($message);
    }
    $player->sendMessage("§b[§c❤§eVote-System§c❤§b]§a Cảm Ơn Bạn Đã Vote Cho Máy Chủ Cả Chúng Tôi!!!");
  }

}