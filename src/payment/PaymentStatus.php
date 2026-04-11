<?php

declare(strict_types=1);

namespace NapThe\payment;

/**
 * Payment status constants
 */
final class PaymentStatus {
	/** Thanh toán đang chờ xử lý */
	public const PENDING = "pending";

	/** Thanh toán thành công */
	public const SUCCESSFUL = "successful";

	/** Thanh toán thất bại */
	public const FAILED = "failed";
}
