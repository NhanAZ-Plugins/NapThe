<?php

declare(strict_types=1);

namespace Donate;

use Donate\manager\FormManager;
use Donate\manager\PaymentManager;
use Donate\traits\DonateDebugSupportTrait;
use Donate\traits\DonateTopDonateTrait;
use Donate\utils\DebugLogger;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\MainLogger;
use pocketmine\utils\Terminal;
use pocketmine\utils\Timezone;
use Symfony\Component\Filesystem\Path;
use function array_rand;
use function array_slice;
use function arsort;
use function ceil;
use function count;
use function date;
use function fclose;
use function fgets;
use function file_exists;
use function file_put_contents;
use function filemtime;
use function filesize;
use function floor;
use function fopen;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_writable;
use function log;
use function max;
use function min;
use function mkdir;
use function mt_rand;
use function number_format;
use function round;
use function strlen;
use function strtoupper;
use function substr;
use function time;
use function trim;
use function uniqid;
use function version_compare;
use const PHP_VERSION;

class Donate extends PluginBase {
	use SingletonTrait;
	use DonateTopDonateTrait;
	use DonateDebugSupportTrait;

	public MainLogger $logger;
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
		if (version_compare(PHP_VERSION, '8.3.0', '<')) {
			$this->getLogger()->error("Plugin require PHP 8.3.0 or higher. Current version: " . PHP_VERSION);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		api\TrumTheAPI::setApiCooldown(Constant::API_COOLDOWN);

		$this->donateData = new Config($this->getDataFolder() . "donateData.yml", Config::YAML);

		$this->logger = new MainLogger(
			logFile: Path::join($this->getDataFolder(), "log.log"),
			useFormattingCodes: Terminal::hasFormattingCodes(),
			mainThreadName: "Server",
			timezone: new \DateTimeZone(Timezone::get())
		);

		// Initialize debug logger
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

		// Ghi log việc sử dụng lệnh
		$senderName = $sender instanceof Player ? $sender->getName() : "Console";
		$argsString = !empty($args) ? " " . implode(" ", $args) : "";
		$this->logger->info("[Donate/Command] $senderName executed /$commandName$argsString");
		$this->debugLogger->log("Command executed: $senderName used /$commandName$argsString", "command");

		switch ($commandName) {
			case "donate":
				if ($sender instanceof Player) {
					// Người chơi thực thi lệnh, mở form donate
					$this->logger->info("[Donate/Form] Opening donate form for player: " . $sender->getName());
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
							$sender->sendMessage(Constant::PREFIX . "§cLoại thẻ không hợp lệ. Các loại thẻ hỗ trợ: " . implode(", ", $validTelcos));
							return true;
						}

						// Kiểm tra mệnh giá
						$validAmounts = [10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000, 1000000];
						if (!in_array($amount, $validAmounts, true)) {
							$sender->sendMessage(Constant::PREFIX . "§cMệnh giá không hợp lệ. Các mệnh giá hỗ trợ: " . implode(", ", $validAmounts));
							return true;
						}

						// Kiểm tra độ dài mã thẻ và serial
						if (strlen($code) < 10) {
							$sender->sendMessage(Constant::PREFIX . "§cMã thẻ không hợp lệ, quá ngắn");
							return true;
						}

						if (strlen($serial) < 10) {
							$sender->sendMessage(Constant::PREFIX . "§cSerial không hợp lệ, quá ngắn");
							return true;
						}

						// Tạo request ID
						$requestId = uniqid("console_", true);

						$this->logger->info("[Donate/Console] Processing card payment - Player: $playerName, Telco: $telco, RequestID: $requestId, Amount: $amount");
						$this->debugLogger->log("Console initiated card payment for $playerName - Telco: $telco, Amount: $amount", "command");

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
								$sender->sendMessage(Constant::PREFIX . "§aĐã gửi yêu cầu nạp thẻ cho người chơi §f" . $playerName);
								$sender->sendMessage(Constant::PREFIX . "§aThẻ đang được xử lý, mã giao dịch: §f" . substr($requestId, 0, 10));
								$targetPlayer->sendMessage(Constant::PREFIX . "§aAdmin đã nạp thẻ giúp bạn, hệ thống đang xử lý");
							} else {
								$sender->sendMessage(Constant::PREFIX . "§cCó lỗi xảy ra khi nạp thẻ: §f" . $response->getMessage());
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

							$sender->sendMessage(Constant::PREFIX . "§aĐã gửi yêu cầu nạp thẻ cho người chơi §f" . $playerName . " §a(đang offline)");
							$sender->sendMessage(Constant::PREFIX . "§aThẻ đang được xử lý, mã giao dịch: §f" . substr($requestId, 0, 10));
						}

						return true;
					} else {
						// Hiển thị hướng dẫn sử dụng
						$sender->sendMessage(Constant::PREFIX . "§aSử dụng: §f/donate <tên người chơi> <telco> <mã thẻ> <serial> <mệnh giá>");
						$sender->sendMessage(Constant::PREFIX . "§aVí dụ: §f/donate NhanAZ VIETTEL 123456789012 987654321098 50000");
						$sender->sendMessage(Constant::PREFIX . "§aCác loại thẻ hỗ trợ: §fVIETTEL, MOBIFONE, VINAPHONE");
						$sender->sendMessage(Constant::PREFIX . "§aCác mệnh giá hỗ trợ: §f10000, 20000, 50000, 100000, 200000, 500000");

						// Hiển thị danh sách người chơi trực tuyến để tham khảo
						$onlinePlayers = $this->getServer()->getOnlinePlayers();
						if (count($onlinePlayers) > 0) {
							$sender->sendMessage(Constant::PREFIX . "§aNgười chơi trực tuyến:");

							foreach ($onlinePlayers as $player) {
								$sender->sendMessage("§8• §f" . $player->getName());
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

				$this->logger->info("[Donate/TopDonate] Player $senderName requested top donators page: $page");
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

			case "donatedebug":
				if (!$sender->hasPermission("donate.command.debug")) {
					$this->debugLogger->log("DonateDebug command rejected - no permission: $senderName", "command");
					$sender->sendMessage(utils\MessageTranslator::formatErrorMessage("Bạn không có quyền sử dụng lệnh này!"));
					return true;
				}

				// Handle debug command
				$subcommand = $args[0] ?? "help";
				$this->debugLogger->log("DonateDebug subcommand: $subcommand by $senderName", "command");

				switch ($subcommand) {
					case "pending":
						$this->showPendingPayments($sender);
						break;

					case "status":
						$requestId = $args[1] ?? "";
						if (empty($requestId)) {
							$sender->sendMessage(utils\MessageTranslator::formatErrorMessage("Vui lòng cung cấp ID yêu cầu thanh toán!"));
							return true;
						}
						$this->checkPaymentStatus($sender, $requestId);
						break;

					case "toggle":
						$category = $args[1] ?? "general";
						$this->toggleDebugCategory($sender, $category);
						break;

					case "enabledebug":
						$this->setDebugEnabled($sender, true);
						break;

					case "disabledebug":
						$this->setDebugEnabled($sender, false);
						break;

					case "notifyadmins":
						$this->toggleNotifyAdmins($sender);
						break;

					case "list":
						$this->listDebugCategories($sender);
						break;

					case "loginfo":
						$this->checkLogFile($sender);
						break;

					case "clearlog":
						$this->clearLogFile($sender);
						break;

					case "testlog":
						$this->testLogWriting($sender);
						break;

					case "sampledata":
						$this->addSampleData($sender);
						break;

					case "samplepending":
						$count = 5; // Số lượng giao dịch mẫu mặc định
						if (isset($args[1]) && is_numeric($args[1])) {
							$count = max(1, min(20, (int) $args[1])); // Hạn chế từ 1-20 giao dịch mẫu
						}
						$this->addSamplePendingPayments($sender, $count);
						break;

					default:
						$sender->sendMessage(utils\MessageTranslator::formatInfoMessage("Các lệnh debug:"));

						// Quản lý cài đặt debug
						$sender->sendMessage("§e§l● Quản lý chung:");
						$sender->sendMessage("§7 /donatedebug list §f- Liệt kê các danh mục debug và trạng thái");
						$sender->sendMessage("§7 /donatedebug enabledebug §f- Bật debug");
						$sender->sendMessage("§7 /donatedebug disabledebug §f- Tắt debug");
						$sender->sendMessage("§7 /donatedebug notifyadmins §f- Bật/tắt thông báo debug cho admin");
						$sender->sendMessage("§7 /donatedebug toggle <category> §f- Bật/tắt debug cho danh mục cụ thể");

						// Thanh toán
						$sender->sendMessage("§e§l● Thanh toán:");
						$sender->sendMessage("§7 /donatedebug pending §f- Kiểm tra các giao dịch đang xử lý");
						$sender->sendMessage("§7 /donatedebug status <requestId> §f- Kiểm tra trạng thái giao dịch");

						// Quản lý log
						$sender->sendMessage("§e§l● Quản lý Log:");
						$sender->sendMessage("§7 /donatedebug loginfo §f- Kiểm tra thông tin file log");
						$sender->sendMessage("§7 /donatedebug clearlog §f- Xóa nội dung file log");
						$sender->sendMessage("§7 /donatedebug testlog §f- Kiểm tra khả năng ghi log");

						// Dữ liệu mẫu
						$sender->sendMessage("§e§l● Dữ liệu mẫu:");
						$sender->sendMessage("§7 /donatedebug sampledata §f- Thêm dữ liệu mẫu để test");
						$sender->sendMessage("§7 /donatedebug samplepending [số lượng] §f- Thêm giao dịch đang chờ xử lý mẫu");
						break;
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
