<?php

declare(strict_types=1);

namespace NapThe;

use NapThe\manager\FormManager;
use NapThe\manager\PaymentManager;
use NapThe\traits\NapTheTopNapTheTrait;
use NapThe\utils\DebugLogger;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use function count;
use function implode;
use function in_array;
use function is_numeric;
use function max;
use function mkdir;
use function strlen;
use function strtoupper;
use function substr;
use function time;
use function uniqid;
use function version_compare;
use const PHP_VERSION;

class NapThe extends PluginBase {
	use SingletonTrait;
	use NapTheTopNapTheTrait;

	public DebugLogger $debugLogger;

	private Config $napTheData;
	private FormManager $formManager;
	private PaymentManager $paymentManager;

	protected function onLoad() : void {
		self::setInstance($this);

		// Chuẩn bị thư mục của plugin
		@mkdir($this->getDataFolder());
	}

	protected function onEnable() : void {
		// Kiểm tra phiên bản PHP
		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			$this->getLogger()->error("Plugin require PHP 8.2.0 or higher. Current version: " . PHP_VERSION);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		api\TrumTheAPI::setApiCooldown(Constant::API_COOLDOWN);

		$this->napTheData = new Config($this->getDataFolder() . "naptheData.yml", Config::YAML);

		// Khởi tạo hệ thống ghi lỗi vào tệp (file-only, daily log files)
		$this->debugLogger = new DebugLogger($this);

		// Kiểm tra tài khoản API có hợp lệ không
		if (Constant::PARTNER_ID === "" || Constant::PARTNER_KEY === "") {
			$this->getLogger()->error("Vui lòng cấu hình PARTNER_ID và PARTNER_KEY trong src/Constant.php!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		// Khởi tạo các trình quản lý
		$this->formManager = new FormManager($this);
		$this->paymentManager = new PaymentManager($this);

		// Bắt đầu tiến trình kiểm tra thanh toán
		$this->paymentManager->startPaymentCheckTask();

		$this->debugLogger->log("Plugin enabled successfully", "general");
	}

	protected function onDisable() : void {
		// Lưu các dữ liệu còn đang chờ
		if (isset($this->napTheData)) {
			$this->napTheData->save();
		}

		if (isset($this->debugLogger)) {
			$this->debugLogger->log("Plugin disabled", "general");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		$commandName = $command->getName();

		// Ghi nhật ký sử dụng lệnh
		$senderName = $sender instanceof Player ? $sender->getName() : "Console";
		$argsString = !empty($args) ? " " . implode(" ", $args) : "";
		$this->debugLogger->log("Command executed: $senderName used /$commandName$argsString", "command");

		switch ($commandName) {
			case "napthe":
				if ($sender instanceof Player) {
					// Người chơi thực thi lệnh, mở form napthe
					$this->debugLogger->log("Opening napthe form for player: " . $sender->getName(), "command");
					$this->formManager->sendNapTheForm($sender);
					return true;
				} else {
					// Nếu console thực thi lệnh với đầy đủ tham số
					// Định dạng: /napthe <tên người chơi> <telco> <code> <serial> <amount>
					if (isset($args[0], $args[1], $args[2], $args[3], $args[4])) {
						$playerName = $args[0];
						$telco = strtoupper($args[1]);
						$code = $args[2];
						$serial = $args[3];
						$amount = (int) $args[4];

						// Tìm player nếu đang online
						$targetPlayer = $this->getServer()->getPlayerExact($playerName);

						// Kiểm tra tính hợp lệ của telco
						$validTelcos = ["VIETTEL", "MOBIFONE", "VINAPHONE", "VIETNAMOBILE", "ZING"];
						if (!in_array($telco, $validTelcos, true)) {
							$sender->sendMessage(Constant::PREFIX . "Loại thẻ không hợp lệ. Hỗ trợ: " . implode(", ", $validTelcos));
							return true;
						}

						// Kiểm tra mệnh giá
						$validAmounts = [10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000, 1000000];
						if (!in_array($amount, $validAmounts, true)) {
							$sender->sendMessage(Constant::PREFIX . "Mệnh giá không hợp lệ. Hỗ trợ: " . implode(", ", $validAmounts));
							return true;
						}

						// Kiểm tra độ dài mã thẻ và serial
						if (strlen($code) < 10) {
							$sender->sendMessage(Constant::PREFIX . "Mã thẻ không hợp lệ, quá ngắn.");
							return true;
						}

						if (strlen($serial) < 10) {
							$sender->sendMessage(Constant::PREFIX . "Serial không hợp lệ, quá ngắn.");
							return true;
						}

						// Tạo request ID
						$requestId = uniqid("console_", true);

						$this->debugLogger->log("Console initiated card payment for $playerName - Telco: $telco, Amount: $amount, RequestID: $requestId", "command");

						// Xử lý nạp thẻ
						if ($targetPlayer !== null) {
							// Nếu người chơi online, sử dụng processCardPayment từ PaymentManager
							$response = $this->paymentManager->processCardPayment(
								$targetPlayer,
								$telco,
								$code,
								$serial,
								$amount,
								$requestId
							);

							// Thông báo kết quả ban đầu
							if ($response->isSuccessful() || $response->isPending()) {
								$sender->sendMessage(Constant::PREFIX . "Đã gửi yêu cầu nạp thẻ cho người chơi " . $playerName);
								$sender->sendMessage(Constant::PREFIX . "Mã giao dịch: " . substr($requestId, 0, 10));
								$targetPlayer->sendMessage(Constant::PREFIX . "Admin đã nạp thẻ giúp bạn, hệ thống đang xử lý.");
							} else {
								$sender->sendMessage(Constant::PREFIX . "Có lỗi xảy ra khi nạp thẻ: " . $response->getMessage());
							}
						} else {
							// Nếu người chơi offline, sử dụng task
							$this->getServer()->getAsyncPool()->submitTask(new tasks\ChargingTask(
								$playerName,
								$telco,
								$code,
								$serial,
								$amount,
								$requestId
							));

							// Dùng CardPayment để lưu trữ thông tin giao dịch
							$payment = new payment\CardPayment(
								$requestId,
								$playerName,
								$telco,
								$code,
								$serial,
								$amount,
								time()
							);

							// Thêm vào danh sách thanh toán đang xử lý
							$this->paymentManager->addSamplePayment($requestId, $payment);

							$sender->sendMessage(Constant::PREFIX . "Đã gửi yêu cầu nạp thẻ cho người chơi " . $playerName . " (đang offline).");
							$sender->sendMessage(Constant::PREFIX . "Mã giao dịch: " . substr($requestId, 0, 10));
						}

						return true;
					} else {
						// Hiển thị hướng dẫn sử dụng
						$sender->sendMessage(Constant::PREFIX . "Hướng dẫn sử dụng lệnh /donate:");
						$sender->sendMessage("  /napthe [tên] [telco] [mã thẻ] [serial] [mệnh giá]");
						$sender->sendMessage("  Ví dụ: /napthe NhanAZ VIETTEL 123456789012 123456789 50000");
						$sender->sendMessage("  Loại thẻ: VIETTEL, MOBIFONE, VINAPHONE");
						$sender->sendMessage("  Mệnh giá: 10k, 20k, 50k, 100k, 200k, 500k");

						// Hiển thị danh sách người chơi trực tuyến để tham khảo
						$onlinePlayers = $this->getServer()->getOnlinePlayers();
						if (count($onlinePlayers) > 0) {
							$sender->sendMessage(Constant::PREFIX . "Người chơi trực tuyến:");

							foreach ($onlinePlayers as $player) {
								$sender->sendMessage("  - " . $player->getName());
							}
						}
					}
					return true;
				}

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
		$formattedAmount = utils\MessageTranslator::formatAmount($amount);

		// Gửi thông báo nạp thẻ trên toàn server
		$this->getServer()->broadcastMessage(utils\MessageTranslator::formatSuccessMessage("Người chơi $playerName đã nạp {$formattedAmount} để ủng hộ máy chủ!"));

		// Cập nhật thống kê người chơi an toàn
		$currentAmount = $this->napTheData->getNested($playerName, 0);
		$safeAmount = is_numeric($currentAmount) ? (int) $currentAmount : 0;
		$this->napTheData->setNested($playerName, $safeAmount + $amount);
		$this->napTheData->save();

		// Tính toán số tiền khuyến mãi
		$bonusAmount = (int) ($amount * Constant::BONUS);

		// Thêm phần thưởng phụ (ví dụ xu, vật phẩm) tại đây
		// Example: EconomyAPI::getInstance()->addMoney($playerName, $bonusAmount);

		// Thông báo nếu người chơi đang online
		$player = $this->getServer()->getPlayerExact($playerName);
		if ($player !== null) {
			$player->sendMessage(utils\MessageTranslator::formatSuccessMessage("Chân thành cảm ơn bạn đã ủng hộ máy chủ {$formattedAmount}!"));
			$player->sendMessage(utils\MessageTranslator::formatSuccessMessage("Bạn đã nhận được " . utils\MessageTranslator::formatAmount($bonusAmount) . " xu"));
		}

		$this->debugLogger->log("Successful donation - Player: $playerName, Amount: $amount, Bonus: $bonusAmount", "payment");
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
