<?php

declare(strict_types=1);

namespace Donate\manager;

use Donate\Constant;
use Donate\Donate;
use Donate\utils\DataTypeUtils;
use Donate\utils\FormAPI\CustomForm;
use Donate\utils\FormAPI\ModalForm;
use Donate\utils\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use Ramsey\Uuid\Uuid;
use function arsort;
use function ceil;
use function count;
use function implode;
use function is_array;
use function is_numeric;
use function max;
use function min;
use function number_format;
use function strpos;
use function substr;
use function time;

class FormManager {
	private Donate $plugin;

	/** @var array<string, int> Last form submission time by player */
	public array $lastFormSubmission = [];

	/** @var array<string, int> Last donate form submission time by player */
	public array $lastDonateFormSubmission = [];

	private int $formCooldown = 5;

	public function getFormCooldown() : int {
		return $this->formCooldown;
	}

	public function __construct(Donate $plugin) {
		$this->plugin = $plugin;
		$this->formCooldown = max(1, Constant::FORM_COOLDOWN);

		$plugin->debugLogger->log("Form cooldown set to {$this->formCooldown} seconds", "form");
	}

	public function sendDonateForm(Player $player) : void {
		$playerName = $player->getName();
		$currentTime = time();

		if (isset($this->lastDonateFormSubmission[$playerName])) {
			$lastTime = $this->lastDonateFormSubmission[$playerName];
			$timeDiff = $currentTime - $lastTime;

			if ($timeDiff < $this->formCooldown) {
				$remainingTime = $this->formCooldown - $timeDiff;
				$player->sendMessage(\Donate\utils\MessageTranslator::formatErrorMessage("Vui lòng đợi {$remainingTime} giây trước khi mở form nạp thẻ lại."));
				$this->plugin->debugLogger->log("Form spam prevented - Player: {$playerName} tried to open donate form too quickly", "form");
				return;
			}
		}

		$this->lastDonateFormSubmission[$playerName] = $currentTime;

		$this->plugin->logger->info("[Donate/Form] Sending donate form to player: " . $player->getName());
		$player->sendForm($this->createDonateForm());
	}

	public function sendTopDonateForm(Player $player, int $page = 1) : void {
		$this->plugin->logger->info("[Donate/Form] Sending top donate form to player: " . $player->getName() . ", page: " . $page);
		$this->plugin->debugLogger->log("Sending top donate form to player: " . $player->getName() . ", page: " . $page, "form");
		$player->sendForm($this->createTopDonateForm($page));
	}

