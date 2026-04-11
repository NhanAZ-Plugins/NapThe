<?php

declare(strict_types=1);

namespace Donate\manager;

use Donate\Constant;
use Donate\Donate;
use Donate\form\ConfirmationForm;
use Donate\form\DonateCardForm;
use Donate\form\FormCooldownTracker;
use Donate\form\TopDonateForm;
use pocketmine\player\Player;
use function max;

class FormManager {
	private DonateCardForm $donateCardForm;

	private TopDonateForm $topDonateForm;

	private ConfirmationForm $confirmationForm;

	private FormCooldownTracker $cooldownTracker;

	public function __construct(Donate $plugin) {
		$formCooldown = max(1, Constant::FORM_COOLDOWN);
		$this->cooldownTracker = new FormCooldownTracker($formCooldown);
		$this->donateCardForm = new DonateCardForm($plugin, $this->cooldownTracker);
		$this->topDonateForm = new TopDonateForm($plugin);
		$this->confirmationForm = new ConfirmationForm();

		$plugin->debugLogger->log("Form cooldown set to {$formCooldown} seconds", "form");
	}

	public function getFormCooldown() : int {
		return $this->cooldownTracker->getCooldown();
	}

	public function sendDonateForm(Player $player) : void {
		$this->donateCardForm->send($player);
	}

	public function sendTopDonateForm(Player $player, int $page = 1) : void {
		$this->topDonateForm->send($player, $page);
	}

	public function sendConfirmationForm(Player $player, string $message, callable $onConfirm) : void {
		$this->confirmationForm->send($player, $message, $onConfirm);
	}
}
