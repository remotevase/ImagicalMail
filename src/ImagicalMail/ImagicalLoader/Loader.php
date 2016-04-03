<?php
namespace ImagicalMail\ImagicalMail;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use ImagicalMail\ImagicalMain;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
class ImagicalMail extends PluginBase implements Listener {
    const CONFIG_MAXMESSAGE = "maxMessagesToPlayer";
    const CONFIG_SIMILARLIM = "similarLimit";
    const CONFIG_NOTIFY = "notifyOnNew";
    protected $messages = [];
    public $prefix;
    
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->saveResource("messages.yml");
        $messages = (new Config($this->getDataFolder() . "messages.yml"))->getAll();
        $this->messages = $this->parseMessages($messages);
        ImagicalMain::setupDataFiles($this);
        $this->getLogger()->info(C::YELLOW."ImagicalMail has loaded!");
        $this->prefix = $this->plugin->getConfig()->get("prefix");
    }
    public function onDisable() {
        $this->getLogger()->info(C::RED."ImagicalMail has disabled!");
    }
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch ($command->getName()) {
            case "mail":
                switch (strtolower(array_shift($args))) {
                    case "see":
                        $messages = ImagicalMain::getMessages($this->getUserName($sender));
                        $sender->sendMessage($prefix. " " . sprintf($this->getMessage("messages.count"), count($messages)) . ".");
                        foreach ($messages as $message) {
                            $sender->sendMessage("    " . $message["sender"] . ": " . $message["message"]);
                        }
                        break;
                    case "clear":
                    case $this->getMessage("commands.names.clear"):
                        ImagicalMain::clearMessages($this->getUserName($sender));
                        $sender->sendMessage($prefix. " " . $this->getMessage("messages.cleared"));
                        break;
                    case "send":
                    case $this->getMessage("commands.names.send"):
                        $senderName = $this->getUserName($sender);
                        $recipiant = strtolower(array_shift($args));
                        $message = implode(" ", $args);
                        if ($recipiant != NULL && $message != NULL) {
                            if ($this->checkUser($recipiant)) {
                                if ($this->isMessageSimilar($senderName, $recipiant, $message)) {
                                    $sender->sendMessage($this->getMessage("messages.similar"));
                                }else{
                                    $msgCount = s:countMessagesFromPlayer($senderName, $recipiant);
                                    $msgCountMax = $this->getConfig()->get(ImagicalMail::CONFIG_MAXMESSAGE);
                                    if ($msgCount > $msgCountMax) {
                                        $sender->sendMessage($prefix. " " . sprintf($this->getMessage("messages.too_many"), $recipiant) . " (" . ($msgCount - 1) . "/$msgCountMax)");
                                    }else{
                                        ImagicalMain::addMessage($recipiant, $senderName, $message);
                                        $sender->sendMessage($prefix. " " . $this->getMessage("messages.sent") . " ($msgCount/$msgCountMax)");
                                        $this->sendNotification($recipiant, $senderName);
                                    }
                                }
                            }else{
                                $sender->sendMessage($prefix. " " . sprintf($this->getMessage("messages.no_player"), $recipiant));
                            }
                        }else{
                            $sender->sendMessage($this->getSendCommandUsage());
                        }
                        break;
                    case "sendall":
                        if ($sender->hasPermission("imagicalmail.command.mail.all")) {
                            $senderName = $this->getUserName($sender);
                            $message = implode(" ", $args);
                            ImagicalMain::sendall($senderName, $message);
                            $sender->sendMessage($prefix. " " . $this->getMessage("messages.sent"));
                            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                                $this->sendNotification($player->getName(), $senderName);
                            }
                        }else{
                            $sender->sendMessage($prefix. " " . $this->getMessage("messages.not_allowed"));
                        }
                        break;
                    default:
                        $sender->sendMessage($this->getMessage("commands.usage.usage") . ": " . $this->getMainCommandUsage());
                }
                return true;
            default:
                return false;
        }
    }
    public function checkUser($name) {
        $name = strtolower($name);
        return file_exists($this->getServer()->getDataPath() . "players/$name.dat") || $name == "server";
    }
    public function sendNotification($player, $sender) {
        if ($this->getConfig()->get(ImagicalMail::CONFIG_NOTIFY) &&
                ($pPlayer = $this->getServer()->getPlayerExact($player)) !== null &&
                $pPlayer->isOnline()) {
            $pPlayer->sendMessage($prefix. " " . sprintf($this->getMessage("messages.new_message"), $sender));
        }
    }
    public function isMessageSimilar($fromPlayer, $toPlayer, $newmessage) {
        $limit = $this->getConfig()->get(ImagicalMail::CONFIG_SIMILARLIM);
        #console("limit:$limit");
        #console("1 - limit:" . 1 - $limit);
        if ($limit == 0) {
            return false;
        }
        $messages = ImagicalMain::getMessages($toPlayer);
        foreach ($messages as $message) {
            if ($message["sender"] == $fromPlayer) {
                if ($this->compareStrings($message["message"], $newmessage) <= (1 - $limit)) {
                    return true;
                }
            }
        }
        return false;
    }
    public function compareStrings($str1, $str2) {
        $str1m = metaphone($str1);
        $str2m = metaphone($str2);
        #console("str1m:$str1m");
        #console("str2m:$str2m");
        $dist = levenshtein($str1m, $str2m);
        #console("dist:$dist");
        #console("return:" . $dist / max(strlen($str1m), strlen($str2m)));
        return $dist / max(strlen($str1m), strlen($str2m));
    }
    public function getUserName($issuer) {
        if ($issuer instanceof \pocketmine\Player) {
            return $issuer->getName();
        }else{
            return "Server";
        }
    }
    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        $messagecount = ImagicalMain::getMessageCount($player);
        $player->sendMessage($prefix. " " . sprintf($this->getMessage("messages.count"), $messagecount) . ".  /"
                . $this->getMessage("commands.names.mail") . " "
                . $this->getMessage("commands.names.read"));
    }
    public function getMessage($key) {
        return isset($this->messages[$key]) ? $this->messages[$key] : $key;
    }
    public function getMainCommandUsage() {
        return "/" . $this->getMessage("commands.names.mail")
                . " < " . $this->getMessage("commands.names.read") . " | "
                . $this->getMessage("commands.names.clear") . " | "
                . $this->getMessage("commands.names.send") . " | "
                . $this->getMessage("commands.names.sendall") . " >";
    }
    public function getSendCommandUsage() {
        return $this->getMessage("commands.usage.usage") . ": /"
                . $this->getMessage("commands.names.mail") . " "
                . $this->getMessage("commands.names.send") . " < "
                . $this->getMessage("commands.usage.player") . " > < "
                . $this->getMessage("commands.usage.message") . " >";
    }
    private function parseMessages(array $messages) {
        $result = [];
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                foreach ($this->parseMessages($value) as $k => $v) {
                    $result[$key . "." . $k] = $v;
                }
            }else{
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