	private function createDonateForm() : CustomForm {
		$form = new CustomForm(function(Player $player, mixed $data) : void {
			if ($data === null) {
				$this->plugin->logger->info("[Donate/Form] Player " . $player->getName() . " closed the donate form");
				return;
			}

			$playerName = $player->getName();
			$currentTime = time();
			$formManager = $this->plugin->getFormManager();

			if (isset($formManager->lastDonateFormSubmission[$playerName])) {
				$lastTime = $formManager->lastDonateFormSubmission[$playerName];
				$timeDiff = $currentTime - $lastTime;

				if ($timeDiff < $formManager->getFormCooldown()) {
					$remainingTime = $formManager->getFormCooldown() - $timeDiff;
					$player->sendMessage(\Donate\utils\MessageTranslator::formatErrorMessage("Gửi quá nhanh! Vui lòng đợi {$remainingTime} giây trước khi gửi lại."));
					$this->plugin->debugLogger->log("Form submission throttled - Player: {$playerName} tried to submit too quickly", "form");
					return;
				}
			}

			$formManager->lastDonateFormSubmission[$playerName] = $currentTime;

			if (!is_array($data)) {
				$player->sendMessage(\Donate\utils\MessageTranslator::formatErrorMessage("Có lỗi xảy ra khi xử lý biểu mẫu!"));
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
				$player->sendMessage(\Donate\utils\MessageTranslator::formatInfoMessage("Thẻ của bạn đang được xử lý, vui lòng đợi..."));
				$this->plugin->debugLogger->log("Card processing started for {$player->getName()} - RequestID: $requestId", "payment");

				if ($player->hasPermission("donate.admin")) {
					$this->plugin->debugLogger->sendToPlayer($player, "Thẻ đang được xử lý với ID: $requestId", "payment");
				}
				return;
			}

			$this->plugin->debugLogger->log("Got error message from API: '{$response->getMessage()}'", "payment");
			$message = $response->getMessage();
			if (strpos($message, "card_existed") !== false) {
				$player->sendMessage(\Donate\utils\MessageTranslator::formatErrorMessage("Thẻ này đã được sử dụng trước đó"));
			} else {
				$player->sendMessage(\Donate\utils\MessageTranslator::formatErrorMessage($message));
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

	private function createTopDonateForm(int $page = 1) : SimpleForm {
		$form = new SimpleForm(function(Player $player, mixed $selection) use ($page) : void {
			if ($selection === null) {
				$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " closed the form", "form");
				return;
			}

			$selectionLabel = DataTypeUtils::toString($selection);
			$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " clicked button label: " . $selectionLabel, "form");

			$donateData = $this->plugin->getDonateData()->getAll();
			if (empty($donateData)) {
				$this->plugin->debugLogger->log("TopDonate form: No donation data found when handling response", "form");
				return;
			}

			$totalPlayers = count($donateData);
			$itemsPerPage = 10;
			$maxPage = (int) ceil($totalPlayers / $itemsPerPage);
			$currentPage = max(1, min($page, $maxPage));

			switch ($selectionLabel) {
				case "prev":
					if ($currentPage > 1) {
						$this->plugin->debugLogger->log("TopDonate form: Going to previous page: " . ($currentPage - 1), "form");
						$this->plugin->getFormManager()->sendTopDonateForm($player, $currentPage - 1);
					} else {
						$this->plugin->debugLogger->log("TopDonate form: Attempted to go to previous page while on first page", "form");
						$player->sendTip(Constant::PREFIX . "§eĐây là trang đầu tiên rồi.");
						$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void {
							if ($player->isOnline()) {
								$this->plugin->debugLogger->log("TopDonate form: Reopening first page after delay", "form");
								$this->plugin->getFormManager()->sendTopDonateForm($player, 1);
							}
						}), 30);
					}
					return;

				case "next":
					if ($currentPage < $maxPage) {
						$this->plugin->debugLogger->log("TopDonate form: Going to next page: " . ($currentPage + 1), "form");
						$this->plugin->getFormManager()->sendTopDonateForm($player, $currentPage + 1);
					} else {
						$this->plugin->debugLogger->log("TopDonate form: Attempted to go to next page while on last page", "form");
						$player->sendTip(Constant::PREFIX . "§eĐây là trang cuối rồi.");
						$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $maxPage) : void {
							if ($player->isOnline()) {
								$this->plugin->debugLogger->log("TopDonate form: Reopening last page after delay", "form");
								$this->plugin->getFormManager()->sendTopDonateForm($player, $maxPage);
							}
						}), 30);
					}
					return;

				case "goto":
					$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " clicked Go To Page button", "form");
					$this->openGoToPageForm($player, $currentPage, $maxPage);
					return;

