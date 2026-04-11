<?php

declare(strict_types=1);

namespace Donate\form;

use Donate\utils\FormAPI\ModalForm;
use pocketmine\player\Player;

final class ConfirmationForm {
	public function send(Player $player, string $message, callable $onConfirm) : void {
		$player->sendForm($this->create($message, $onConfirm));
	}

	private function create(string $message, callable $onConfirm) : ModalForm {
		$form = new ModalForm(function(Player $player, mixed $confirmed) use ($onConfirm) : void {
			if ($confirmed === true) {
				$onConfirm();
			}
		});

		$form->setTitle("§l§f⁕ §8[§6Xác nhận§8] §f⁕")
			->setContent("§f" . $message)
			->setButton1("§l§8« §aXác nhận §8»")
			->setButton2("§l§8« §cHủy §8»");

		return $form;
	}
}
