<?php

declare(strict_types=1);

namespace NapThe\utils;

use NapThe\NapThe;
use pocketmine\utils\TextFormat;
use function date;
use function dirname;
use function file_put_contents;
use function memory_get_usage;
use function mkdir;
use function number_format;
use function rtrim;
use function str_ends_with;
use function strtoupper;
use function substr;
use const FILE_APPEND;
use const LOCK_EX;
use const PHP_EOL;

/**
 * File-only debug logger that writes daily log files.
 *
 * All debug information is written to logs/YYYY-MM-DD.log files.
 * No output is sent to the console or to players.
 * Logging is always enabled — every action is recorded for post-mortem debugging.
 */
class DebugLogger {
	private static ?self $instance = null;

	/** @var string Đường dẫn tới thư mục lưu log */
	private string $logsDir;

	public function __construct(NapThe $plugin) {
		self::$instance = $this;
		$this->logsDir = $plugin->getDataFolder() . "logs";
		@mkdir($this->logsDir, 0755, true);
	}

	public static function getInstance() : ?self {
		return self::$instance;
	}

	/**
	 * Get the path to today's log file.
	 */
	private function getLogFilePath() : string {
		return $this->logsDir . DIRECTORY_SEPARATOR . date("Y-m-d") . ".log";
	}

	/**
	 * Log a general debug message to the daily log file.
	 *
	 * @param string $message  The message to log
	 * @param string $category The category tag (e.g. "general", "payment", "api", "form", "command")
	 */
	public function log(string $message, string $category = "general") : void {
		$timestamp = date("Y-m-d H:i:s");
		$categoryTag = strtoupper($category);
		$cleanMessage = TextFormat::clean($message);
		$memoryMB = number_format(memory_get_usage(true) / 1024 / 1024, 2);

		$logLine = "[$timestamp] [$categoryTag] $cleanMessage [mem: {$memoryMB}MB]";
		$this->writeToLogFile($logLine);
	}

	/**
	 * Log a payment-related debug message with structured details.
	 *
	 * @param string               $action    The payment action (e.g. "SUBMITTED", "SUCCESS", "FAILED", "PENDING")
	 * @param string               $player    The player name
	 * @param string               $requestId The payment request ID
	 * @param array<string, mixed> $details   Details; only safe numeric/status fields are logged
	 */
	public function logPayment(
		string $action,
		string $player,
		string $requestId,
		array $details = []
	) : void {
		$detailsStr = "";
		foreach ($details as $key => $value) {
			if (!in_array($key, ["status", "amount", "declared", "received"], true) || (!is_int($value) && !is_float($value))) {
				continue;
			}
			$detailsStr .= $key . ": " . (string) $value . ", ";
		}
		$detailsStr = rtrim($detailsStr, ", ");

		$message = "Payment $action - Player: $player, RequestID: $requestId" .
			(!empty($detailsStr) ? ", Details: {$detailsStr}" : "");

		$this->log($message, "payment");
	}

	/** Record the API stage without card numbers, signatures or response bodies. */
	public function logApi(string $action) : void {
		$this->log("API $action", "api");
	}

	/**
	 * Write a line to today's log file.
	 */
	private function writeToLogFile(string $message) : void {
		if (!str_ends_with($message, PHP_EOL)) {
			$message .= PHP_EOL;
		}

		$logFile = $this->getLogFilePath();
		$result = @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);

		if ($result === false) {
			@mkdir(dirname($logFile), 0755, true);
			@file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
		}
	}
}
