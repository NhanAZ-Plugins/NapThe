<?php

declare(strict_types=1);

namespace Donate\traits;

use Donate\Constant;
use Donate\api\TrumTheAPI;
use Donate\payment\CardPayment;
use Donate\utils\MessageTranslator;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function array_rand;
use function array_slice;
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
use function is_array;
use function is_numeric;
use function is_writable;
use function log;
use function max;
use function min;
use function mt_rand;
use function number_format;
use function round;
use function substr;
use function time;
use function trim;
use function uniqid;

trait DonateDebugSupportTrait {
	private function showPendingPayments(CommandSender $sender) : void {
		$pendingPayments = $this->paymentManager->getPendingPayments();

		if (empty($pendingPayments)) {
			$sender->sendMessage(MessageTranslator::formatInfoMessage("KhÃ´ng cÃ³ giao dá»‹ch nÃ o Ä‘ang chá» xá»­ lÃ½."));
			return;
		}

		$sender->sendMessage(MessageTranslator::formatInfoMessage("CÃ¡c giao dá»‹ch Ä‘ang xá»­ lÃ½: Â§f" . count($pendingPayments)));

		foreach ($pendingPayments as $requestId => $payment) {
			$elapsedTime = time() - $payment->getCreatedAt();
			$elapsedFormatted = floor($elapsedTime / 60) . "m " . ($elapsedTime % 60) . "s";

			$sender->sendMessage("Â§7- Â§f" . $payment->getPlayerName() .
				"Â§7: Â§f" . MessageTranslator::formatAmount($payment->getAmount()) .
				"Â§7, ID: Â§f" . substr($requestId, 0, 8) .
				"Â§7, Thá»i gian: Â§f" . $elapsedFormatted);
		}
	}

	private function checkPaymentStatus(CommandSender $sender, string $requestId) : void {
		$payment = $this->paymentManager->getPayment($requestId);

		if ($payment !== null) {
			$elapsedTime = time() - $payment->getCreatedAt();
			$elapsedFormatted = floor($elapsedTime / 60) . "m " . ($elapsedTime % 60) . "s";

			$sender->sendMessage(MessageTranslator::formatInfoMessage("ThÃ´ng tin thanh toÃ¡n:"));
			$sender->sendMessage("Â§7- NgÆ°á»i chÆ¡i: Â§f" . $payment->getPlayerName());
			$sender->sendMessage("Â§7- Má»‡nh giÃ¡: Â§f" . MessageTranslator::formatAmount($payment->getAmount()));
			$sender->sendMessage("Â§7- Loáº¡i tháº»: Â§f" . $payment->getTelco());
			$sender->sendMessage("Â§7- Tráº¡ng thÃ¡i: Â§f" . $payment->getStatus());
			$sender->sendMessage("Â§7- Thá»i gian chá»: Â§f" . $elapsedFormatted);
			$sender->sendMessage("Â§7- ID: Â§f" . $requestId);
			$sender->sendMessage(MessageTranslator::formatInfoMessage("Tiáº¿n hÃ nh kiá»ƒm tra tráº¡ng thÃ¡i..."));

			try {
				$response = TrumTheAPI::checkCardStatus($requestId, $payment->toCardInfo());

				if ($response === null) {
					$sender->sendMessage(MessageTranslator::formatErrorMessage("KhÃ´ng thá»ƒ káº¿t ná»‘i Ä‘áº¿n dá»‹ch vá»¥ thanh toÃ¡n!"));
					return;
				}

				$sender->sendMessage(MessageTranslator::formatInfoMessage("Káº¿t quáº£ kiá»ƒm tra:"));
				$sender->sendMessage("Â§7- MÃ£ tráº¡ng thÃ¡i: Â§f" . $response->getStatus());
				$sender->sendMessage("Â§7- ThÃ´ng bÃ¡o: Â§f" . MessageTranslator::translateErrorMessage($response->getMessage()));

				if ($response->getAmount() !== null) {
					$sender->sendMessage("Â§7- Sá»‘ tiá»n: Â§f" . MessageTranslator::formatAmount($response->getAmount()));
				}
			} catch (\Throwable $e) {
				$sender->sendMessage(MessageTranslator::formatErrorMessage("Lá»—i khi kiá»ƒm tra: " . $e->getMessage()));
			}
		} else {
			$sender->sendMessage(MessageTranslator::formatErrorMessage("KhÃ´ng tÃ¬m tháº¥y giao dá»‹ch vá»›i ID: " . $requestId));
		}
	}

	private function toggleDebugCategory(CommandSender $sender, string $category) : void {
		$newValue = $this->debugLogger->toggleCategory($category);
		$status = $newValue ? "Â§aBáº­t" : "Â§cTáº¯t";
		$sender->sendMessage(MessageTranslator::formatInfoMessage("ÄÃ£ " . $status . " Â§edebug cho danh má»¥c Â§f" . $category));
	}

