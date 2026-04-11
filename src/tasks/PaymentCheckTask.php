<?php

declare(strict_types=1);

namespace NapThe\tasks;

use NapThe\manager\PaymentManager;
use pocketmine\scheduler\Task;

/**
 * Task to periodically check payment statuses
 */
class PaymentCheckTask extends Task {
	private PaymentManager $paymentManager;

	/**
	 * @param PaymentManager $paymentManager The payment manager
	 */
	public function __construct(PaymentManager $paymentManager) {
		$this->paymentManager = $paymentManager;
	}

	/**
	 * Check payment statuses on each tick
	 */
	public function onRun() : void {
		// Chỉ kiểm tra khi có thẻ đang chờ
		if (!$this->paymentManager->hasPendingPayments()) {
			return;
		}

		// Xử lý tất cả các thẻ đang chờ xử lý
		$this->paymentManager->checkPendingPayments();
	}
}
