<?php

declare(strict_types=1);

namespace NapThe\utils\FormAPI;

use InvalidArgumentException;
use pocketmine\form\Form as IForm;
use pocketmine\player\Player;
use function is_string;

/**
 * Adapted from jojoe77777/FormAPI (LGPL-3.0).
 */
abstract class Form implements IForm {
	/** @var array<string, mixed> */
	protected array $data = [];

	protected function getStringField(string $key) : string {
		$value = $this->data[$key] ?? null;
		if (!is_string($value)) {
			throw new \LogicException("Invalid form field: $key");
		}
		return $value;
	}

	/** @var callable|null */
	private $callable;

	public function __construct(?callable $callable) {
		$this->callable = $callable;
	}

	/**
	 * @deprecated Use Player::sendForm() instead.
	 * @throws InvalidArgumentException
	 */
	public function sendToPlayer(Player $player) : void {
		$player->sendForm($this);
	}

	public function getCallable() : ?callable {
		return $this->callable;
	}

	public function setCallable(?callable $callable) : void {
		$this->callable = $callable;
	}

	public function handleResponse(Player $player, mixed $data) : void {
		$this->processData($data);
		$callable = $this->getCallable();
		if ($callable !== null) {
			$callable($player, $data);
		}
	}

	public function processData(mixed &$data) : void {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize() : array {
		return $this->data;
	}
}
