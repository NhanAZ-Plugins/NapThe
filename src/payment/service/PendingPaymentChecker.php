<?php

declare(strict_types=1);

namespace Donate\payment\service;

use Donate\Donate;
use Donate\payment\CardPayment;
use Donate\payment\PendingPaymentStore;
use pocketmine\utils\InternetException;
use function count;
use function time;

final class PendingPaymentChecker {
	public function __construct(
		private Donate $plugin,
		private PendingPaymentStore $store,
		private PaymentOutcomeHandler $outcomeHandler,
		private ImmediateCheckScheduler $immediateCheckScheduler
	) {
	}

	/**
	 * @return array<string, CardPayment>
	 */
	public function checkPendingPayments() : array {
		$processedPayments = [];
		$pendingPayments = $this->store->all();
		$pendingCount = count($pendingPayments);

		if ($pendingCount > 0) {
			$this->plugin->debugLogger->log(
				"Checking " . $pendingCount . " pending payments",
				"payment"
			);
		}

		foreach ($pendingPayments as $requestId => $payment) {
			if (time() - $payment->getCreatedAt() < 30) {
				continue;
			}

			try {
				$this->plugin->debugLogger->logPayment(
					"CHECKING",
					$payment->getPlayerName(),
					$requestId,
					["age" => (time() - $payment->getCreatedAt()) . "s"]
				);

				$response = \Donate\api\TrumTheAPI::checkCardStatus($requestId, $payment->toCardInfo());
				if ($response === null) {
					$this->plugin->debugLogger->logPayment(
						"CHECK_ERROR",
						$payment->getPlayerName(),
						$requestId,
						["reason" => "API Connection Failed"]
					);
					continue;
				}

				if ($response->isPending()) {
					$this->plugin->debugLogger->logPayment(
						"STILL_PENDING",
						$payment->getPlayerName(),
						$requestId,
						["message" => $response->getMessage()]
					);

					$pendingTime = time() - $payment->getCreatedAt();
					if ($pendingTime > 60) {
						$this->plugin->debugLogger->log(
							"Payment still PENDING after {$pendingTime}s, scheduling immediate checks for RequestID: $requestId",
							"payment"
						);
						$this->immediateCheckScheduler->scheduleImmediateCheck($requestId);
					}

					continue;
				}

				$processedPayments[$requestId] = $this->outcomeHandler->finalize(
					$requestId,
					$payment,
					$response,
					"SUCCESSFUL",
					"FAILED",
					true
				);
			} catch (InternetException $e) {
				$this->plugin->debugLogger->logPayment(
					"CHECK_EXCEPTION",
					$payment->getPlayerName(),
					$requestId,
					["error" => $e->getMessage()]
				);
			}
		}

		return $processedPayments;
	}
}
