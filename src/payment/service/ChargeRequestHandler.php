<?php

declare(strict_types=1);

namespace Donate\payment\service;

use Donate\api\ChargeResponse;
use Donate\api\TrumTheAPI;
use Donate\Donate;
use Donate\payment\CardPayment;
use Donate\payment\PendingPaymentStore;
use pocketmine\player\Player;
use pocketmine\utils\InternetException;
use function substr;
use function time;

final class ChargeRequestHandler {
	public function __construct(
		private Donate $plugin,
		private PendingPaymentStore $store,
		private ImmediateCheckScheduler $immediateCheckScheduler
	) {
	}

	public function processCardPayment(
		Player $player,
		string $telco,
		string $code,
		string $serial,
		int $amount,
		string $requestId
	) : ChargeResponse {
		try {
			$this->plugin->debugLogger->logPayment(
				"ATTEMPTED",
				$player->getName(),
				$requestId,
				[
					"telco" => $telco,
					"amount" => $amount,
					"serial" => substr($serial, 0, 4) . "****" . substr($serial, -4),
					"code" => substr($code, 0, 2) . "****" . substr($code, -2)
				]
			);

			$response = TrumTheAPI::chargeCard($telco, $code, $serial, $amount, $requestId);
			if ($response === null) {
				$this->plugin->debugLogger->logPayment(
					"ERROR",
					$player->getName(),
					$requestId,
					["reason" => "API Connection Failed"]
				);

				return new ChargeResponse(-1, "connection.failed");
			}

			if ($response->isSuccessful() || $response->isPending()) {
				$payment = new CardPayment(
					$requestId,
					$player->getName(),
					$telco,
					$code,
					$serial,
					$amount,
					time()
				);
				$this->store->add($requestId, $payment);

				$this->plugin->debugLogger->logPayment(
					"PENDING",
					$player->getName(),
					$requestId,
					[
						"status" => $response->getStatus(),
						"message" => $response->getMessage(),
						"telco" => $telco,
						"amount" => $amount
					]
				);

				if ($response->isPending()) {
					$this->plugin->debugLogger->log(
						"Payment is PENDING, scheduling immediate checks for RequestID: $requestId",
						"payment"
					);
					$this->immediateCheckScheduler->scheduleImmediateCheck($requestId);
				}

				return $response;
			}

			$this->plugin->debugLogger->log(
				"Payment rejected for {$player->getName()}: Raw message: {$response->getMessage()}",
				"payment"
			);
			$this->plugin->debugLogger->logPayment(
				"REJECTED",
				$player->getName(),
				$requestId,
				[
					"status" => $response->getStatus(),
					"message" => $response->getMessage()
				]
			);

			return $response;
		} catch (InternetException $e) {
			$this->plugin->debugLogger->logPayment(
				"EXCEPTION",
				$player->getName(),
				$requestId,
				["error" => $e->getMessage()]
			);

			return new ChargeResponse(-1, "connection.timeout");
		}
	}
}
