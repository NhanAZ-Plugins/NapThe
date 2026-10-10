<?php

declare(strict_types=1);

namespace NapThe\utils;

use NapThe\Constant;
use function number_format;
use function preg_match;
use function str_starts_with;
use function stripos;
use function strpos;

/**
 * Lớp dịch các thông báo lỗi từ API thành thông báo thân thiện cho người chơi
 */
class MessageTranslator {

	/**
	 * Chuyển đổi thông báo lỗi API thành thông báo thân thiện
	 */
	public static function translateErrorMessage(string $errorMessage) : string {
		// Đây là thông báo do FormManager tạo ra - không cần dịch lại
		if (preg_match('/Vui lòng không để trống|để trống số sê-ri|sê-ri hoặc mã thẻ/', $errorMessage)) {
			return $errorMessage;
		}

		// Trường hợp đặc biệt xử lý trực tiếp
		if ($errorMessage === "charging.card_existed") {
			return "Thẻ này đã được sử dụng trước đó";
		}

		// Kiểm tra chuỗi con
		if (strpos($errorMessage, "card_existed") !== false) {
			return "Thẻ này đã được sử dụng trước đó";
		}

		// Ánh xạ các thông báo lỗi API thành thông báo thân thiện
		$errorMappings = [
			// Các lỗi từ hệ thống nạp thẻ
			"charging.card_existed" => "Thẻ này đã được sử dụng trước đó",
			"charging.card.wrong" => "Mã thẻ hoặc số serial không đúng",
			"charging.card.notmatch" => "Mệnh giá thẻ không khớp với giá trị đã chọn",
			"charging.card.unknown" => "Không thể xác định thông tin thẻ",
			"charging.card.invalid" => "Thẻ không hợp lệ hoặc đã bị khóa",
			"charging.card.timeout" => "Quá thời gian xử lý thẻ, vui lòng thử lại sau",
			"charging.card.provider_error" => "Lỗi từ nhà mạng, vui lòng thử lại sau",
			"charging.pending" => "Thẻ đang được xử lý, vui lòng đợi",
			"system.maintenance" => "Hệ thống đang bảo trì, vui lòng thử lại sau",

			// Thêm các thông báo lỗi phổ biến khác
			"charging.invalid_card_code" => "Mã thẻ không hợp lệ, vui lòng kiểm tra lại",
			"charging.invalid_serial" => "Số serial không hợp lệ, vui lòng kiểm tra lại",
			"charging.wrong_telco" => "Loại thẻ không đúng, vui lòng chọn đúng nhà mạng",
			"charging.invalid_amount" => "Mệnh giá thẻ không đúng, vui lòng chọn đúng mệnh giá",
			"charging.card.used" => "Thẻ đã được sử dụng trước đó",
			"charging.invalid_partner" => "Lỗi xác thực đối tác thanh toán",
			"charging.wrong_format" => "Định dạng thẻ không đúng",
			"charging.invalid_sign" => "Lỗi xác thực chữ ký",
			"charging.invalid_request" => "Yêu cầu không hợp lệ",
			"charging.card.locked" => "Thẻ đã bị khóa hoặc tạm ngưng",
			"charging.invalid_telco" => "Nhà mạng không được hỗ trợ",
			"charging.telco_maintain" => "Nhà mạng đang bảo trì",

			// Thông báo từ FormManager
			"Vui lòng không để trống số sê-ri hoặc mã thẻ!" => "Vui lòng không để trống số sê-ri hoặc mã thẻ!",
			"Có lỗi xảy ra khi xử lý biểu mẫu!" => "Có lỗi xảy ra khi xử lý biểu mẫu!",

			// Các lỗi kết nối
			"connection.failed" => "Không thể kết nối đến máy chủ thanh toán",
			"connection.timeout" => "Kết nối đến máy chủ thanh toán quá thời gian chờ",

			// Thông báo thành công
			"payment.successful" => "Thẻ nạp thành công!",

			// Thông báo mặc định
			"default" => "Có lỗi xảy ra khi xử lý thẻ"
		];

		// Trả về ngay nếu tìm thấy trong bản đồ lỗi
		if (isset($errorMappings[$errorMessage])) {
			return $errorMappings[$errorMessage];
		}

		// Xử lý các trường hợp đặc biệt - tìm theo tiền tố
		foreach ($errorMappings as $errorKey => $friendlyMessage) {
			// Kiểm tra nếu thông báo lỗi bắt đầu bằng một trong các mã đã biết
			if (str_starts_with($errorMessage, $errorKey)) {
				return $friendlyMessage;
			}

			// Kiểm tra nếu thông báo lỗi chứa mã lỗi này
			if (stripos($errorMessage, $errorKey) !== false) {
				return $friendlyMessage;
			}
		}

		// Xử lý một số mẫu thông báo đặc biệt
		if (stripos($errorMessage, "card_existed") !== false) {
			return "Thẻ này đã được sử dụng trước đó";
		}

		if (stripos($errorMessage, "wrong") !== false && stripos($errorMessage, "card") !== false) {
			return "Thông tin thẻ không chính xác";
		}

		if (stripos($errorMessage, "invalid") !== false && stripos($errorMessage, "code") !== false) {
			return "Mã thẻ không hợp lệ";
		}

		if (stripos($errorMessage, "invalid") !== false && stripos($errorMessage, "serial") !== false) {
			return "Số serial không hợp lệ";
		}

		if (stripos($errorMessage, "timeout") !== false) {
			return "Quá thời gian xử lý thẻ, vui lòng thử lại sau";
		}

		if (stripos($errorMessage, "maintain") !== false) {
			return "Hệ thống đang bảo trì, vui lòng thử lại sau";
		}

		// Unknown provider text may contain card details. Never echo it to players or logs.
		return "Có lỗi xảy ra khi xử lý thẻ";
	}

	/**
	 * Định dạng thông báo lỗi với prefix
	 */
	public static function formatErrorMessage(string $errorMessage) : string {
		$translatedMessage = self::translateErrorMessage($errorMessage);
		return Constant::PREFIX . $translatedMessage;
	}

	/**
	 * Định dạng thông báo thành công với prefix
	 */
	public static function formatSuccessMessage(string $message) : string {
		return Constant::PREFIX . $message;
	}

	/**
	 * Định dạng thông báo thông tin với prefix
	 */
	public static function formatInfoMessage(string $message) : string {
		return Constant::PREFIX . $message;
	}

	/**
	 * Định dạng thông báo số tiền
	 */
	public static function formatAmount(int $amount) : string {
		return number_format($amount, 0, ",", ".") . "₫";
	}
}
