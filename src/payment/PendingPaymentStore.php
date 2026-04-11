<?php

declare(strict_types=1);

namespace NapThe\payment;

/**
 * In-memory storage for payments waiting on remote confirmation.
 */
final class PendingPaymentStore {
	/** @var array<string, CardPayment> */
	private array $payments = [];

	public function add(string $requestId, CardPayment $payment) : void {
		$this->payments[$requestId] = $payment;
	}

	public function get(string $requestId) : ?CardPayment {
		return $this->payments[$requestId] ?? null;
	}

	public function remove(string $requestId) : ?CardPayment {
		$payment = $this->payments[$requestId] ?? null;
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
