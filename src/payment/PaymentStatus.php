<?php

declare(strict_types=1);

namespace NapThe\payment;

/**
 * Payment status constants
 */
final class PaymentStatus {
	/** Payment is pending. */
	public const PENDING = "pending";

	/** Payment succeeded. */
	public const SUCCESSFUL = "successful";

	/** Payment failed. */
	public const FAILED = "failed";
}
