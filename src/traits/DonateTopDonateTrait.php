<?php

declare(strict_types=1);

namespace Donate\traits;

use Donate\Constant;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function arsort;
use function ceil;
use function count;
use function is_numeric;
use function min;
use function number_format;

trait DonateTopDonateTrait {
	private function showTopDonators(CommandSender $sender, int $page) : void {
		$donateData = $this->donateData->getAll();
		if (empty($donateData)) {
			$sender->sendMessage(Constant::PREFIX . "Hiện chưa có một ai nạp thẻ ủng hộ máy chủ...");
			return;
		}

		arsort($donateData);

		$totalPlayers = count($donateData);
		$itemsPerPage = 10;
		$maxPage = ceil($totalPlayers / $itemsPerPage);
		$page = min($maxPage, $page);

		$sender->sendMessage(Constant::PREFIX . "§6⁕ §fBảng xếp hạng nạp thẻ trang §a{$page}§8/§a{$maxPage} §7(/topdonate <trang>) §6⁕");

		$startIndex = ($page - 1) * $itemsPerPage;
		$i = 0;
		$senderRank = 0;
		$serverTotalDonated = 0;

		foreach ($donateData as $playerName => $amount) {
			$amountValue = is_numeric($amount) ? (int) $amount : 0;
			$serverTotalDonated += $amountValue;

			if ($sender instanceof Player && $playerName === $sender->getName()) {
				$senderRank = $i + 1;
			}

			if ($i >= $startIndex && $i < $startIndex + $itemsPerPage) {
				$rank = $i + 1;
				$formattedAmount = number_format($amountValue, 0, ".", ".");
				$sender->sendMessage("§f$rank. §e{$playerName}§8: §a{$formattedAmount}₫");
			}

			$i++;
		}

		if ($sender instanceof Player && $senderRank > 0) {
			$sender->sendMessage(Constant::PREFIX . "Xếp hạng của bạn là $senderRank");
		}

		$formattedTotal = number_format($serverTotalDonated, 0, ".", ".");
		$sender->sendMessage(Constant::PREFIX . "§fTổng tiền nạp từ toàn máy chủ§8: §a{$formattedTotal}₫");
	}
}
