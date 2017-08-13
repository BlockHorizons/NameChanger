<?php

namespace BlockHorizons\NameChanger;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class NameChanger extends PluginBase implements Listener {

	/** @var PlayerSession[] */
	private $sessions = [];
	/** @var array */
	private $userNameChanged = [];

	public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!is_dir($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}
		if(file_exists($path = $this->getDataFolder() . "sessions.yml")) {
			foreach(yaml_parse_file($path) as $clientUUID => $serializedSession) {
				$this->sessions[$clientUUID] = unserialize($serializedSession);
			}
			unlink($path);
		}
	}

	public function onDisable() {
		$data = [];
		foreach($this->sessions as $clientUUID => $session) {
			$data[$clientUUID] = serialize($session);
		}
		yaml_emit_file($this->getDataFolder() . "sessions.yml", $data);
	}

	/**
	 * @param PlayerLoginEvent $event
	 */
	public function onLogin(PlayerLoginEvent $event) {
		$clientUUID = $event->getPlayer()->getUniqueId();
		if(!isset($this->sessions[$clientUUID->toString()])) {
			$this->sessions[$clientUUID->toString()] = (new PlayerSession($clientUUID))->setUserName($event->getPlayer()->getName());
		}
		if(isset($this->userNameChanged[$event->getPlayer()->getName()])) {
			$event->getPlayer()->sendMessage(TextFormat::GREEN . "Your username has been changed to " . $event->getPlayer()->getName());
			unset($this->userNameChanged[$event->getPlayer()->getName()]);
		} else {
			$event->getPlayer()->sendTip(TextFormat::AQUA . "Open the settings screen to switch username.");
		}
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 */
	public function onDataPacket(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		if($packet instanceof ServerSettingsRequestPacket) {
			$packet = new ServerSettingsResponsePacket();
			$packet->formData = file_get_contents(__DIR__ . "\NameChangeSettings.json");
			$packet->formId = 3218; // For future readers, this ID should be something other plugins won't use, and is only for yourself to recognize your response packets.
			$event->getPlayer()->dataPacket($packet);
		} elseif($packet instanceof ModalFormResponsePacket) {
			$formId = $packet->formId;
			if($formId !== 3218) {
				return;
			}
			$formData = (array) json_decode($packet->formData, true);
			if(!$confirmed = $formData[2]) {
				$event->getPlayer()->sendMessage(TextFormat::RED . "You did not click the name change confirm button.");
				return;
			}
			if(strtolower($formData[1]) === $event->getPlayer()->getLowerCaseName()) {
				$event->getPlayer()->sendMessage(TextFormat::RED . "You can't change your name to your current name.");
				return;
			}
			$this->sessions[$event->getPlayer()->getUniqueId()->toString()]->setUserName($formData[1]);
			$event->getPlayer()->transfer($this->getServer()->getIp(), $this->getServer()->getPort(), "Username is being changed.");
		} elseif($packet instanceof LoginPacket) {
			if(!isset($this->sessions[$packet->clientUUID])) {
				return;
			}
			$packet->username = $this->sessions[$packet->clientUUID]->getUserName();
			$this->userNameChanged[$packet->username] = true;
		}
	}
}