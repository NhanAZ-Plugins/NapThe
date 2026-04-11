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
			$sender->sendMessage(Constant::PREFIX . "Hiá»‡n chÆ°a cÃ³ má»™t ai náº¡p tháº» á»§ng há»™ mÃ¡y chá»§...");
			return;
		}

		arsort($donateData);

		$totalPlayers = count($donateData);
		$itemsPerPage = 10;
		$maxPage = ceil($totalPlayers / $itemsPerPage);
		$page = min($maxPage, $page);

		$sender->sendMessage(Constant::PREFIX . "--- Báº£ng xáº¿p háº¡ng náº¡p tháº» trang $page/$maxPage (/topdonate <trang>) ---");

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
				$sender->sendMessage("$rank. $playerName: {$formattedAmount}â‚«");
			}

			$i++;
		}

		if ($sender instanceof Player && $senderRank > 0) {
			$sender->sendMessage(Constant::PREFIX . "Xáº¿p háº¡ng cá»§a báº¡n lÃ  $senderRank");
		}

		$formattedTotal = number_format($serverTotalDonated, 0, ".", ".");
		$sender->sendMessage(Constant::PREFIX . "Tá»•ng sá»‘ tiá»n náº¡p tháº» tá»« ngÆ°á»i chÆ¡i cá»§a mÃ¡y chá»§ lÃ : {$formattedTotal}â‚«");
	}
}
