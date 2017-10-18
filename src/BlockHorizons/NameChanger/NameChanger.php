<?php

namespace BlockHorizons\NameChanger;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

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
		$this->saveResource("NameChangeSettings.json");
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
	 * @param PlayerJoinEvent $event
	 */
	public function onJoin(PlayerJoinEvent $event) {
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
			$packet->formData = file_get_contents($this->getDataFolder() . "NameChangeSettings.json");
			$packet->formId = 3218; // For future readers, this ID should be something other plugins won't use, and is only for yourself to recognize your response packets.
			$event->getPlayer()->dataPacket($packet);
		} elseif($packet instanceof ModalFormResponsePacket) {
			$formId = $packet->formId;
			if($formId !== 3218) {
				return;
			}
			$formData = (array) json_decode($packet->formData, true);
			$confirmed = $formData[2];
			$newName = $formData[1];
			if($newName === "Steve" && !$confirmed) {
				return;
			}
			if(!$confirmed) {
				$event->getPlayer()->sendMessage(TextFormat::RED . "You did not click the name change confirm button.");
				return;
			}
			if(strtolower($newName) === $event->getPlayer()->getLowerCaseName()) {
				$event->getPlayer()->sendMessage(TextFormat::RED . "You can't change your name to your current name.");
				return;
			}
			if(!Player::isValidUserName($newName)) {
				$event->getPlayer()->sendMessage(TextFormat::RED . "Please enter a valid username.");
				return;
			}
			$this->sessions[$event->getPlayer()->getUniqueId()->toString()]->setUserName($formData[1]);
			$event->getPlayer()->transfer($this->sessions[$event->getPlayer()->getUniqueId()->toString()]->getAddress(), $this->sessions[$event->getPlayer()->getUniqueId()->toString()]->getPort(), "Username is being changed.");
		} elseif($packet instanceof LoginPacket) {
			if($packet->clientUUID === null) {
				return;
			}
			$session = $this->sessions[UUID::fromString($packet->clientUUID)->toString()] ?? null;
			if($session === null) {
				$this->sessions[UUID::fromString($packet->clientUUID)->toString()] = (new PlayerSession($packet->clientUUID, $packet->serverAddress))->setUserName($packet->username);
				return;
			}
			if($session->getUserName() !== $packet->username) {
				if(!Player::isValidUserName($session->getUserName())) {
					unset($this->sessions[UUID::fromString($packet->clientUUID)->toString()]);
					return;
				}
				$packet->username = $this->sessions[UUID::fromString($packet->clientUUID)->toString()]->getUserName();
				$this->userNameChanged[$packet->username] = true;
			}
		}
	}
}
