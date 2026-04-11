<?php

declare(strict_types=1);

namespace Donate\form;

use Donate\Constant;
use Donate\Donate;
use Donate\utils\DataTypeUtils;
use Donate\utils\FormAPI\CustomForm;
use Donate\utils\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use function arsort;
use function ceil;
use function count;
use function implode;
use function is_numeric;
use function max;
use function min;
use function number_format;

final class TopDonateForm {
	public function __construct(private Donate $plugin) {
	}

	public function send(Player $player, int $page = 1) : void {
		$this->plugin->debugLogger->log("Sending top donate form to player: " . $player->getName() . ", page: " . $page, "form");
		$player->sendForm($this->create($page));
	}

	private function create(int $page = 1) : SimpleForm {
		$form = new SimpleForm(function(Player $player, mixed $selection) use ($page) : void {
			if ($selection === null) {
				$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " closed the form", "form");
				return;
			}

			$selectionLabel = DataTypeUtils::toString($selection);
			$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " clicked button label: " . $selectionLabel, "form");

			$donateData = $this->plugin->getDonateData()->getAll();
			if (empty($donateData)) {
				$this->plugin->debugLogger->log("TopDonate form: No donation data found when handling response", "form");
				return;
			}

			$totalPlayers = count($donateData);
			$itemsPerPage = 10;
			$maxPage = (int) ceil($totalPlayers / $itemsPerPage);
			$currentPage = max(1, min($page, $maxPage));

			switch ($selectionLabel) {
				case "prev":
					if ($currentPage > 1) {
						$this->plugin->debugLogger->log("TopDonate form: Going to previous page: " . ($currentPage - 1), "form");
						$this->send($player, $currentPage - 1);
					} else {
						$this->plugin->debugLogger->log("TopDonate form: Attempted to go to previous page while on first page", "form");
						$player->sendTip("§8[§b!§8] §7» §fĐây là trang đầu tiên rồi.");
						$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void {
							if ($player->isOnline()) {
								$this->plugin->debugLogger->log("TopDonate form: Reopening first page after delay", "form");
								$this->send($player, 1);
							}
						}), 30);
					}
					return;

				case "next":
					if ($currentPage < $maxPage) {
						$this->plugin->debugLogger->log("TopDonate form: Going to next page: " . ($currentPage + 1), "form");
						$this->send($player, $currentPage + 1);
					} else {
						$this->plugin->debugLogger->log("TopDonate form: Attempted to go to next page while on last page", "form");
						$player->sendTip("§8[§b!§8] §7» §fĐây là trang cuối rồi.");
						$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $maxPage) : void {
							if ($player->isOnline()) {
								$this->plugin->debugLogger->log("TopDonate form: Reopening last page after delay", "form");
								$this->send($player, $maxPage);
							}
						}), 30);
					}
					return;

				case "goto":
					$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " clicked Go To Page button", "form");
					$this->openGoToPageForm($player, $currentPage, $maxPage);
					return;

				default:
					$this->plugin->debugLogger->log("TopDonate form: Player " . $player->getName() . " clicked Close button", "form");
					return;
			}
		});

		$donateData = $this->plugin->getDonateData()->getAll();
		$this->plugin->debugLogger->log("TopDonate form: Loaded " . count($donateData) . " donation records", "form");

		if (empty($donateData)) {
			$this->plugin->debugLogger->log("TopDonate form: No donation data found, showing empty message", "form");
			return $form
				->setTitle("§l§f⁕ §8[§6Xếp Hạng Donate§8] §f⁕")
				->setContent("§cHiện chưa có một ai nạp thẻ ủng hộ máy chủ...")
				->addButton("§l§8« §cĐóng §8»", -1, "", "close");
		}

		arsort($donateData);

		$totalPlayers = count($donateData);
		$itemsPerPage = 10;
		$maxPage = (int) ceil($totalPlayers / $itemsPerPage);
		$page = max(1, min($page, $maxPage));

		$this->plugin->debugLogger->log("TopDonate form: Page info - Current: " . $page . ", Max: " . $maxPage . ", Total Players: " . $totalPlayers, "form");

		$startIndex = ($page - 1) * $itemsPerPage;
		$i = 0;
		$topList = [];
		$serverTotalDonated = 0;

		foreach ($donateData as $playerName => $amount) {
			$amountValue = is_numeric($amount) ? (int) $amount : 0;
			$serverTotalDonated += $amountValue;

			if ($i >= $startIndex && $i < $startIndex + $itemsPerPage) {
				$rank = $i + 1;
				$formattedAmount = number_format($amountValue, 0, ",", ".");
				$topList[] = "§8▪ §7Hạng §f" . $rank . " §7» §b" . $playerName . "\n  §7Đã nạp: §a" . $formattedAmount . "₫\n";
			}

			$i++;
		}

		$formattedTotal = number_format($serverTotalDonated, 0, ",", ".");
		$content = "§6⁕ §fBảng xếp hạng donate trang §a" . $page . "§8/§a" . $maxPage . " §6⁕\n\n";
		$content .= implode("\n", $topList);
		$content .= "\n\n§6⁕ §fTổng nạp từ người chơi§8: §a{$formattedTotal}₫ §6⁕";

		$form->setTitle("§8» §6Top Nạp Thẻ §8«");
		$form->setContent("§7Danh sách những người chơi đóng góp nhiều nhất\n§fTrang §b" . $page . " §7/ §b" . $maxPage);
		$form->addButton($page > 1 ? "§l§8« §a← Trang Trước" : "§l§8« §0← Trang Trước", -1, "", "prev")
			->addButton("§l§8« §cĐóng §8»", -1, "", "close")
			->addButton($page < $maxPage ? "§l§aTrang Sau → §8»" : "§l§0Trang Sau → §8»", -1, "", "next")
			->addButton("§l§8« §eNhập Số Trang §8»", -1, "", "goto");

		return $form;
	}

	private function openGoToPageForm(Player $player, int $currentPage, int $maxPage) : void {
		$form = new CustomForm(function(Player $player, mixed $data) use ($currentPage, $maxPage) : void {
			if ($data === null) {
				$this->plugin->debugLogger->log("GoToPage form: Player " . $player->getName() . " closed the form", "form");
				$this->send($player, $currentPage);
				return;
			}

			if (!is_array($data)) {
				$this->send($player, $currentPage);
				return;
			}

			$pageInput = DataTypeUtils::toString($data["page"] ?? "");
			$this->plugin->debugLogger->log("GoToPage form: Player " . $player->getName() . " entered page: " . $pageInput, "form");

			if (!is_numeric($pageInput)) {
				$player->sendMessage("§8[§c×§8] §7» §cVui lòng nhập một số hợp lệ.");
				$this->send($player, $currentPage);
				return;
			}

			$pageNumber = (int) $pageInput;
			if ($pageNumber < 1 || $pageNumber > $maxPage) {
				$player->sendMessage("§8[§c×§8] §7» §cSố trang phải từ 1 đến {$maxPage}.");
				$this->send($player, $currentPage);
				return;
			}

			$this->plugin->debugLogger->log("GoToPage form: Going to page " . $pageNumber, "form");
			$this->send($player, $pageNumber);
		});

		$form->setTitle("§8» §6Chuyển Trang §8«");
		$form->addInput("§7Nhập số trang muốn đến §8(1 - " . $maxPage . ")", "Ví dụ: 2", "", "page");

		$player->sendForm($form);
	}
}
