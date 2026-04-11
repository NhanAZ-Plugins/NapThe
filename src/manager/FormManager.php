<?php

declare(strict_types=1);

namespace NapThe\manager;

use NapThe\Constant;
use NapThe\NapThe;
use NapThe\form\ConfirmationForm;
use NapThe\form\NapTheCardForm;
use NapThe\form\FormCooldownTracker;
use NapThe\form\TopNapTheForm;
use pocketmine\player\Player;
use function max;

class FormManager {
	private NapTheCardForm $donateCardForm;

	private TopNapTheForm $topNapTheForm;

	private ConfirmationForm $confirmationForm;

	private FormCooldownTracker $cooldownTracker;

	public function __construct(NapThe $plugin) {
		$formCooldown = max(1, Constant::FORM_COOLDOWN);
		$this->cooldownTracker = new FormCooldownTracker($formCooldown);
		$this->napTheCardForm = new NapTheCardForm($plugin, $this->cooldownTracker);
		$this->topNapTheForm = new TopNapTheForm($plugin);
		$this->confirmationForm = new ConfirmationForm();

		$plugin->debugLogger->log("Form cooldown set to {$formCooldown} seconds", "form");
	}

	public function getFormCooldown() : int {
		return $this->cooldownTracker->getCooldown();
	}

	public function sendNapTheForm(Player $player) : void {
		$this->napTheCardForm->send($player);
	}

	public function sendTopNapTheForm(Player $player, int $page = 1) : void {
		$this->topNapTheForm->send($player, $page);
	}

	public function sendConfirmationForm(Player $player, string $message, callable $onConfirm) : void {
		$this->confirmationForm->send($player, $message, $onConfirm);
	}
}
