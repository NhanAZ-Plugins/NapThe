<?php

declare(strict_types=1);

namespace NapThe\api;

use NapThe\Constant;
use NapThe\NapThe;
use NapThe\utils\DataTypeUtils;
use pocketmine\utils\Internet;
use pocketmine\utils\InternetException;
use pocketmine\utils\InternetRequestResult;
use function is_array;
use function json_decode;
use function json_encode;
use function max;
use function md5;
use function time;

/**
 * API client for trumthe.vn card charging service
 */
final class TrumTheAPI {

	/** @var array<string, int> Last API request time by IP */
	private static array $lastApiRequests = [];

	/** @var int Cooldown between API requests in seconds */
	private static int $apiCooldown = Constant::API_COOLDOWN;

	/**
	 * Set the API request cooldown
	 *
	 * @param int $seconds Number of seconds between API requests
	 */
	public static function setApiCooldown(int $seconds) : void {
		self::$apiCooldown = max(1, $seconds); // Tối thiểu 1 giây
	}

	/**
	 * Get the current API cooldown setting
	 */
	public static function getApiCooldown() : int {
		return self::$apiCooldown;
	}

	/**
	 * Check if an API request can be made
	 *
	 * @param string $requestType Type of request (for logging)
	 * @return bool True if request can proceed, false if throttled
	 */
	private static function canMakeApiRequest(string $requestType) : bool {
		$plugin = NapThe::getInstance();
		$server = $plugin->getServer();
		$serverIp = $server->getIp();
		$currentTime = time();

		// Dùng IP máy chủ làm khoá theo dõi
		$key = $serverIp . '_' . $requestType;

		if (isset(self::$lastApiRequests[$key])) {
			$lastTime = self::$lastApiRequests[$key];
			$timeDiff = $currentTime - $lastTime;

			if ($timeDiff < self::$apiCooldown) {
				$plugin->debugLogger->log("API request throttled ($requestType) - Needs to wait " . (self::$apiCooldown - $timeDiff) . " more seconds", "api");
				return false;
			}
		}

		// Cập nhật thời gian request cuối
		self::$lastApiRequests[$key] = $currentTime;
		return true;
	}

	/**
	 * Submit a card charge request to the API
	 *
	 * @param string $telco     Card provider code
	 * @param string $code      Card code
	 * @param string $serial    Card serial number
	 * @param int    $amount    Card amount value in VND
	 * @param string $requestId Unique request ID
	 *
	 * @return ChargeResponse|null Response from the API, or null if the request failed
	 *
	 * @throws InternetException If the HTTP request fails
	 * @throws \InvalidArgumentException If API credentials are not configured
	 */
	public static function chargeCard(
		string $telco,
		string $code,
		string $serial,
		int $amount,
		string $requestId
	) : ?ChargeResponse {
		$plugin = NapThe::getInstance();

		// Kiểm tra giới hạn gọi API
		if (!self::canMakeApiRequest('charge')) {
			return new ChargeResponse(
				-1,
				"Too many API requests. Please try again in a few seconds."
			);
		}

		$partnerId = Constant::partnerId();
		$partnerKey = Constant::partnerKey();

		if ($partnerId === "" || $partnerKey === "") {
			throw new \InvalidArgumentException("TRUMTHE_PARTNER_ID and TRUMTHE_PARTNER_KEY must be set in the server process environment");
		}

		$sign = md5($partnerKey . $code . $serial);

		$data = [
			"telco" => $telco,
			"code" => $code,
			"serial" => $serial,
			"amount" => $amount,
			"request_id" => $requestId,
			"partner_id" => $partnerId,
			"sign" => $sign,
			"command" => "charging"
		];

		$plugin->debugLogger->logApi("CHARGE_REQUEST");

		$result = self::postRequest($data);
		if ($result === null) {
			$plugin->debugLogger->logApi("CHARGE_FAILED");
			return null;
		}

		$responseData = json_decode($result->getBody(), true);
		if (!is_array($responseData)) {
			$plugin->debugLogger->logApi("CHARGE_PARSE_ERROR");
			return null;
		}

		$responseDataTyped = DataTypeUtils::toStringKeyedArray($responseData);
		$plugin->debugLogger->logApi("CHARGE_RESPONSE");

		// Ghi log đặc biệt cho trạng thái ĐANG XỬ LÝ (PENDING)
		if (isset($responseData['message']) && $responseData['message'] === "PENDING") {
			$plugin->debugLogger->log("Card with RequestID: $requestId is PENDING processing (status 99 with PENDING message). This is expected behavior.", "api");
		}

		return ChargeResponse::fromArray($responseDataTyped);
	}

