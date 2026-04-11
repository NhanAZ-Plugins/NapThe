<?php

declare(strict_types=1);

namespace NapThe\tasks;

use NapThe\Constant;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use function is_array;
use function is_string;

/**
 * Task to handle card charging asynchronously
 */
class ChargingTask extends AsyncTask {
	/**
	 * @param string $playerName Name of the player
	 * @param string $telco      Telco code
	 * @param string $code       Card code
	 * @param string $serial     Card serial number
	 * @param int    $amount     Card amount
	 * @param string $requestId  Unique request ID
	 */
	public function __construct(
		private string $playerName,
		private string $telco,
		private string $code,
		private string $serial,
		private int $amount,
		private string $requestId
	) {
	}

	public function onRun() : void {
		// Luồng thanh toán đồng bộ hiện được xử lý trong PaymentManager + payment services.
		// Task bất đồng bộ này chỉ mang siêu dữ liệu trả về luồng chính.

		// Lưu kết quả để sử dụng trong onCompletion
		$this->setResult([
			'playerName' => $this->playerName,
			'telco' => $this->telco,
			'code' => $this->code,
			'serial' => $this->serial,
			'amount' => $this->amount,
			'requestId' => $this->requestId
		]);
	}

	public function onCompletion() : void {
		$data = $this->getResult();
		if (!is_array($data)) {
			return;
		}

		$playerName = is_string($data['playerName']) ? $data['playerName'] : '';

		// Lấy người chơi đã thực hiện nạp thẻ
		$player = Server::getInstance()->getPlayerExact($playerName);

		// Nếu người chơi đang online, thông báo cho họ bước tiếp theo
		if ($player !== null) {
			$player->sendMessage(Constant::PREFIX . "Thẻ của bạn đang được xử lý. Vui lòng đợi, bạn sẽ nhận được thông báo khi quá trình hoàn tất.");
		}
	}
}
