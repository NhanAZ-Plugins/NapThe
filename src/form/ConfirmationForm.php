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

		$form->setTitle("XÃ¡c nháº­n")
			->setContent($message)
			->setButton1("XÃ¡c nháº­n")
			->setButton2("Há»§y");

		return $form;
	}
}
