<?php

declare(strict_types=1);

namespace Donate;

use Donate\manager\FormManager;
use Donate\manager\PaymentManager;
use Donate\traits\DonateTopDonateTrait;
use Donate\utils\DebugLogger;
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

class Donate extends PluginBase {
	use SingletonTrait;
	use DonateTopDonateTrait;

	public DebugLogger $debugLogger;

	private Config $donateData;
	private FormManager $formManager;
	private PaymentManager $paymentManager;

	protected function onLoad() : void {
		self::setInstance($this);

		// Prepare plugin directory
		@mkdir($this->getDataFolder());
	}

	protected function onEnable() : void {
		// Check PHP version
		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			$this->getLogger()->error("Plugin require PHP 8.2.0 or higher. Current version: " . PHP_VERSION);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		api\TrumTheAPI::setApiCooldown(Constant::API_COOLDOWN);

		$this->donateData = new Config($this->getDataFolder() . "donateData.yml", Config::YAML);

		// Initialize debug logger (file-only, daily log files)
		$this->debugLogger = new DebugLogger($this);

		// Check for valid API credentials
		if (Constant::PARTNER_ID === "" || Constant::PARTNER_KEY === "") {
			$this->getLogger()->error("Vui lòng cấu hình partner_id và partner_key trong src/Constant.php!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		// Initialize managers
		$this->formManager = new FormManager($this);
		$this->paymentManager = new PaymentManager($this);

		// Start the payment checking task
		$this->paymentManager->startPaymentCheckTask();

		$this->debugLogger->log("Plugin enabled successfully", "general");
	}

	protected function onDisable() : void {
		// Save any pending data
		if (isset($this->donateData)) {
			$this->donateData->save();
		}

		if (isset($this->debugLogger)) {
			$this->debugLogger->log("Plugin disabled", "general");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		$commandName = $command->getName();

		// Log command usage to file
		$senderName = $sender instanceof Player ? $sender->getName() : "Console";
		$argsString = !empty($args) ? " " . implode(" ", $args) : "";
		$this->debugLogger->log("Command executed: $senderName used /$commandName$argsString", "command");

		switch ($commandName) {
			case "donate":
				if ($sender instanceof Player) {
					// Người chơi thực thi lệnh, mở form donate
					$this->debugLogger->log("Opening donate form for player: " . $sender->getName(), "command");
					$this->formManager->sendDonateForm($sender);
					return true;
				} else {
					// Nếu console thực thi lệnh với đầy đủ tham số
					// Định dạng: /donate <tên người chơi> <telco> <code> <serial> <amount>
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
							$sender->sendMessage(Constant::PREFIX . "§8[§c×§8] §7» §cLoại thẻ không hợp lệ§7. Hỗ trợ§8: §f" . implode("§8, §f", $validTelcos));
							return true;
						}

						// Kiểm tra mệnh giá
						$validAmounts = [10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000, 1000000];
						if (!in_array($amount, $validAmounts, true)) {
							$sender->sendMessage(Constant::PREFIX . "§8[§c×§8] §7» §cMệnh giá không hợp lệ§7. Hỗ trợ§8: §f" . implode("§8, §f", $validAmounts));
							return true;
						}

						// Kiểm tra độ dài mã thẻ và serial
						if (strlen($code) < 10) {
							$sender->sendMessage(Constant::PREFIX . "§8[§c×§8] §7» §cMã thẻ không hợp lệ§7, quá ngắn.");
							return true;
						}

						if (strlen($serial) < 10) {
							$sender->sendMessage(Constant::PREFIX . "§8[§c×§8] §7» §cSerial không hợp lệ§7, quá ngắn.");
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
								$sender->sendMessage(Constant::PREFIX . "§8[§a+§8] §7» §fĐã gửi yêu cầu nạp thẻ cho người chơi §a" . $playerName);
								$sender->sendMessage(Constant::PREFIX . "§8[§e!§8] §7» §fMã giao dịch§8: §e" . substr($requestId, 0, 10));
								$targetPlayer->sendMessage(Constant::PREFIX . "§8[§a+§8] §7» §fAdmin đã nạp thẻ giúp bạn§7, hệ thống đang xử lý.");
							} else {
								$sender->sendMessage(Constant::PREFIX . "§8[§c×§8] §7» §cCó lỗi xảy ra khi nạp thẻ§8: §f" . $response->getMessage());
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

							$sender->sendMessage(Constant::PREFIX . "§8[§a+§8] §7» §fĐã gửi yêu cầu nạp thẻ cho người chơi §a" . $playerName . " §7(đang offline).");
							$sender->sendMessage(Constant::PREFIX . "§8[§e!§8] §7» §fMã giao dịch§8: §e" . substr($requestId, 0, 10));
						}

						return true;
					} else {
						// Hiển thị hướng dẫn sử dụng
						$sender->sendMessage(Constant::PREFIX . "§8[§e!§8] §7» §fHướng dẫn sử dụng lệnh §e/donate§f:");
						$sender->sendMessage("  §8▪ §7/donate §8[§etên§8] [§etelco§8] [§emã thẻ§8] [§eserial§8] [§emệnh giá§8]");
						$sender->sendMessage("  §8▪ §7Ví dụ: §f/donate NhanAZ VIETTEL 123456789012 123456789 50000");
						$sender->sendMessage("  §8▪ §7Loại thẻ: §aVIETTEL§8, §aMOBIFONE§8, §aVINAPHONE");
						$sender->sendMessage("  §8▪ §7Mệnh giá: §a10k§8, §a20k§8, §a50k§8, §a100k§8, §a200k§8, §a500k");

						// Hiển thị danh sách người chơi trực tuyến để tham khảo
						$onlinePlayers = $this->getServer()->getOnlinePlayers();
						if (count($onlinePlayers) > 0) {
							$sender->sendMessage(Constant::PREFIX . "§8[§e!§8] §7» §fNgười chơi trực tuyến:");

							foreach ($onlinePlayers as $player) {
								$sender->sendMessage("  §8▪ §b" . $player->getName());
							}
						}
					}
					return true;
				}

			case "topdonate":
				$page = 1;
				if (isset($args[0]) && is_numeric($args[0])) {
					$page = max(1, (int) $args[0]);
				}

				$this->debugLogger->log("TopDonate command: $senderName requested page $page", "command");

				// Nếu là player thì hiển thị form, nếu là console thì hiển thị text
				if ($sender instanceof Player) {
					$this->debugLogger->log("Sending TopDonate form to player: " . $sender->getName(), "command");
					$this->formManager->sendTopDonateForm($sender, $page);
				} else {
					$this->debugLogger->log("Showing TopDonate text to console", "command");
					$this->showTopDonators($sender, $page);
				}
				return true;

			default:
				return false;
		}
	}

	public function successfulDonation(string $playerName, int $amount) : void {
		$formattedAmount = utils\MessageTranslator::formatAmount($amount);

		// Broadcast donation message
		$this->getServer()->broadcastMessage(utils\MessageTranslator::formatSuccessMessage("Người chơi $playerName đã nạp {$formattedAmount} để ủng hộ máy chủ!"));

		// Update player statistics safely
		$currentAmount = $this->donateData->getNested($playerName, 0);
		$safeAmount = is_numeric($currentAmount) ? (int) $currentAmount : 0;
		$this->donateData->setNested($playerName, $safeAmount + $amount);
		$this->donateData->save();

		// Calculate bonus amount
		$bonusAmount = (int) ($amount * Constant::BONUS);

		// Add any additional reward code here
		// Example: EconomyAPI::getInstance()->addMoney($playerName, $bonusAmount);

		// Notify player if online
		$player = $this->getServer()->getPlayerExact($playerName);
		if ($player !== null) {
			$player->sendMessage(utils\MessageTranslator::formatSuccessMessage("Chân thành cảm ơn bạn đã ủng hộ máy chủ {$formattedAmount}!"));
			$player->sendMessage(utils\MessageTranslator::formatSuccessMessage("Bạn đã nhận được " . utils\MessageTranslator::formatAmount($bonusAmount) . " xu"));
		}

		$this->debugLogger->log("Successful donation - Player: $playerName, Amount: $amount, Bonus: $bonusAmount", "payment");
	}

	public function getDonateData() : Config {
		return $this->donateData;
	}

	public function getFormManager() : FormManager {
		return $this->formManager;
	}

	public function getPaymentManager() : PaymentManager {
		return $this->paymentManager;
	}

}
