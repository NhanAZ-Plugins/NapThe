<?php

declare(strict_types=1);

namespace NapThe\form;

use NapThe\Constant;
use NapThe\NapThe;
use NapThe\utils\DataTypeUtils;
use NapThe\utils\FormAPI\CustomForm;
use NapThe\utils\MessageTranslator;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;
use function is_array;
use function time;

final class NapTheCardForm {
	public function __construct(
		private NapThe $plugin,
		private FormCooldownTracker $cooldownTracker
	) {
	}

	public function send(Player $player) : void {
		$playerName = $player->getName();
		$currentTime = time();
		$remainingTime = $this->cooldownTracker->getRemainingNapTheCooldown($playerName, $currentTime);

		if ($remainingTime > 0) {
			$player->sendMessage(MessageTranslator::formatErrorMessage("Please wait {$remainingTime} seconds before opening the card form again."));
			$this->plugin->debugLogger->log("Form spam prevented - Player: {$playerName} tried to open napthe form too quickly", "form");
			return;
		}

		$this->cooldownTracker->touchNapTheCooldown($playerName, $currentTime);
		$this->plugin->debugLogger->log("Sending napthe form to player: " . $player->getName(), "form");
		$player->sendForm($this->create());
	}

	private function create() : CustomForm {
		$form = new CustomForm(function(Player $player, mixed $data) : void {
			if ($data === null) {
				$this->plugin->debugLogger->log("Player " . $player->getName() . " closed the napthe form", "form");
				return;
			}

			$playerName = $player->getName();
			$currentTime = time();
			$remainingTime = $this->cooldownTracker->getRemainingNapTheCooldown($playerName, $currentTime);

			if ($remainingTime > 0) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("You are submitting too quickly. Please wait {$remainingTime} seconds before trying again."));
				$this->plugin->debugLogger->log("Form submission throttled - Player: {$playerName} tried to submit too quickly", "form");
				return;
			}

			$this->cooldownTracker->touchNapTheCooldown($playerName, $currentTime);

			if (!is_array($data)) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("An error occurred while processing the form."));
				return;
			}

			$telcoIndex = DataTypeUtils::toInt($data["telco"] ?? 0);
			$amountIndex = DataTypeUtils::toInt($data["amount"] ?? 0);
			$serial = DataTypeUtils::toString($data["serial"] ?? "");
			$code = DataTypeUtils::toString($data["code"] ?? "");

			if ($serial === "" || $code === "") {
				$player->sendMessage("Please enter both the serial number and card code.");
				$this->plugin->debugLogger->log("Player {$player->getName()} submitted form with empty serial or code", "payment");
				return;
			}

			$telco = Constant::TELCO[$telcoIndex] ?? Constant::TELCO[0];
			$amount = Constant::AMOUNT[$amountIndex] ?? Constant::AMOUNT[0];
			$requestId = Uuid::uuid4()->toString();

			$this->plugin->debugLogger->log("Player {$player->getName()} submitted card payment - Telco: $telco, Amount: $amount, RequestID: $requestId", "payment");

			$response = $this->plugin->getPaymentManager()->processCardPayment(
				$player,
				$telco,
				$code,
				$serial,
				$amount,
				$requestId
			);

			if ($response->isSuccessful() || $response->isPending()) {
				$player->sendMessage(MessageTranslator::formatInfoMessage("Your card is being processed. Please wait..."));
				$this->plugin->debugLogger->log("Card processing started for {$player->getName()} - RequestID: $requestId", "payment");
				return;
			}

			$player->sendMessage($response->getFriendlyMessage());
			$this->plugin->debugLogger->log("Card processing failed immediately for {$player->getName()} - Status: {$response->getStatus()}", "payment");
		});

		$form->setTitle("Prepaid Card")
			->addDropdown("Card provider", Constant::TELCO_DISPLAY, 0, "telco")
			->addDropdown("Card amount", Constant::AMOUNT_DISPLAY, 0, "amount")
			->addInput("Serial number", "Example: 1000...", null, "serial")
			->addInput("Card code", "Example: 3128...", null, "code");

		return $form;
	}
}
