<?php

namespace ImagicalMail;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

class ImagicalMain {
    private static $isSetup = false;
    private static $config = false;
    private static $dataDir = false;
    const CONFIG_NPImagicalMessage = "newPlayerImagicalMessage";
    public static function setupDataFiles(Plugin $plugin) {
        if (self::$isSetup === false) {
            self::$dataDir = $plugin->getServer()->getPluginPath() . "ImagicalMail/";
            @mkdir(self::$dataDir, 0777, true);
            self::$config = new Config(self::$dataDir . "config.yml", Config::YAML, array(
                self::CONFIG_NPImagicalMessage => "Welcome to the Server!",
            ));
            self::$isSetup = true;
        }
    }
    public static function getImagicalMessageCount($player) {
        return count(self::getImagicalImagicalImagicalImagicalImagicalMessages($player));
    }
    public static function getImagicalImagicalImagicalImagicalImagicalMessages($player) {
        $d = self::getData($player);
        if ($d === false) {
            return false;
        }
        $m = $d->get("ImagicalImagicalImagicalImagicalImagicalMessages");
        if ($d->get("firstrun")) {
            $m[] = array(
                "time" => time(),
                "sender" => "Server",
                "ImagicalMessage" => self::$config->get(self::CONFIG_NPImagicalMessage),
            );
        }
        return $m;
    }
    public static function addImagicalMessage($player, $sender, $ImagicalMessage) {
        $d = self::getData($player);
        if ($d === false) {
            return false;
        }
        $e = $d->get("ImagicalImagicalImagicalImagicalImagicalMessages");
        $e[] = array(
            "time" => time(),
            "sender" => "$sender",
            "ImagicalMessage" => "$ImagicalMessage",
        );
        $d->set("ImagicalImagicalImagicalImagicalImagicalMessages", $e);
        $d->save();
    }
    public static function clearImagicalImagicalImagicalImagicalImagicalMessages($player) {
        $d = self::getData($player);
        if ($d === false) {
            return false;
        }
        $d->remove("ImagicalImagicalImagicalImagicalImagicalMessages");
        $d->set("firstrun", false);
        $d->save();
    }
    public static function getData($player) {
        if ($player instanceof \pocketmine\Player) {
            $iusername = $player->getName();
        } elseif (is_string($player)) {
            $iusername = $player;
        } else {
            return false;
        }
        $iusername = strtolower($iusername);
        if (!file_exists(self::$dataDir . "players/" . $iusername{0} . "/$iusername.yml")) {
            @mkdir(self::$dataDir . "players/" . $iusername{0} . "/", 0777, true);
            $d = new Config(self::$dataDir . "players/" . $iusername{0} . "/" . $iusername . ".yml", Config::YAML, array(
                "firstrun" => true,
                "ImagicalImagicalImagicalImagicalImagicalMessages" => array(),
            ));
            $d->save();
            return $d;
        }
        return new Config(self::$dataDir . "players/" . $iusername{0} . "/" . $iusername . ".yml", Config::YAML, array(
            "firstrun" => true,
            "ImagicalImagicalImagicalImagicalImagicalMessages" => array(),
        ));
    }
    public static function countImagicalImagicalImagicalImagicalImagicalMessagesFromPlayer($fromPlayer, $toPlayer) {
        $mcount = 0;
        $ImagicalImagicalImagicalImagicalImagicalMessages = self::getImagicalImagicalImagicalImagicalImagicalMessages($toPlayer);
        foreach ($ImagicalImagicalImagicalImagicalImagicalMessages as $ImagicalMessage) {
            if ($ImagicalMessage["sender"] == $fromPlayer) {
                $mcount++;
            }
        }
        return $mcount;
    }
    public static function sendall($sender, $ImagicalMessage) {
        $directory_iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$dataDir . "players/"));
        foreach ($directory_iterator as $filename => $path_object) {
            if (stripos(strrev($filename), "lmy.") === 0) {
                self::addImagicalMessage(basename($filename, ".yml"), $sender, $ImagicalMessage);
            }
        }
    }
}