	/**
	 * Check the status of a card charge request
	 *
	 * @param string                   $requestId Request ID to check
	 * @param array<string,mixed>|null $cardInfo  Original card information (optional)
	 *
	 * @return ChargeStatusResponse|null Response from the API, or null if the request failed
	 *
	 * @throws InternetException If the HTTP request fails
	 */
	public static function checkCardStatus(string $requestId, ?array $cardInfo = null) : ?ChargeStatusResponse {
		$plugin = NapThe::getInstance();

		// Kiểm tra giới hạn gọi API
		if (!self::canMakeApiRequest('status')) {
			return new ChargeStatusResponse(
				-1,
				"Too many API requests. Please try again in a few seconds."
			);
		}

		$partnerId = Constant::partnerId();
		$partnerKey = Constant::partnerKey();
		if ($partnerId === "" || $partnerKey === "") {
			throw new \InvalidArgumentException("TRUMTHE_PARTNER_ID and TRUMTHE_PARTNER_KEY must be set in the server process environment");
		}

		// Tạo dữ liệu cơ bản
		$data = [
			"request_id" => $requestId,
			"partner_id" => $partnerId,
			"command" => "check"
		];

		// Nếu có thông tin thẻ gốc, thêm vào yêu cầu và tính toán sign giống như lúc charging
		if ($cardInfo !== null && isset($cardInfo['code'], $cardInfo['serial'])) {
			$data['telco'] = $cardInfo['telco'] ?? '';
			$data['code'] = $cardInfo['code'];
			$data['serial'] = $cardInfo['serial'];
			if (isset($cardInfo['amount'])) $data['amount'] = $cardInfo['amount'];

			// Tính sign giống như trong chargeCard
			$codeStr = DataTypeUtils::toString($cardInfo['code']);
			$serialStr = DataTypeUtils::toString($cardInfo['serial']);
			$data['sign'] = md5($partnerKey . $codeStr . $serialStr);

			$plugin->debugLogger->log(
				"Added original card info to status check request for RequestID: $requestId",
				"api"
			);
		} else {
			// Nếu không có thông tin thẻ, sử dụng sign đơn giản
			$data['sign'] = md5($partnerKey . $requestId);
		}

		$plugin->debugLogger->logApi("STATUS_REQUEST");

		$result = self::postRequest($data);
		if ($result === null) {
			$plugin->debugLogger->logApi("STATUS_FAILED");
			return null;
		}

		$responseData = json_decode($result->getBody(), true);
		if (!is_array($responseData)) {
			$plugin->debugLogger->logApi("STATUS_PARSE_ERROR");
			return null;
		}

		$responseDataTyped = DataTypeUtils::toStringKeyedArray($responseData);
		$plugin->debugLogger->logApi("STATUS_RESPONSE");

		if (isset($responseData['message']) && $responseData['message'] === "PENDING") {
			$plugin->debugLogger->log("Card with RequestID: $requestId is still PENDING processing. This is expected behavior.", "api");
		}

		return ChargeStatusResponse::fromArray($responseDataTyped);
	}

	/**
	 * Send a POST request to the API
	 *
	 * @param array<string, mixed> $data POST data
	 *
	 * @return InternetRequestResult|null Response from the API, or null if the request failed
	 *
	 * @throws InternetException If the HTTP request fails
	 */
	private static function postRequest(array $data) : ?InternetRequestResult {
		try {
			$apiUrl = Constant::URL;
			$timeout = max(1, Constant::API_TIMEOUT);

			// Mã hoá dữ liệu JSON cho POST request
			$jsonData = json_encode($data);
			if ($jsonData === false) {
				return null;
			}

			return Internet::postURL(
				$apiUrl,
				$jsonData,
				$timeout,
				["Content-Type: application/json"]
			);
		} catch (InternetException $e) {
			throw $e;
		}
	}
}
