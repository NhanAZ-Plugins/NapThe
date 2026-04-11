<?php

declare(strict_types=1);

namespace NapThe\payment\service;

use NapThe\Constant;
use NapThe\NapThe;
use NapThe\payment\PendingPaymentStore;
use pocketmine\scheduler\ClosureTask;
use function time;

final class ImmediateCheckScheduler {
	public function __construct(
		private NapThe $plugin,
		private PendingPaymentStore $store,
		private PaymentOutcomeHandler $outcomeHandler
	) {
	}

	public function scheduleImmediateCheck(
		string $requestId,
		int $retryDelay = Constant::IMMEDIATE_CHECK_INTERVAL,
		int $maxRetries = Constant::MAX_IMMEDIATE_CHECKS
	) : void {
		$payment = $this->store->get($requestId);
		if ($payment === null) {
			$this->plugin->debugLogger->log("Cannot schedule check - Payment not found: $requestId", "payment");
			return;
		}

		$this->plugin->debugLogger->log("Scheduling immediate check for payment - RequestID: $requestId", "payment");
		$this->plugin->getScheduler()->scheduleDelayedTask(
			new ClosureTask(function() use ($requestId, $retryDelay, $maxRetries) : void {
				$payment = $this->store->get($requestId);
				if ($payment === null) {
					return;
				}

				try {
					$this->plugin->debugLogger->logPayment(
						"IMMEDIATE_CHECK",
						$payment->getPlayerName(),
						$requestId,
						["age" => (time() - $payment->getCreatedAt()) . "s"]
					);

					$response = \NapThe\api\TrumTheAPI::checkCardStatus($requestId, $payment->toCardInfo());
					if ($response === null) {
						if ($maxRetries > 0) {
							$this->scheduleImmediateCheck($requestId, $retryDelay, $maxRetries - 1);
						}
						return;
					}

					if ($response->isPending()) {
						$this->plugin->debugLogger->logPayment(
							"STILL_PENDING_IMMEDIATE",
							$payment->getPlayerName(),
							$requestId,
							[
								"message" => $response->getMessage(),
								"retries_left" => $maxRetries
							]
						);

						if ($maxRetries > 0) {
							$this->scheduleImmediateCheck($requestId, $retryDelay, $maxRetries - 1);
						}
						return;
					}

					$this->outcomeHandler->finalize(
						$requestId,
						$payment,
						$response,
						"SUCCESSFUL_IMMEDIATE",
						"FAILED_IMMEDIATE",
						false
					);
				} catch (\Throwable $e) {
					$this->plugin->debugLogger->logPayment(
						"CHECK_EXCEPTION_IMMEDIATE",
						$payment->getPlayerName(),
						$requestId,
						["error" => $e->getMessage()]
					);
				}
			}),
			$retryDelay * 20
		);
	}
}
