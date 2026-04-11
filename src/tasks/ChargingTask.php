<?php

declare(strict_types=1);

namespace Donate\tasks;

use Donate\Constant;
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
		// The synchronous payment flow now lives in PaymentManager + payment services.
		// This async task only carries request metadata back to the main thread.

		// Store the result to be accessed in onCompletion
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

		// Get the player who initiated the charge
		$player = Server::getInstance()->getPlayerExact($playerName);

		// If player is still online, inform them about the next step
		if ($player !== null) {
			$player->sendMessage(Constant::PREFIX . "Thẻ của bạn đang được xử lý. Vui lòng đợi, bạn sẽ nhận được thông báo khi quá trình hoàn tất.");
		}
	}
}