	private function setDebugEnabled(CommandSender $sender, bool $enabled) : void {
		$this->debugLogger->setEnabled($enabled);
		$status = $enabled ? "Â§aBáº­t" : "Â§cTáº¯t";
		$sender->sendMessage(MessageTranslator::formatInfoMessage("ÄÃ£ " . $status . " Â§edebug"));
	}

	private function toggleNotifyAdmins(CommandSender $sender) : void {
		$newValue = $this->debugLogger->toggleNotifyAdmins();
		$status = $newValue ? "Â§aBáº­t" : "Â§cTáº¯t";
		$sender->sendMessage(MessageTranslator::formatInfoMessage("ÄÃ£ " . $status . " Â§ethÃ´ng bÃ¡o debug cho admin"));
	}

	private function listDebugCategories(CommandSender $sender) : void {
		$enabled = $this->debugLogger->isDebugEnabled();
		$notifyAdmins = $this->debugLogger->shouldNotifyAdmins();
		$categories = $this->debugLogger->getEnabledCategories();

		$enabledStatus = $enabled ? "Â§aBáº­t" : "Â§cTáº¯t";
		$notifyStatus = $notifyAdmins ? "Â§aBáº­t" : "Â§cTáº¯t";

		$sender->sendMessage(MessageTranslator::formatInfoMessage("Tráº¡ng thÃ¡i debug:"));
		$sender->sendMessage("Â§7- Tráº¡ng thÃ¡i: " . $enabledStatus);
		$sender->sendMessage("Â§7- ThÃ´ng bÃ¡o admin: " . $notifyStatus);
		$sender->sendMessage("Â§7- Danh má»¥c:");

		if (empty($categories) || !is_array($categories)) {
			$sender->sendMessage("  Â§7KhÃ´ng cÃ³ danh má»¥c nÃ o Ä‘Æ°á»£c cáº¥u hÃ¬nh");
			return;
		}

		foreach ($categories as $category => $status) {
			$categoryStatus = $status ? "Â§aBáº­t" : "Â§cTáº¯t";
			$sender->sendMessage("  Â§7- Â§f" . $category . ": " . $categoryStatus);
		}
	}

	private function checkLogFile(CommandSender $sender) : void {
		$logPath = $this->getDataFolder() . "log.log";

		if (!file_exists($logPath)) {
			$sender->sendMessage(MessageTranslator::formatErrorMessage("File log khÃ´ng tá»“n táº¡i!"));
			return;
		}

		$fileSize = filesize($logPath);
		$isWritable = is_writable($logPath);
		$lastModified = filemtime($logPath);

		$sender->sendMessage(MessageTranslator::formatInfoMessage("ThÃ´ng tin file log:"));
		$sender->sendMessage("Â§7- ÄÆ°á»ng dáº«n: Â§f" . $logPath);
		$sender->sendMessage("Â§7- KÃ­ch thÆ°á»›c: Â§f" . $this->formatFileSize($fileSize !== false ? $fileSize : 0));
		$sender->sendMessage("Â§7- Quyá»n ghi: Â§f" . ($isWritable ? "Â§aCÃ³" : "Â§cKhÃ´ng"));
		$sender->sendMessage("Â§7- Láº§n sá»­a cuá»‘i: Â§f" . date("Y-m-d H:i:s", $lastModified !== false ? $lastModified : time()));

		if ($fileSize > 0) {
			$firstLines = $this->readFirstLines($logPath, 5);
			if (!empty($firstLines)) {
				$sender->sendMessage("Â§7- 5 dÃ²ng Ä‘áº§u tiÃªn:");
				foreach ($firstLines as $line) {
					$sender->sendMessage("  Â§7" . $line);
				}
			}

			$lastLines = $this->readLastLines($logPath, 5);
			if (!empty($lastLines)) {
				$sender->sendMessage("Â§7- 5 dÃ²ng cuá»‘i cÃ¹ng:");
				foreach ($lastLines as $line) {
					$sender->sendMessage("  Â§7" . $line);
				}
			}
		} else {
			$sender->sendMessage("Â§7- File log trá»‘ng!");
		}
	}