				default:
					$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " clicked Close button", "form");
					return;
			}
		});

		$donateData = $this->plugin->getDonateData()->getAll();
		$this->plugin->debugLogger->log("TopDonate form: Loaded " . count($donateData) . " donation records", "form");

		if (empty($donateData)) {
			$this->plugin->debugLogger->log("TopDonate form: No donation data found, showing empty message", "form");
			return $form
				->setTitle("§l§f• §8[§6Xếp Hạng Donate§8] §f•")
				->setContent("§cHiện chưa có một ai nạp thẻ ủng hộ máy chủ...")
				->addButton("§l§8« §cĐóng §8»", -1, "", "close");
		}

		arsort($donateData);

		$totalPlayers = count($donateData);
		$itemsPerPage = 10;
		$maxPage = (int) ceil($totalPlayers / $itemsPerPage);
		$page = max(1, min($page, $maxPage));

		$this->plugin->debugLogger->log("TopDonate form: Page info - Current: " . $page . ", Max: " . $maxPage . ", Total Players: " . $totalPlayers, "form");

		$startIndex = ($page - 1) * $itemsPerPage;
		$i = 0;
		$topList = [];
		$serverTotalDonated = 0;

		foreach ($donateData as $playerName => $amount) {
			$amountValue = is_numeric($amount) ? (int) $amount : 0;
			$serverTotalDonated += $amountValue;

			if ($i >= $startIndex && $i < $startIndex + $itemsPerPage) {
				$rank = $i + 1;
				$formattedAmount = number_format($amountValue, 0, ",", ".");
				$topList[] = "§l§f$rank. §e$playerName: §a{$formattedAmount}₫";
			}

			$i++;
		}

		$formattedTotal = number_format($serverTotalDonated, 0, ",", ".");
		$content = "§l§6• §fBảng xếp hạng donate trang §a" . $page . "§f/§a" . $maxPage . " §6•\n\n";
		$content .= implode("\n", $topList);
		$content .= "\n\n§l§6• §fTổng số tiền nạp thẻ từ người chơi: §a{$formattedTotal}₫ §6•";

		$form->setTitle("§l§f• §8[§6Xếp Hạng Donate§8] §f•")
			->setContent($content)
			->addButton($page > 1 ? "§l§8« §aTrang Trước" : "§l§8« §0Trang Trước", -1, "", "prev")
			->addButton("§l§8« §cĐóng §8»", -1, "", "close")
			->addButton($page < $maxPage ? "§l§aTrang Sau §8»" : "§l§0Trang Sau §8»", -1, "", "next")
			->addButton("§l§8« §eNhập Số Trang §8»", -1, "", "goto");

		return $form;
	}

	private function openGoToPageForm(Player $player, int $currentPage, int $maxPage) : void {
		$form = new CustomForm(function(Player $player, mixed $data) use ($currentPage, $maxPage) : void {
			if ($data === null) {
				$this->plugin->debugLogger->log("GoToPage form: Player " . $player->getName() . " closed the form", "form");
				$this->plugin->getFormManager()->sendTopDonateForm($player, $currentPage);
				return;
			}

			if (!is_array($data)) {
				$this->plugin->getFormManager()->sendTopDonateForm($player, $currentPage);
				return;
			}

			$pageInput = DataTypeUtils::toString($data["page"] ?? "");
			$this->plugin->debugLogger->log("GoToPage form: Player " . $player->getName() . " entered page: " . $pageInput, "form");

			if (!is_numeric($pageInput)) {
				$player->sendMessage(Constant::PREFIX . "§cVui lòng nhập một số hợp lệ!");
				$this->plugin->getFormManager()->sendTopDonateForm($player, $currentPage);
				return;
			}

			$pageNumber = (int) $pageInput;
			if ($pageNumber < 1 || $pageNumber > $maxPage) {
				$player->sendMessage(Constant::PREFIX . "§cSố trang phải từ 1 đến {$maxPage}!");
				$this->plugin->getFormManager()->sendTopDonateForm($player, $currentPage);
				return;
			}

			$this->plugin->debugLogger->log("GoToPage form: Going to page " . $pageNumber, "form");
			$this->plugin->getFormManager()->sendTopDonateForm($player, $pageNumber);
		});

		$form->setTitle("§l§f• §8[§6Chuyển Trang§8] §f•")
			->addLabel("§fNhập số trang bạn muốn xem (1-{$maxPage}):", "info")
			->addInput("Số trang", "Nhập số từ 1 đến {$maxPage}", null, "page");

		$player->sendForm($form);
	}

	public function sendConfirmationForm(Player $player, string $message, callable $onConfirm) : void {
		$player->sendForm($this->createConfirmationForm($message, $onConfirm));
	}

	private function createConfirmationForm(string $message, callable $onConfirm) : ModalForm {
		$form = new ModalForm(function(Player $player, mixed $confirmed) use ($onConfirm) : void {
			if ($confirmed === true) {
				$onConfirm();
			}
		});

		$form->setTitle("Xác nhận")
			->setContent($message)
			->setButton1("Xác nhận")
			->setButton2("Hủy");

		return $form;
	}
}
