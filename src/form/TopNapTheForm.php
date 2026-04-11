<?php

declare(strict_types=1);

namespace NapThe\form;

use NapThe\Constant;
use NapThe\NapThe;
use NapThe\utils\DataTypeUtils;
use NapThe\utils\FormAPI\CustomForm;
use NapThe\utils\FormAPI\SimpleForm;
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

final class TopNapTheForm {
	public function __construct(private NapThe $plugin) {
	}

	public function send(Player $player, int $page = 1) : void {
		$this->plugin->debugLogger->log("Sending top napthe form to player: " . $player->getName() . ", page: " . $page, "form");
		$player->sendForm($this->create($page));
	}

	private function create(int $page = 1) : SimpleForm {
		$form = new SimpleForm(function(Player $player, mixed $selection) use ($page) : void {
			if ($selection === null) {
				$this->plugin->debugLogger->log("TopNapThe form: Player " . $player->getName() . " closed the form", "form");
				return;
			}

			$selectionLabel = DataTypeUtils::toString($selection);
			$this->plugin->debugLogger->log("TopNapThe form: Player " . $player->getName() . " clicked button label: " . $selectionLabel, "form");

			$napTheData = $this->plugin->getNapTheData()->getAll();
			if (empty($napTheData)) {
				$this->plugin->debugLogger->log("TopNapThe form: No donation data found when handling response", "form");
				return;
			}

			$totalPlayers = count($napTheData);
			$itemsPerPage = 10;
			$maxPage = (int) ceil($totalPlayers / $itemsPerPage);
			$currentPage = max(1, min($page, $maxPage));

			switch ($selectionLabel) {
				case "prev":
					if ($currentPage > 1) {
						$this->plugin->debugLogger->log("TopNapThe form: Going to previous page: " . ($currentPage - 1), "form");
						$this->send($player, $currentPage - 1);
					} else {
						$this->plugin->debugLogger->log("TopNapThe form: Attempted to go to previous page while on first page", "form");
						$player->sendTip("Đây là trang đầu tiên rồi.");
						$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void {
							if ($player->isOnline()) {
								$this->plugin->debugLogger->log("TopNapThe form: Reopening first page after delay", "form");
								$this->send($player, 1);
							}
						}), 30);
					}
					return;

				case "next":
					if ($currentPage < $maxPage) {
						$this->plugin->debugLogger->log("TopNapThe form: Going to next page: " . ($currentPage + 1), "form");
						$this->send($player, $currentPage + 1);
					} else {
						$this->plugin->debugLogger->log("TopNapThe form: Attempted to go to next page while on last page", "form");
						$player->sendTip("Đây là trang cuối rồi.");
						$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $maxPage) : void {
							if ($player->isOnline()) {
								$this->plugin->debugLogger->log("TopNapThe form: Reopening last page after delay", "form");
								$this->send($player, $maxPage);
							}
						}), 30);
					}
					return;

				case "goto":
					$this->plugin->debugLogger->log("TopNapThe form: Player " . $player->getName() . " clicked Go To Page button", "form");
					$this->openGoToPageForm($player, $currentPage, $maxPage);
					return;

				default:
					$this->plugin->debugLogger->log("TopNapThe form: Player " . $player->getName() . " clicked Close button", "form");
					return;
			}
		});

		$napTheData = $this->plugin->getNapTheData()->getAll();
		$this->plugin->debugLogger->log("TopNapThe form: Loaded " . count($napTheData) . " donation records", "form");

		if (empty($napTheData)) {
			$this->plugin->debugLogger->log("TopNapThe form: No donation data found, showing empty message", "form");
			return $form
				->setTitle("Xếp Hạng Nạp Thẻ")
				->setContent("Hiện chưa có một ai nạp thẻ ủng hộ máy chủ...")
				->addButton("Đóng", -1, "", "close");
		}

		arsort($napTheData);

		$totalPlayers = count($napTheData);
		$itemsPerPage = 10;
		$maxPage = (int) ceil($totalPlayers / $itemsPerPage);
		$page = max(1, min($page, $maxPage));

		$this->plugin->debugLogger->log("TopNapThe form: Page info - Current: " . $page . ", Max: " . $maxPage . ", Total Players: " . $totalPlayers, "form");

		$startIndex = ($page - 1) * $itemsPerPage;
		$i = 0;
		$topList = [];
		$serverTotalNapThe = 0;

		foreach ($napTheData as $playerName => $amount) {
			$amountValue = is_numeric($amount) ? (int) $amount : 0;
			$serverTotalNapThe += $amountValue;

			if ($i >= $startIndex && $i < $startIndex + $itemsPerPage) {
				$rank = $i + 1;
				$formattedAmount = number_format($amountValue, 0, ",", ".");
				$topList[] = "Hạng " . $rank . " - " . $playerName . "\n  Đã nạp: " . $formattedAmount . "₫\n";
			}

			$i++;
		}

		$formattedTotal = number_format($serverTotalNapThe, 0, ",", ".");
		$content = "Bảng xếp hạng nạp thẻ trang " . $page . "/" . $maxPage . "\n\n";
		$content .= implode("\n", $topList);
		$content .= "\n\nTổng nạp từ người chơi: {$formattedTotal}₫";

		$form->setTitle("Top Nạp Thẻ");
		$form->setContent("Danh sách những người chơi đóng góp nhiều nhất\nTrang " . $page . " / " . $maxPage);
		$form->addButton("Trang Trước", -1, "", "prev")
			->addButton("Đóng", -1, "", "close")
			->addButton("Trang Sau", -1, "", "next")
			->addButton("Nhập Số Trang", -1, "", "goto");

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
				$player->sendMessage("Vui lòng nhập một số hợp lệ.");
				$this->send($player, $currentPage);
				return;
			}

			$pageNumber = (int) $pageInput;
			if ($pageNumber < 1 || $pageNumber > $maxPage) {
				$player->sendMessage("Số trang phải từ 1 đến {$maxPage}.");
				$this->send($player, $currentPage);
				return;
			}

			$this->plugin->debugLogger->log("GoToPage form: Going to page " . $pageNumber, "form");
			$this->send($player, $pageNumber);
		});

		$form->setTitle("Chuyển Trang");
		$form->addInput("Nhập số trang muốn đến (1 - " . $maxPage . ")", "Ví dụ: 2", "", "page");

		$player->sendForm($form);
	}
}