	private function clearLogFile(CommandSender $sender) : void {
		$logPath = $this->getDataFolder() . "log.log";

		if (!file_exists($logPath)) {
			$sender->sendMessage(MessageTranslator::formatErrorMessage("File log khÃ´ng tá»“n táº¡i!"));
			return;
		}

		if (!is_writable($logPath)) {
			$sender->sendMessage(MessageTranslator::formatErrorMessage("KhÃ´ng cÃ³ quyá»n ghi file log!"));
			return;
		}

		if (file_put_contents($logPath, "") !== false) {
			$sender->sendMessage(MessageTranslator::formatSuccessMessage("ÄÃ£ xÃ³a ná»™i dung file log!"));
			$this->logger->info("Log file cleared by " . $sender->getName() . " at " . date("Y-m-d H:i:s"));
			$this->debugLogger->log("Log file cleared by " . $sender->getName(), "general");
			return;
		}

		$sender->sendMessage(MessageTranslator::formatErrorMessage("KhÃ´ng thá»ƒ xÃ³a ná»™i dung file log!"));
	}

	private function testLogWriting(CommandSender $sender) : void {
		$testMessage = "Test log tá»« " . $sender->getName() . " lÃºc " . date("Y-m-d H:i:s");

		$this->getLogger()->info("INFO log: " . $testMessage);
		$this->getLogger()->debug("DEBUG log thÃ´ng qua getLogger(): " . $testMessage);
		$this->logger->info("INFO log trá»±c tiáº¿p: " . $testMessage);
		$this->logger->debug("DEBUG log trá»±c tiáº¿p: " . $testMessage);

		$this->debugLogger->log("Test debug cho danh má»¥c general: " . $testMessage, "general");
		$this->debugLogger->log("Test debug cho danh má»¥c payment: " . $testMessage, "payment");
		$this->debugLogger->log("Test debug cho danh má»¥c api: " . $testMessage, "api");
		$this->debugLogger->logPayment(
			"TEST",
			$sender->getName(),
			"test-" . uniqid(),
			["time" => date("H:i:s"), "sender" => $sender->getName()]
		);

		$sender->sendMessage(MessageTranslator::formatSuccessMessage("ÄÃ£ thá»­ ghi log. Kiá»ƒm tra file log báº±ng lá»‡nh /donatedebug loginfo vÃ  kiá»ƒm tra console"));
	}

	/**
	 * @return string[]
	 */
	private function readFirstLines(string $filePath, int $lineCount) : array {
		$lines = [];
		$handle = fopen($filePath, "r");

		if ($handle) {
			$count = 0;
			while (($line = fgets($handle)) !== false && $count < $lineCount) {
				$lines[] = trim($line);
				$count++;
			}
			fclose($handle);
		}

		return $lines;
	}

	/**
	 * @return string[]
	 */
	private function readLastLines(string $filePath, int $lineCount) : array {
		$lines = [];
		$fileSize = filesize($filePath);

		if ($fileSize == 0) {
			return $lines;
		}

		$handle = fopen($filePath, "r");
		if ($handle) {
			$allLines = [];
			while (($line = fgets($handle)) !== false) {
				$allLines[] = trim($line);
			}
			fclose($handle);

			$count = count($allLines);
			$start = max(0, $count - $lineCount);
			$lines = array_slice($allLines, $start);
		}

		return $lines;
	}

	private function formatFileSize(int $bytes) : string {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];

