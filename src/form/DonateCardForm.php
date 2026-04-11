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
			$player->sendMessage(MessageTranslator::formatErrorMessage("Vui lòng đợi {$remainingTime} giây trước khi mở form nạp thẻ lại."));
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
				$player->sendMessage(MessageTranslator::formatErrorMessage("Gửi quá nhanh! Vui lòng đợi {$remainingTime} giây trước khi gửi lại."));
				$this->plugin->debugLogger->log("Form submission throttled - Player: {$playerName} tried to submit too quickly", "form");
				return;
			}

			$this->cooldownTracker->touchDonateCooldown($playerName, $currentTime);

			if (!is_array($data)) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("Có lỗi xảy ra khi xử lý biểu mẫu!"));
				return;
			}

			$telcoIndex = DataTypeUtils::toInt($data["telco"] ?? 0);
			$amountIndex = DataTypeUtils::toInt($data["amount"] ?? 0);
			$serial = DataTypeUtils::toString($data["serial"] ?? "");
			$code = DataTypeUtils::toString($data["code"] ?? "");

			if ($serial === "" || $code === "") {
				$player->sendMessage(Constant::PREFIX . "§cVui lòng không để trống số sê-ri hoặc mã thẻ!");
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
				$player->sendMessage(MessageTranslator::formatInfoMessage("Thẻ của bạn đang được xử lý, vui lòng đợi..."));
				$this->plugin->debugLogger->log("Card processing started for {$player->getName()} - RequestID: $requestId", "payment");

				if ($player->hasPermission("donate.admin")) {
					$this->plugin->debugLogger->sendToPlayer($player, "Tháº» Ä‘ang Ä‘Æ°á»£c xá»­ lÃ½ vá»›i ID: $requestId", "payment");
				}
				return;
			}

			$this->plugin->debugLogger->log("Got error message from API: '{$response->getMessage()}'", "payment");
			$message = $response->getMessage();

			if (strpos($message, "card_existed") !== false) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("Thẻ này đã được sử dụng trước đó"));
			} else {
				$player->sendMessage(MessageTranslator::formatErrorMessage($message));
			}

			$this->plugin->debugLogger->log("Card processing failed immediately for {$player->getName()} - Reason: {$response->getMessage()}", "payment");
		});

		$form->setTitle("Nạp Thẻ")
			->addDropdown("Loại thẻ", Constant::TELCO_DISPLAY, 0, "telco")
			->addDropdown("Mệnh giá", Constant::AMOUNT_DISPLAY, 0, "amount")
			->addInput("Số sê-ri", "Ví dụ: 10004783347874", null, "serial")
			->addInput("Mã thẻ", "Ví dụ: 312821445892982", null, "code");

		return $form;
	}
}
