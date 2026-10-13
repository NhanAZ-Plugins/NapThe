<?php

declare(strict_types=1);

namespace NapThe\payment;

/**
 * In-memory storage for payments waiting on remote confirmation.
 */
final class PendingPaymentStore {
	/** @var array<string, CardPayment> */
	private array $payments = [];

	public function __construct(private ?DonationLedger $ledger = null) {
		if ($ledger === null) {
			return;
		}
		foreach ($ledger->pendingPayments() as $payment) {
			$this->payments[$payment['request_id']] = new CardPayment(
				$payment['request_id'],
				$payment['player_name'],
				null,
				null,
				null,
				$payment['amount'],
				$payment['created_at']
			);
		}
	}

	public function add(string $requestId, CardPayment $payment) : void {
		if ($requestId !== $payment->getRequestId()) {
			throw new \InvalidArgumentException('Pending payment key must match its request ID');
		}
		$this->ledger?->recordPending($requestId, $payment->getPlayerName(), $payment->getAmount(), $payment->getCreatedAt());
		$this->payments[$requestId] = $payment;
	}

	public function get(string $requestId) : ?CardPayment {
		return $this->payments[$requestId] ?? null;
	}

	public function remove(string $requestId) : ?CardPayment {
		$payment = $this->payments[$requestId] ?? null;
		if ($payment === null) {
			return null;
		}
		$this->ledger?->removePending($requestId);
		unset($this->payments[$requestId]);

		return $payment;
	}

	/**
	 * @return array<string, CardPayment>
	 */
	public function all() : array {
		return $this->payments;
	}

	public function hasAny() : bool {
		return $this->payments !== [];
	}
}
