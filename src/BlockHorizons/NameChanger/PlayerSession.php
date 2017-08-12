<?php

namespace BlockHorizons\NameChanger;

use pocketmine\utils\UUID;

class PlayerSession {

	/** @var string */
	private $userName = "";
	/** @var UUID */
	private $clientUUID = null;

	public function __construct(UUID $clientUUID) {
		$this->clientUUID = $clientUUID;
	}

	/**
	 * @return UUID
	 */
	public function getClientUUID(): UUID {
		return $this->clientUUID;
	}

	/**
	 * @return string
	 */
	public function getUserName(): string {
		return $this->userName;
	}

	/**
	 * @param string $userName
	 *
	 * @return PlayerSession
	 */
	public function setUserName(string $userName): PlayerSession {
		$this->userName = $userName;
		return $this;
	}
}