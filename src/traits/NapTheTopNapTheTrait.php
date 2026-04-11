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
			$sender->sendMessage("Chưa có dữ liệu nạp thẻ nào.");
			return;
		}

		arsort($napTheData);

		$totalPlayers = count($napTheData);
		$itemsPerPage = 10;
		$maxPage = ceil($totalPlayers / $itemsPerPage);
		$page = min($maxPage, $page);

		$sender->sendMessage(Constant::PREFIX . "Bảng xếp hạng nạp thẻ trang {$page}/{$maxPage} (/topnapthe <trang>)");

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
				$sender->sendMessage("Hạng " . $rank . " - " . $playerName . " - Đã nạp: " . $formattedAmount . "₫");
			}

			$i++;
		}

		if ($sender instanceof Player && $senderRank > 0) {
			$sender->sendMessage("Top Nạp Thẻ (Trang " . $page . " / " . $maxPage . ")");
		}

		$formattedTotal = number_format($serverTotalNapThe, 0, ".", ".");
		$sender->sendMessage("Tổng nạp toàn server: {$formattedTotal}₫");
	}
}
