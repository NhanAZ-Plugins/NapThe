<?php

declare(strict_types=1);

namespace NapThe\payment\service;

use NapThe\api\ChargeStatusResponse;
use NapThe\NapThe;
use NapThe\payment\CardPayment;
use NapThe\payment\PaymentStatus;
use NapThe\payment\PendingPaymentStore;

final class PaymentOutcomeHandler {
	public function __construct(
		private NapThe $plugin,
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
		if ($response->isSuccessful()) {
			$amount = $response->getAmount() ?? $payment->getAmount();
			$recorded = $this->plugin->successfulDonationOnce($requestId, $payment->getPlayerName(), $amount);

			$this->plugin->debugLogger->logPayment(
				$recorded ? $successEvent : 'SUCCESS_ALREADY_RECORDED',
				$payment->getPlayerName(),
				$requestId,
				[
					"amount" => $amount,
					"declared" => $response->getDeclared(),
					"received" => $response->getReceived(),
					"status" => $response->getStatus()
				]
			);

			$payment->setStatus(PaymentStatus::SUCCESSFUL);
			$payment->setProcessedAmount($amount);
			$this->store->remove($requestId);

			return $payment;
		}

		$this->plugin->debugLogger->logPayment(
			$failureEvent,
			$payment->getPlayerName(),
			$requestId,
			[
				"status" => $response->getStatus(),
				"message" => $response->getMessage()
			]
		);

		$payment->setStatus(PaymentStatus::FAILED);
		$payment->setFailReason("Provider status " . $response->getStatus());
		$this->store->remove($requestId);

		$player = $this->plugin->getServer()->getPlayerExact($payment->getPlayerName());
		if ($player !== null) {
			$player->sendMessage($response->getFriendlyMessage());
		}

		return $payment;
	}
}
