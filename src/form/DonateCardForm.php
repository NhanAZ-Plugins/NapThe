<?php

declare(strict_types=1);

namespace Donate\form;

use Donate\Constant;
use Donate\Donate;
use Donate\utils\DataTypeUtils;
use Donate\utils\FormAPI\CustomForm;
use Donate\utils\MessageTranslator;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;
use function is_array;
use function strpos;
use function substr;
use function time;

final class DonateCardForm {
	public function __construct(
		private Donate $plugin,
		private FormCooldownTracker $cooldownTracker
	) {
	}

	public function send(Player $player) : void {
		$playerName = $player->getName();
		$currentTime = time();
		$remainingTime = $this->cooldownTracker->getRemainingDonateCooldown($playerName, $currentTime);

		if ($remainingTime > 0) {
			$player->sendMessage(MessageTranslator::formatErrorMessage("Vui lÃ²ng Ä‘á»£i {$remainingTime} giÃ¢y trÆ°á»›c khi má»Ÿ form náº¡p tháº» láº¡i."));
			$this->plugin->debugLogger->log("Form spam prevented - Player: {$playerName} tried to open donate form too quickly", "form");
			return;
		}

		$this->cooldownTracker->touchDonateCooldown($playerName, $currentTime);
		$this->plugin->logger->info("[Donate/Form] Sending donate form to player: " . $player->getName());
		$player->sendForm($this->create());
	}

	private function create() : CustomForm {
		$form = new CustomForm(function(Player $player, mixed $data) : void {
			if ($data === null) {
				$this->plugin->logger->info("[Donate/Form] Player " . $player->getName() . " closed the donate form");
				return;
			}

			$playerName = $player->getName();
			$currentTime = time();
			$remainingTime = $this->cooldownTracker->getRemainingDonateCooldown($playerName, $currentTime);

			if ($remainingTime > 0) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("Gá»­i quÃ¡ nhanh! Vui lÃ²ng Ä‘á»£i {$remainingTime} giÃ¢y trÆ°á»›c khi gá»­i láº¡i."));
				$this->plugin->debugLogger->log("Form submission throttled - Player: {$playerName} tried to submit too quickly", "form");
				return;
			}

			$this->cooldownTracker->touchDonateCooldown($playerName, $currentTime);

			if (!is_array($data)) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("CÃ³ lá»—i xáº£y ra khi xá»­ lÃ½ biá»ƒu máº«u!"));
				return;
			}

			$telcoIndex = DataTypeUtils::toInt($data["telco"] ?? 0);
			$amountIndex = DataTypeUtils::toInt($data["amount"] ?? 0);
			$serial = DataTypeUtils::toString($data["serial"] ?? "");
			$code = DataTypeUtils::toString($data["code"] ?? "");

			if ($serial === "" || $code === "") {
				$player->sendMessage(Constant::PREFIX . "Â§cVui lÃ²ng khÃ´ng Ä‘á»ƒ trá»‘ng sá»‘ sÃª-ri hoáº·c mÃ£ tháº»!");
				$this->plugin->logger->info("[Donate/Form] Player " . $player->getName() . " submitted form with empty serial or code");
				$this->plugin->debugLogger->log("Player {$player->getName()} submitted form with empty serial or code", "payment");
				return;
			}

			$telco = Constant::TELCO[$telcoIndex] ?? Constant::TELCO[0];
			$amount = Constant::AMOUNT[$amountIndex] ?? Constant::AMOUNT[0];
			$requestId = Uuid::uuid4()->toString();

			$this->plugin->logger->info("[Donate/Payment] Player " . $player->getName() . " submitted card payment - Telco: $telco, Amount: $amount");
			$this->plugin->debugLogger->log("Player {$player->getName()} submitted donation form - Telco: $telco, Amount: $amount", "payment");

			$maskedSerial = substr($serial, 0, 4) . "****" . substr($serial, -4);
			$maskedCode = substr($code, 0, 2) . "****" . substr($code, -2);
			$this->plugin->debugLogger->log("Card details - Serial: $maskedSerial, Code: $maskedCode, RequestID: $requestId", "payment");

			$response = $this->plugin->getPaymentManager()->processCardPayment(
				$player,
				$telco,
				$code,
				$serial,
				$amount,
				$requestId
			);

			if ($response->isSuccessful() || $response->isPending()) {
				$player->sendMessage(MessageTranslator::formatInfoMessage("Tháº» cá»§a báº¡n Ä‘ang Ä‘Æ°á»£c xá»­ lÃ½, vui lÃ²ng Ä‘á»£i..."));
				$this->plugin->debugLogger->log("Card processing started for {$player->getName()} - RequestID: $requestId", "payment");

				if ($player->hasPermission("donate.admin")) {
					$this->plugin->debugLogger->sendToPlayer($player, "Tháº» Ä‘ang Ä‘Æ°á»£c xá»­ lÃ½ vá»›i ID: $requestId", "payment");
				}
				return;
			}

			$this->plugin->debugLogger->log("Got error message from API: '{$response->getMessage()}'", "payment");
			$message = $response->getMessage();

			if (strpos($message, "card_existed") !== false) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("Tháº» nÃ y Ä‘Ã£ Ä‘Æ°á»£c sá»­ dá»¥ng trÆ°á»›c Ä‘Ã³"));
			} else {
				$player->sendMessage(MessageTranslator::formatErrorMessage($message));
			}

			$this->plugin->debugLogger->log("Card processing failed immediately for {$player->getName()} - Reason: {$response->getMessage()}", "payment");
		});

		$form->setTitle("Náº¡p Tháº»")
			->addDropdown("Loáº¡i tháº»", Constant::TELCO_DISPLAY, 0, "telco")
			->addDropdown("Má»‡nh giÃ¡", Constant::AMOUNT_DISPLAY, 0, "amount")
			->addInput("Sá»‘ sÃª-ri", "VÃ­ dá»¥: 10004783347874", null, "serial")
			->addInput("MÃ£ tháº»", "VÃ­ dá»¥: 312821445892982", null, "code");

		return $form;
	}
}
