<?php

declare(strict_types=1);

namespace Donate\payment\service;

use Donate\api\ChargeStatusResponse;
use Donate\Donate;
use Donate\payment\CardPayment;
use Donate\payment\PaymentStatus;
use Donate\payment\PendingPaymentStore;
use Donate\utils\MessageTranslator;

final class PaymentOutcomeHandler {
	public function __construct(
		private Donate $plugin,
		private PendingPaymentStore $store
	) {
	}

	public function finalize(
		string $requestId,
		CardPayment $payment,
		ChargeStatusResponse $response,
		string $successEvent,
		string $failureEvent,
		bool $logSummary
	) : CardPayment {
		$this->store->remove($requestId);

		if ($response->isSuccessful()) {
			$amount = $response->getAmount() ?? $payment->getAmount();

			$this->plugin->logger->info("[Donate/Payment] Payment successful - Player: {$payment->getPlayerName()}, RequestID: {$requestId}, Amount: {$amount}");
			$this->plugin->debugLogger->logPayment(
				$successEvent,
				$payment->getPlayerName(),
				$requestId,
				[
					"amount" => $amount,
					"declared" => $response->getDeclared(),
					"received" => $response->getReceived(),
					"status" => $response->getStatus()
				]
			);

			$this->plugin->successfulDonation($payment->getPlayerName(), $amount);

			if ($logSummary) {
				$this->plugin->getLogger()->info(
					"Payment successful: [Player: {$payment->getPlayerName()}, RequestID: {$requestId}, Amount: {$amount}]"
				);
			}

			$payment->setStatus(PaymentStatus::SUCCESSFUL);
			$payment->setProcessedAmount($amount);

			return $payment;
		}

		$this->plugin->logger->info("[Donate/Payment] Payment failed - Player: {$payment->getPlayerName()}, RequestID: {$requestId}, Reason: {$response->getMessage()}");
		$this->plugin->debugLogger->logPayment(
			$failureEvent,
			$payment->getPlayerName(),
			$requestId,
			[
				"status" => $response->getStatus(),
				"message" => $response->getMessage()
			]
		);

		if ($logSummary) {
			$this->plugin->getLogger()->info(
				"Payment failed: [Player: {$payment->getPlayerName()}, RequestID: {$requestId}, Reason: {$response->getMessage()}]"
			);
		}

		$payment->setStatus(PaymentStatus::FAILED);
		$payment->setFailReason($response->getMessage());

		$player = $this->plugin->getServer()->getPlayerExact($payment->getPlayerName());
		if ($player !== null) {
			$player->sendMessage(MessageTranslator::formatErrorMessage($response->getMessage()));
		}

		return $payment;
	}
}
