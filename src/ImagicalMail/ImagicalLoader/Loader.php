<?php
namespace ImagicalMail\ImagicalLoader\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use ImagicalMail\ImagicalMain;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
class ImagicalMail extends PluginBase implements Listener {
    const CONFIG_MAXImagicalMessage = "maxImagicalMessagesToPlayer";
    const CONFIG_SIMILARLIM = "similarLimit";
    const CONFIG_NOTIFY = "notifyOnNew";
    protected $ImagicalMessages = [];
    public $prefix;
    
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->saveResource("ImagicalMessages.yml");
        $ImagicalMessages = (new Config($this->getDataFolder() . "ImagicalMessages.yml"))->getAll();
        $this->ImagicalMessages = $this->parseImagicalMessages($ImagicalMessages);
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
                        $ImagicalMessages = ImagicalMain::getImagicalMessages($this->getUserName($sender));
                        $sender->sendImagicalMessage($prefix. " " . sprintf($this->getImagicalMessage("ImagicalMessages.count"), count($ImagicalMessages)) . ".");
                        foreach ($ImagicalMessages as $ImagicalMessage) {
                            $sender->sendImagicalMessage("    " . $ImagicalMessage["sender"] . ": " . $ImagicalMessage["ImagicalMessage"]);
                        }
                        break;
                    case "clear":
                    case $this->getImagicalMessage("commands.names.clear"):
                        ImagicalMain::clearImagicalMessages($this->getUserName($sender));
                        $sender->sendImagicalMessage($prefix. " " . $this->getImagicalMessage("ImagicalMessages.cleared"));
                        break;
                    case "send":
                    case $this->getImagicalMessage("commands.names.send"):
                        $senderName = $this->getUserName($sender);
                        $recipiant = strtolower(array_shift($args));
                        $ImagicalMessage = implode(" ", $args);
                        if ($recipiant != NULL && $ImagicalMessage != NULL) {
                            if ($this->checkUser($recipiant)) {
                                if ($this->isImagicalMessagesimilar($senderName, $recipiant, $ImagicalMessage)) {
                                    $sender->sendImagicalMessage($this->getImagicalMessage("ImagicalMessages.similar"));
                                }else{
                                    $msgCount = s:countImagicalMessagesFromPlayer($senderName, $recipiant);
                                    $msgCountMax = $this->getConfig()->get(ImagicalMail::CONFIG_MAXImagicalMessage);
                                    if ($msgCount > $msgCountMax) {
                                        $sender->sendImagicalMessage($prefix. " " . sprintf($this->getImagicalMessage("ImagicalMessages.too_many"), $recipiant) . " (" . ($msgCount - 1) . "/$msgCountMax)");
                                    }else{
                                        ImagicalMain::addImagicalMessage($recipiant, $senderName, $ImagicalMessage);
                                        $sender->sendImagicalMessage($prefix. " " . $this->getImagicalMessage("ImagicalMessages.sent") . " ($msgCount/$msgCountMax)");
                                        $this->sendNotification($recipiant, $senderName);
                                    }
                                }
                            }else{
                                $sender->sendImagicalMessage($prefix. " " . sprintf($this->getImagicalMessage("ImagicalMessages.no_player"), $recipiant));
                            }
                        }else{
                            $sender->sendImagicalMessage($this->getSendCommandUsage());
                        }
                        break;
                    case "sendtoall":
                        if ($sender->hasPermission("imagicalmail.command.mail.all")) {
                            $senderName = $this->getUserName($sender);
                            $ImagicalMessage = implode(" ", $args);
                            ImagicalMain::sendtoall($senderName, $ImagicalMessage);
                            $sender->sendImagicalMessage($prefix. " " . $this->getImagicalMessage("ImagicalMessages.sent"));
                            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                                $this->sendNotification($player->getName(), $senderName);
                            }
                        }else{
                            $sender->sendImagicalMessage($prefix. " " . $this->getImagicalMessage("ImagicalMessages.not_allowed"));
                        }
                        break;
                    default:
                        $sender->sendImagicalMessage($this->getImagicalMessage("commands.usage.usage") . ": " . $this->getMainCommandUsage());
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
            $pPlayer->sendImagicalMessage($prefix. " " . sprintf($this->getImagicalMessage("ImagicalMessages.new_ImagicalMessage"), $sender));
        }
    }
    public function isImagicalMessagesimilar($fromPlayer, $toPlayer, $newImagicalMessage) {
        $limit = $this->getConfig()->get(ImagicalMail::CONFIG_SIMILARLIM);
        #console("limit:$limit");
        #console("1 - limit:" . 1 - $limit);
        if ($limit == 0) {
            return false;
        }
        $ImagicalMessages = ImagicalMain::getImagicalMessages($toPlayer);
        foreach ($ImagicalMessages as $ImagicalMessage) {
            if ($ImagicalMessage["sender"] == $fromPlayer) {
                if ($this->compareStrings($ImagicalMessage["ImagicalMessage"], $newImagicalMessage) <= (1 - $limit)) {
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
        $ImagicalMessagecount = ImagicalMain::getImagicalMessageCount($player);
        $player->sendImagicalMessage($prefix. " " . sprintf($this->getImagicalMessage("ImagicalMessages.count"), $ImagicalMessagecount) . ".  /"
                . $this->getImagicalMessage("commands.names.mail") . " "
                . $this->getImagicalMessage("commands.names.see"));
    }
    public function getImagicalMessage($key) {
        return isset($this->ImagicalMessages[$key]) ? $this->ImagicalMessages[$key] : $key;
    }
    public function getMainCommandUsage() {
        return "/" . $this->getImagicalMessage("commands.names.mail")
                . " < " . $this->getImagicalMessage("commands.names.see") . " | "
                . $this->getImagicalMessage("commands.names.clear") . " | "
                . $this->getImagicalMessage("commands.names.send") . " | "
                . $this->getImagicalMessage("commands.names.sendtoall") . " >";
    }
    public function getSendCommandUsage() {
        return $this->getImagicalMessage("commands.usage.usage") . ": /"
                . $this->getImagicalMessage("commands.names.mail") . " "
                . $this->getImagicalMessage("commands.names.send") . " < "
                . $this->getImagicalMessage("commands.usage.player") . " > < "
                . $this->getImagicalMessage("commands.usage.ImagicalMessage") . " >";
    }
    private function parseImagicalMessages(array $ImagicalMessages) {
        $result = [];
        foreach ($ImagicalMessages as $key => $value) {
            if (is_array($value)) {
                foreach ($this->parseImagicalMessages($value) as $k => $v) {
                    $result[$key . "." . $k] = $v;
                }
            }else{
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
