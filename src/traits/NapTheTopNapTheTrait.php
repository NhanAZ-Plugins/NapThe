<?php

declare(strict_types=1);

namespace NapThe\traits;

use NapThe\Constant;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function arsort;
use function ceil;
use function count;
use function is_numeric;
use function min;
use function number_format;

trait NapTheTopNapTheTrait {
	private function showTopDonators(CommandSender $sender, int $page) : void {
		$napTheData = $this->napTheData->getAll();
		if (empty($napTheData)) {
			$sender->sendMessage("§8[§b!§8] §7» §fChưa có dữ liệu nạp thẻ nào.");
			return;
		}

		arsort($napTheData);

		$totalPlayers = count($napTheData);
		$itemsPerPage = 10;
		$maxPage = ceil($totalPlayers / $itemsPerPage);
		$page = min($maxPage, $page);

		$sender->sendMessage(Constant::PREFIX . "§6⁕ §fBảng xếp hạng nạp thẻ trang §a{$page}§8/§a{$maxPage} §7(/topdonate <trang>) §6⁕");

		$startIndex = ($page - 1) * $itemsPerPage;
		$i = 0;
		$senderRank = 0;
		$serverTotalNapThe = 0;

		foreach ($napTheData as $playerName => $amount) {
			$amountValue = is_numeric($amount) ? (int) $amount : 0;
			$serverTotalNapThe += $amountValue;

			if ($sender instanceof Player && $playerName === $sender->getName()) {
				$senderRank = $i + 1;
			}

			if ($i >= $startIndex && $i < $startIndex + $itemsPerPage) {
				$rank = $i + 1;
				$formattedAmount = number_format($amountValue, 0, ".", ".");
				$sender->sendMessage("  §8▪ §7Hạng §f" . $rank . " §7» §b" . $playerName . " §7- Đã nạp: §a" . $formattedAmount . "₫");
			}

			$i++;
		}

		if ($sender instanceof Player && $senderRank > 0) {
			$sender->sendMessage("§8» §6Top Nạp Thẻ §7(Trang §b" . $page . " §7/ §b" . $maxPage . "§7) §8«");
		}

		$formattedTotal = number_format($serverTotalNapThe, 0, ".", ".");
		$sender->sendMessage("§8[§b!§8] §7» §fTổng nạp toàn server: §a{$formattedTotal}₫");
	}
}
