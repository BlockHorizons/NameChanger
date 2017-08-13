<?php

namespace BlockHorizons\NameChanger;

use pocketmine\utils\UUID;

class PlayerSession {

	/** @var string */
	private $userName = "";
	/** @var UUID */
	private $clientUUID = null;
	/** @var string */
	private $address = "";
	/** @var int */
	private $port = 19132;

	public function __construct(string $clientUUID, string $address) {
		$this->clientUUID = UUID::fromString($clientUUID);

		$this->address = ($connectInfo = explode(":", $address))[0];
		$this->port = (int) $connectInfo[1];
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

	/**
	 * @return string
	 */
	public function getAddress(): string {
		return $this->address;
	}

	/**
	 * @return int
	 */
	public function getPort(): int {
		return $this->port;
	}
}