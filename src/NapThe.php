<?php

declare(strict_types=1);

namespace NapThe;

use NapThe\manager\FormManager;
use NapThe\manager\PaymentManager;
use NapThe\payment\DonationLedger;
use NapThe\traits\NapTheTopNapTheTrait;
use NapThe\utils\DebugLogger;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use function bin2hex;
use function is_numeric;
use function max;
use function mkdir;
use function random_bytes;
use function version_compare;
use const PHP_VERSION;

class NapThe extends PluginBase {
	use SingletonTrait;
	use NapTheTopNapTheTrait;

	public DebugLogger $debugLogger;

	private Config $napTheData;
	private DonationLedger $donationLedger;
	private FormManager $formManager;
	private PaymentManager $paymentManager;
	private bool $ready = false;

	protected function onLoad() : void {
		self::setInstance($this);

		// Chuẩn bị thư mục của plugin
		@mkdir($this->getDataFolder());
	}

	protected function onEnable() : void {
		// Kiểm tra phiên bản PHP
		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			$this->getLogger()->error("Plugin require PHP 8.2.0 or higher. Current version: " . PHP_VERSION);
			return;
		}

		api\TrumTheAPI::setApiCooldown(Constant::API_COOLDOWN);

		$this->napTheData = new Config($this->getDataFolder() . "naptheData.yml", Config::YAML);

		// Khởi tạo hệ thống ghi lỗi vào tệp (file-only, daily log files)
		$this->debugLogger = new DebugLogger($this);

		// Kiểm tra tài khoản API có hợp lệ không
		if (Constant::partnerId() === "" || Constant::partnerKey() === "") {
			$this->getLogger()->error("Set TRUMTHE_PARTNER_ID and TRUMTHE_PARTNER_KEY in the server process environment.");
			return;
		}
		try {
			Constant::apiUrl();
		} catch (\InvalidArgumentException $e) {
			$this->getLogger()->error($e->getMessage());
			return;
		}
		try {
			$this->donationLedger = new DonationLedger($this->getDataFolder() . 'donations.sqlite', $this->napTheData->getAll());
			$this->syncDonationTotals();
		} catch (\Throwable $e) {
			$this->getLogger()->error('Could not initialize the donation ledger: ' . $e->getMessage());
			return;
		}

		// Khởi tạo các trình quản lý
		$this->formManager = new FormManager($this);
		$this->paymentManager = new PaymentManager($this);

		// Bắt đầu tiến trình kiểm tra thanh toán
		$this->paymentManager->startPaymentCheckTask();
		$this->ready = true;

		$this->debugLogger->log("Plugin enabled successfully", "general");
	}

	protected function onDisable() : void {
		$this->ready = false;
		// Lưu các dữ liệu còn đang chờ
		if (isset($this->napTheData)) {
			$this->napTheData->save();
		}

		if (isset($this->debugLogger)) {
			$this->debugLogger->log("Plugin disabled", "general");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		if (!$this->ready) {
			$sender->sendMessage(Constant::PREFIX . "NapThe chưa sẵn sàng. Kiểm tra PHP và biến môi trường TrumThe trên server.");
			return true;
		}

		$commandName = $command->getName();

		// Ghi nhật ký sử dụng lệnh
		$senderName = $sender instanceof Player ? $sender->getName() : "Console";
		$this->debugLogger->log("Command executed: $senderName used /$commandName", "command");

		switch ($commandName) {
			case "napthe":
				if ($sender instanceof Player) {
					// Người chơi thực thi lệnh, mở form napthe
					$this->debugLogger->log("Opening napthe form for player: " . $sender->getName(), "command");
					$this->formManager->sendNapTheForm($sender);
				} else {
					$sender->sendMessage(Constant::PREFIX . "Lệnh này chỉ có thể sử dụng trong trò chơi.");
				}
				return true;

			case "topnapthe":
				$page = 1;
				if (isset($args[0]) && is_numeric($args[0])) {
					$page = max(1, (int) $args[0]);
				}

				$this->debugLogger->log("TopNapThe command: $senderName requested page $page", "command");

				// Nếu là player thì hiển thị form, nếu là console thì hiển thị text
				if ($sender instanceof Player) {
					$this->debugLogger->log("Sending TopNapThe form to player: " . $sender->getName(), "command");
					$this->formManager->sendTopNapTheForm($sender, $page);
				} else {
					$this->debugLogger->log("Showing TopNapThe text to console", "command");
					$this->showTopDonators($sender, $page);
				}
				return true;

			default:
				return false;
		}
	}

	public function successfulDonation(string $playerName, int $amount) : void {
		$this->successfulDonationOnce('manual-' . bin2hex(random_bytes(16)), $playerName, $amount);
	}

	public function successfulDonationOnce(string $requestId, string $playerName, int $amount) : bool {
		$recorded = $this->donationLedger->record($requestId, $playerName, $amount);
		$this->syncDonationTotals();
		if (!$recorded) {
			return false;
		}

		$formattedAmount = utils\MessageTranslator::formatAmount($amount);

		// Gửi thông báo nạp thẻ trên toàn server
		$this->getServer()->broadcastMessage(utils\MessageTranslator::formatSuccessMessage("Người chơi $playerName đã nạp {$formattedAmount} để ủng hộ máy chủ!"));

		// Thông báo nếu người chơi đang online
		$player = $this->getServer()->getPlayerExact($playerName);
		if ($player !== null) {
			$player->sendMessage(utils\MessageTranslator::formatSuccessMessage("Chân thành cảm ơn bạn đã ủng hộ máy chủ {$formattedAmount}!"));
		}

		$this->debugLogger->log("Successful donation - Player: $playerName, Amount: $amount", "payment");
		return true;
	}

	private function syncDonationTotals() : void {
		$this->napTheData->setAll([]);
		foreach ($this->donationLedger->totals() as $playerName => $amount) {
			$this->napTheData->setNested((string) $playerName, $amount);
		}
		$this->napTheData->save();
	}

	public function getNapTheData() : Config {
		return $this->napTheData;
	}

	public function getFormManager() : FormManager {
		return $this->formManager;
	}

	public function getPaymentManager() : PaymentManager {
		return $this->paymentManager;
	}

}
