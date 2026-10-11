<?php

declare(strict_types=1);

namespace pocketmine\scheduler {
	final class ClosureTask {
		public function __construct(private \Closure $callback) {
		}

		public function onRun() : void {
			($this->callback)();
		}
	}
}

namespace NapThe\api {
	final class TrumTheAPI {
		public static ?ChargeStatusResponse $next = null;

		public static function checkCardStatus(string $requestId, ?array $cardInfo = null) : ?ChargeStatusResponse {
			return self::$next;
		}
	}
}

namespace NapThe {
	final class TestServer {
		public function getPlayerExact(string $name) : null {
			return null;
		}
	}

	final class TestScheduler {
		/** @var list<\pocketmine\scheduler\ClosureTask> */
		private array $tasks = [];

		public function scheduleDelayedTask(\pocketmine\scheduler\ClosureTask $task, int $delay) : void {
			$this->tasks[] = $task;
		}

		public function runOne() : void {
			$task = array_shift($this->tasks);
			if ($task === null) {
				throw new \RuntimeException('No status check was scheduled');
			}
			$task->onRun();
		}
	}

	final class TestLogger {
		public function log(string $message, string $category) : void {
		}

		public function logPayment(string $action, string $player, string $requestId, array $details) : void {
		}
	}

	final class NapThe {
		public TestLogger $debugLogger;
		public int $donations = 0;
		private TestServer $server;
		private TestScheduler $scheduler;

		public function __construct() {
			$this->debugLogger = new TestLogger();
			$this->server = new TestServer();
			$this->scheduler = new TestScheduler();
		}

		public function getServer() : TestServer {
			return $this->server;
		}

		public function getScheduler() : TestScheduler {
			return $this->scheduler;
		}

		public function successfulDonation(string $playerName, int $amount) : void {
			$this->donations++;
		}
	}
}

namespace {
	require __DIR__ . '/../src/Constant.php';
	require __DIR__ . '/../src/StatusCode.php';
	require __DIR__ . '/../src/api/ChargeStatusResponse.php';
	require __DIR__ . '/../src/payment/PaymentStatus.php';
	require __DIR__ . '/../src/payment/CardPayment.php';
	require __DIR__ . '/../src/payment/PendingPaymentStore.php';
	require __DIR__ . '/../src/payment/service/PaymentOutcomeHandler.php';
	require __DIR__ . '/../src/payment/service/ImmediateCheckScheduler.php';
	require __DIR__ . '/../src/payment/service/PendingPaymentChecker.php';

	function checkStatus(int $status, bool $immediate, bool $final, string $message = 'Provider response') : void {
		$plugin = new \NapThe\NapThe();
		$store = new \NapThe\payment\PendingPaymentStore();
		$id = 'status-' . $status;
		$payment = new \NapThe\payment\CardPayment($id, 'Player', 'TELCO', 'code', 'serial', 10000, time() - 31);
		$store->add($id, $payment);
		$outcome = new \NapThe\payment\service\PaymentOutcomeHandler($plugin, $store);
		$scheduler = new \NapThe\payment\service\ImmediateCheckScheduler($plugin, $store, $outcome);
		$checker = new \NapThe\payment\service\PendingPaymentChecker($plugin, $store, $outcome, $scheduler);
		\NapThe\api\TrumTheAPI::$next = new \NapThe\api\ChargeStatusResponse($status, $message);

		if ($immediate) {
			$scheduler->scheduleImmediateCheck($id, 1, 0);
			$plugin->getScheduler()->runOne();
		} else {
			$checker->checkPendingPayments();
		}

		if (($store->get($id) === null) !== $final || $plugin->donations !== ($status === \NapThe\StatusCode::SUCCESS ? 1 : 0)) {
			throw new \RuntimeException('Incorrect finality for status ' . $status . ' on ' . ($immediate ? 'immediate' : 'periodic') . ' check');
		}
	}

	foreach ([-1, 2, 3, 7, 8, 9, 42] as $status) {
		checkStatus($status, false, false);
		checkStatus($status, true, false);
	}
	checkStatus(99, false, false, 'PENDING');
	checkStatus(99, true, false, 'PENDING');
	foreach ([1, 4, 5, 6, 99] as $status) {
		checkStatus($status, false, true);
		checkStatus($status, true, true);
	}

	echo "Periodic and immediate checks retain inconclusive statuses\n";
}