		$bytes = max($bytes, 0);
		$pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);
		$bytes /= (1 << (10 * $pow));

		return round($bytes, 2) . ' ' . $units[$pow];
	}

	private function addSampleData(CommandSender $sender) : void {
		$this->debugLogger->log("Adding sample data requested by: " . ($sender instanceof Player ? $sender->getName() : "Console"), "sample");

		$sampleData = [
			"NhanAZ" => 500000000,
			"Steve" => 200000,
			"Alex" => 350000,
			"Notch" => 1000000,
			"Herobrine" => 900000,
			"Jeb" => 750000,
			"Dinnerbone" => 450000,
			"WillowWisp" => 180000,
			"Enderman" => 220000,
			"Creeper" => 150000,
			"Skeleton" => 50000,
			"Zombie" => 75000,
			"Tester1" => 25000,
			"Tester2" => 35000,
			"Tester3" => 45000,
			"VIPPlayer" => 650000,
			"Regular" => 120000,
			"NewMember" => 10000,
			"ServerSponsor" => 1500000,
			"MasterBuilder" => 300000,
		];

		$currentData = $this->donateData->getAll();
		$this->debugLogger->log("Current data has " . count($currentData) . " entries", "sample");

		if (!empty($currentData)) {
			$sender->sendMessage(MessageTranslator::formatInfoMessage("ÄÃ£ tÃ¬m tháº¥y dá»¯ liá»‡u hiá»‡n cÃ³, Ä‘ang há»£p nháº¥t..."));

			$newCount = 0;
			foreach ($sampleData as $player => $amount) {
				if (!isset($currentData[$player])) {
					$currentData[$player] = $amount;
					$newCount++;
				}
			}

			$this->debugLogger->log("Added $newCount new entries to existing data", "sample");

			$stringKeysData = [];
			foreach ($currentData as $key => $value) {
				$stringKeysData[(string) $key] = $value;
			}
			$this->donateData->setAll($stringKeysData);
		} else {
			$stringKeysData = [];
			foreach ($sampleData as $key => $value) {
				$stringKeysData[(string) $key] = $value;
			}
			$this->donateData->setAll($stringKeysData);
			$this->debugLogger->log("Added all sample data (20 entries) to empty data file", "sample");
		}

		$this->donateData->save();
		$this->debugLogger->log("Saved donate data to file", "sample");

		$requestId = "sample-" . uniqid();
		$payment = new CardPayment(
			$requestId,
			$sender instanceof Player ? $sender->getName() : "Console",
			"VIETTEL",
			"123456789012",
			"987654321098",
			100000,
			time()
		);

		$this->paymentManager->addSamplePayment($requestId, $payment);
		$this->debugLogger->log("Added sample payment with ID: $requestId", "sample");

		$sender->sendMessage(MessageTranslator::formatSuccessMessage("ÄÃ£ thÃªm dá»¯ liá»‡u máº«u thÃ nh cÃ´ng!"));
		$sender->sendMessage(MessageTranslator::formatInfoMessage("- ÄÃ£ thÃªm 20 ngÆ°á»i chÆ¡i vá»›i sá»‘ tiá»n donate máº«u"));
		$sender->sendMessage(MessageTranslator::formatInfoMessage("- ÄÃ£ thÃªm 1 giao dá»‹ch Ä‘ang chá» xá»­ lÃ½ vá»›i ID: " . substr($requestId, 0, 10) . "..."));
		$sender->sendMessage(MessageTranslator::formatInfoMessage("Báº¡n cÃ³ thá»ƒ test lá»‡nh /topdonate vÃ  /donatedebug pending ngay bÃ¢y giá»"));
	}

	private function addSamplePendingPayments(CommandSender $sender, int $count) : void {
		$this->debugLogger->log("Adding $count sample pending payments requested by: " . ($sender instanceof Player ? $sender->getName() : "Console"), "sample");

		$telcos = ["VIETTEL", "MOBIFONE", "VINAPHONE", "VIETNAMOBILE", "ZING"];
		$amounts = [10000, 20000, 50000, 100000, 200000, 500000];
		$names = [
			$sender instanceof Player ? $sender->getName() : "Console",
			"Steve",
			"Alex",
			"Notch",
			"Herobrine",
			"Jeb",
			"Dinnerbone",
			"Player1",
			"Player2",
			"Player3",
			"Player4",
			"Player5",
			"VIPMember",
			"RegularMember",
			"NewMember"
		];

		$addedPayments = [];

		for ($i = 0; $i < $count; $i++) {
			$requestId = "sample-" . uniqid() . "-" . ($i + 1);
			$telco = $telcos[array_rand($telcos)];
			$amount = $amounts[array_rand($amounts)];
			$playerName = $names[array_rand($names)];
			$code = mt_rand(100000000000, 999999999999);
			$serial = mt_rand(100000000000, 999999999999);
			$createdTime = time() - mt_rand(0, 600);

			$payment = new CardPayment(
				$requestId,
				$playerName,
				$telco,
				(string) $code,
				(string) $serial,
				$amount,
				$createdTime
			);

			$this->paymentManager->addSamplePayment($requestId, $payment);
			$addedPayments[] = [
				"id" => substr($requestId, 0, 8) . "...",
				"player" => $playerName,
				"amount" => $amount,
				"telco" => $telco,
				"time" => date("H:i:s", $createdTime)
			];
		}

		$sender->sendMessage(MessageTranslator::formatSuccessMessage("ÄÃ£ thÃªm $count giao dá»‹ch Ä‘ang chá» xá»­ lÃ½ máº«u!"));

		if ($count <= 10) {
			$sender->sendMessage(MessageTranslator::formatInfoMessage("Danh sÃ¡ch giao dá»‹ch Ä‘Ã£ thÃªm:"));
			foreach ($addedPayments as $paymentInfo) {
				$formattedAmount = number_format($paymentInfo["amount"], 0, ",", ".");
				$sender->sendMessage("Â§7- Â§f{$paymentInfo["player"]} Â§7| Â§f{$formattedAmount}Ä‘ Â§7| Â§f{$paymentInfo["telco"]} Â§7| Â§f{$paymentInfo["time"]} Â§7| Â§fID: {$paymentInfo["id"]}");
			}
		}

		$sender->sendMessage(MessageTranslator::formatInfoMessage("DÃ¹ng lá»‡nh /donatedebug pending Ä‘á»ƒ xem danh sÃ¡ch"));
	}
}
