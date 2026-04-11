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
		$this->plugin->logger->info("[Donate/Form] Sending top donate form to player: " . $player->getName() . ", page: " . $page);
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
						$player->sendTip(Constant::PREFIX . "Â§eÄÃ¢y lÃ  trang Ä‘áº§u tiÃªn rá»“i.");
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
						$player->sendTip(Constant::PREFIX . "Â§eÄÃ¢y lÃ  trang cuá»‘i rá»“i.");
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
				->setTitle("Â§lÂ§fâ€¢ Â§8[Â§6Xáº¿p Háº¡ng DonateÂ§8] Â§fâ€¢")
				->setContent("Â§cHiá»‡n chÆ°a cÃ³ má»™t ai náº¡p tháº» á»§ng há»™ mÃ¡y chá»§...")
				->addButton("Â§lÂ§8Â« Â§cÄÃ³ng Â§8Â»", -1, "", "close");
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
				$topList[] = "Â§lÂ§f$rank. Â§e$playerName: Â§a{$formattedAmount}â‚«";
			}

			$i++;
		}

		$formattedTotal = number_format($serverTotalDonated, 0, ",", ".");
		$content = "Â§lÂ§6â€¢ Â§fBáº£ng xáº¿p háº¡ng donate trang Â§a" . $page . "Â§f/Â§a" . $maxPage . " Â§6â€¢\n\n";
		$content .= implode("\n", $topList);
		$content .= "\n\nÂ§lÂ§6â€¢ Â§fTá»•ng sá»‘ tiá»n náº¡p tháº» tá»« ngÆ°á»i chÆ¡i: Â§a{$formattedTotal}â‚« Â§6â€¢";

		$form->setTitle("Â§lÂ§fâ€¢ Â§8[Â§6Xáº¿p Háº¡ng DonateÂ§8] Â§fâ€¢")
			->setContent($content)
			->addButton($page > 1 ? "Â§lÂ§8Â« Â§aTrang TrÆ°á»›c" : "Â§lÂ§8Â« Â§0Trang TrÆ°á»›c", -1, "", "prev")
			->addButton("Â§lÂ§8Â« Â§cÄÃ³ng Â§8Â»", -1, "", "close")
			->addButton($page < $maxPage ? "Â§lÂ§aTrang Sau Â§8Â»" : "Â§lÂ§0Trang Sau Â§8Â»", -1, "", "next")
			->addButton("Â§lÂ§8Â« Â§eNháº­p Sá»‘ Trang Â§8Â»", -1, "", "goto");

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
				$player->sendMessage(Constant::PREFIX . "Â§cVui lÃ²ng nháº­p má»™t sá»‘ há»£p lá»‡!");
				$this->send($player, $currentPage);
				return;
			}

			$pageNumber = (int) $pageInput;
			if ($pageNumber < 1 || $pageNumber > $maxPage) {
				$player->sendMessage(Constant::PREFIX . "Â§cSá»‘ trang pháº£i tá»« 1 Ä‘áº¿n {$maxPage}!");
				$this->send($player, $currentPage);
				return;
			}

			$this->plugin->debugLogger->log("GoToPage form: Going to page " . $pageNumber, "form");
			$this->send($player, $pageNumber);
		});

		$form->setTitle("Â§lÂ§fâ€¢ Â§8[Â§6Chuyá»ƒn TrangÂ§8] Â§fâ€¢")
			->addLabel("Â§fNháº­p sá»‘ trang báº¡n muá»‘n xem (1-{$maxPage}):", "info")
			->addInput("Sá»‘ trang", "Nháº­p sá»‘ tá»« 1 Ä‘áº¿n {$maxPage}", null, "page");

		$player->sendForm($form);
	}
}
