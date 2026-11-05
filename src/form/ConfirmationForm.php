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

		$form->setTitle("Confirm")
			->setContent($message)
			->setButton1("Confirm")
			->setButton2("Cancel");

		return $form;
	}
}
