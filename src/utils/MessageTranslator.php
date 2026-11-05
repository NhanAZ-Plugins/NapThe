<?php

declare(strict_types=1);

namespace NapThe\utils;

use NapThe\Constant;
use function number_format;
use function preg_match;
use function str_starts_with;
use function stripos;
use function strpos;

/** Maps provider errors to safe player-facing messages. */
final class MessageTranslator {
	/** Convert provider errors into safe player-facing messages. */
	public static function translateErrorMessage(string $errorMessage) : string {
		if (preg_match('/Please enter both the serial number and card code|Please wait/i', $errorMessage) === 1) {
			return $errorMessage;
		}

		if ($errorMessage === "charging.card_existed" || strpos($errorMessage, "card_existed") !== false) {
			return "This card has already been used.";
		}

		$errorMappings = [
			"charging.card_existed" => "This card has already been used.",
			"charging.card.wrong" => "The card code or serial number is incorrect.",
			"charging.card.notmatch" => "The card value does not match the selected amount.",
			"charging.card.unknown" => "The card details could not be identified.",
			"charging.card.invalid" => "The card is invalid or locked.",
			"charging.card.timeout" => "The card request timed out. Try again later.",
			"charging.card.provider_error" => "The provider returned an error. Try again later.",
			"charging.pending" => "The card is still being processed.",
			"system.maintenance" => "The provider is under maintenance. Try again later.",
			"charging.invalid_card_code" => "The card code is invalid. Check it and try again.",
			"charging.invalid_serial" => "The serial number is invalid. Check it and try again.",
			"charging.wrong_telco" => "The card provider is incorrect.",
			"charging.invalid_amount" => "The card amount is invalid.",
			"charging.card.used" => "This card has already been used.",
			"charging.invalid_partner" => "Payment partner authentication failed.",
			"charging.wrong_format" => "The card format is invalid.",
			"charging.invalid_sign" => "Signature validation failed.",
			"charging.invalid_request" => "The request is invalid.",
			"charging.card.locked" => "The card is locked or suspended.",
			"charging.invalid_telco" => "This card provider is not supported.",
			"charging.telco_maintain" => "The card provider is under maintenance.",
			"Please enter both the serial number and card code." => "Please enter both the serial number and card code.",
			"An error occurred while processing the form." => "An error occurred while processing the form.",
			"connection.failed" => "Could not connect to the payment provider.",
			"connection.timeout" => "The connection to the payment provider timed out.",
			"payment.successful" => "The card was charged successfully.",
			"default" => "An error occurred while processing the card."
		];

		if (isset($errorMappings[$errorMessage])) {
			return $errorMappings[$errorMessage];
		}

		foreach ($errorMappings as $errorKey => $friendlyMessage) {
			if (str_starts_with($errorMessage, $errorKey) || stripos($errorMessage, $errorKey) !== false) {
				return $friendlyMessage;
			}
		}

		if (stripos($errorMessage, "card_existed") !== false) {
			return "This card has already been used.";
		}
		if (stripos($errorMessage, "wrong") !== false && stripos($errorMessage, "card") !== false) {
			return "The card information is incorrect.";
		}
		if (stripos($errorMessage, "invalid") !== false && stripos($errorMessage, "code") !== false) {
			return "The card code is invalid.";
		}
		if (stripos($errorMessage, "invalid") !== false && stripos($errorMessage, "serial") !== false) {
			return "The serial number is invalid.";
		}
		if (stripos($errorMessage, "timeout") !== false) {
			return "The card request timed out. Try again later.";
		}
		if (stripos($errorMessage, "maintain") !== false) {
			return "The provider is under maintenance. Try again later.";
		}

		return "An error occurred while processing the card.";
	}

	/** Format an error message with the plugin prefix. */
	public static function formatErrorMessage(string $errorMessage) : string {
		return Constant::PREFIX . self::translateErrorMessage($errorMessage);
	}

	/** Format a success message with the plugin prefix. */
	public static function formatSuccessMessage(string $message) : string {
		return Constant::PREFIX . $message;
	}

	/** Format an informational message with the plugin prefix. */
	public static function formatInfoMessage(string $message) : string {
		return Constant::PREFIX . $message;
	}

	/** Format a card amount. */
	public static function formatAmount(int $amount) : string {
		return number_format($amount, 0, ",", ".") . "₫";
	}
}
