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
			$player->sendMessage(MessageTranslator::formatErrorMessage("Vui lòng đợi {$remainingTime} giây trước khi mở form nạp thẻ lại."));
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
				$player->sendMessage(MessageTranslator::formatErrorMessage("Gửi quá nhanh! Vui lòng đợi {$remainingTime} giây trước khi gửi lại."));
				$this->plugin->debugLogger->log("Form submission throttled - Player: {$playerName} tried to submit too quickly", "form");
				return;
			}

			$this->cooldownTracker->touchNapTheCooldown($playerName, $currentTime);

			if (!is_array($data)) {
				$player->sendMessage(MessageTranslator::formatErrorMessage("Có lỗi xảy ra khi xử lý biểu mẫu!"));
				return;
			}

			$telcoIndex = DataTypeUtils::toInt($data["telco"] ?? 0);
			$amountIndex = DataTypeUtils::toInt($data["amount"] ?? 0);
			$serial = DataTypeUtils::toString($data["serial"] ?? "");
			$code = DataTypeUtils::toString($data["code"] ?? "");

			if ($serial === "" || $code === "") {
				$player->sendMessage("Vui lòng không để trống số sê-ri hoặc mã thẻ.");
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
				$player->sendMessage(MessageTranslator::formatInfoMessage("Thẻ của bạn đang được xử lý, vui lòng đợi..."));
				$this->plugin->debugLogger->log("Card processing started for {$player->getName()} - RequestID: $requestId", "payment");
				return;
			}

			$player->sendMessage($response->getFriendlyMessage());
			$this->plugin->debugLogger->log("Card processing failed immediately for {$player->getName()} - Status: {$response->getStatus()}", "payment");
		});

		$form->setTitle("Nạp Thẻ")
			->addDropdown("Loại thẻ", Constant::TELCO_DISPLAY, 0, "telco")
			->addDropdown("Mệnh giá", Constant::AMOUNT_DISPLAY, 0, "amount")
			->addInput("Số sê-ri", "Ví dụ: 1000...", null, "serial")
			->addInput("Mã thẻ", "Ví dụ: 3128...", null, "code");

		return $form;
	}
}
