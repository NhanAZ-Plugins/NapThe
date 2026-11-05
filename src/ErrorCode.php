<?php

declare(strict_types=1);

namespace NapThe;

/**
 * Error codes used throughout the plugin
 */
final class ErrorCode {
	/** No error */
	public const NONE = 0;

	/** Generic error */
	public const GENERIC_ERROR = 1;

	/** Network/connection error */
	public const NETWORK_ERROR = 2;

	/** API error */
	public const API_ERROR = 3;

	/** Invalid card error */
	public const INVALID_CARD = 4;

	/** Configuration error */
	public const CONFIG_ERROR = 5;

	/** Permission error */
	public const PERMISSION_ERROR = 6;

	/** Database error */
	public const DATABASE_ERROR = 7;

	/** Invalid input error */
	public const INVALID_INPUT = 8;

	/** Server error */
	public const SERVER_ERROR = 9;

	/** Unknown error */
	public const UNKNOWN_ERROR = 99;

	/**
	 * Get a human-readable error message for an error code
	 */
	public static function getMessage(int $code) : string {
		return match ($code) {
			self::NONE => "No error",
			self::GENERIC_ERROR => "Unknown error",
			self::NETWORK_ERROR => "Network error",
			self::API_ERROR => "API error",
			self::INVALID_CARD => "Invalid card",
			self::CONFIG_ERROR => "Configuration error",
			self::PERMISSION_ERROR => "Permission denied",
			self::DATABASE_ERROR => "Database error",
			self::INVALID_INPUT => "Invalid input",
			self::SERVER_ERROR => "Server error",
			self::UNKNOWN_ERROR => "Unknown error",
			default => "Unknown error code: $code"
		};
	}
}
