<?php

declare(strict_types=1);

namespace NapThe\form;

use NapThe\utils\FormAPI\ModalForm;
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

		$form->setTitle("§8» §6Xác Nhận §8«")
			->setContent("§f" . $message)
			->setButton1("§8« §aXác Nhận §8»")
			->setButton2("§8« §cHuỷ Bỏ §8»");

		return $form;
	}
}
