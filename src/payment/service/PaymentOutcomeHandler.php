<?php

declare(strict_types=1);

namespace NapThe\payment\service;

use NapThe\api\ChargeStatusResponse;
use NapThe\NapThe;
use NapThe\payment\CardPayment;
use NapThe\payment\PaymentStatus;
use NapThe\payment\PendingPaymentStore;
use NapThe\utils\MessageTranslator;

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
		$this->store->remove($requestId);

		if ($response->isSuccessful()) {
			$amount = $response->getAmount() ?? $payment->getAmount();

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

			$payment->setStatus(PaymentStatus::SUCCESSFUL);
			$payment->setProcessedAmount($amount);

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
		$payment->setFailReason($response->getMessage());

		$player = $this->plugin->getServer()->getPlayerExact($payment->getPlayerName());
		if ($player !== null) {
			$player->sendMessage(MessageTranslator::formatErrorMessage($response->getMessage()));
		}

		return $payment;
	}
}
