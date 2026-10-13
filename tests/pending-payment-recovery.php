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
		public static ?\NapThe\api\ChargeStatusResponse $next = null;
		public static ?array $lastCardInfo = null;
		public static int $checks = 0;

		public static function checkCardStatus(string $requestId, ?array $cardInfo = null) : ?ChargeStatusResponse {
			self::$checks++;
			self::$lastCardInfo = $cardInfo;
			return self::$next;
		}
	}
}

namespace NapThe {
	use NapThe\payment\DonationLedger;

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
	}

	final class TestLogger {
		public function log(string $message, string $category) : void {
		}

		public function logPayment(string $action, string $player, string $requestId, array $details) : void {
		}
	}

	final class NapThe {
		public TestLogger $debugLogger;
		private TestServer $server;
		private TestScheduler $scheduler;

		public function __construct(private DonationLedger $ledger) {
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

		public function successfulDonationOnce(string $requestId, string $playerName, int $amount) : bool {
			return $this->ledger->record($requestId, $playerName, $amount);
		}
	}
}

namespace {
	require __DIR__ . '/../src/StatusCode.php';
	require __DIR__ . '/../src/api/ChargeStatusResponse.php';
	require __DIR__ . '/../src/payment/PaymentStatus.php';
	require __DIR__ . '/../src/payment/CardPayment.php';
	require __DIR__ . '/../src/payment/DonationLedger.php';
	require __DIR__ . '/../src/payment/PendingPaymentStore.php';
	require __DIR__ . '/../src/payment/service/PaymentOutcomeHandler.php';
	require __DIR__ . '/../src/payment/service/ImmediateCheckScheduler.php';
	require __DIR__ . '/../src/payment/service/PendingPaymentChecker.php';

	if (!extension_loaded('pdo_sqlite')) {
		throw new \RuntimeException('pdo_sqlite is required for pending payment recovery');
	}
	$path = tempnam(sys_get_temp_dir(), 'napthe-pending-');
	if ($path === false) {
		throw new \RuntimeException('Could not create the pending payment fixture');
	}

	$ledger = new \NapThe\payment\DonationLedger($path, []);
	$store = new \NapThe\payment\PendingPaymentStore($ledger);
	$createdAt = time() - 31;
	$payment = new \NapThe\payment\CardPayment('recover-1', 'Player', 'TELCO', 'sensitive-code', 'sensitive-serial', 10000, $createdAt);
	$store->add('recover-1', $payment);
	unset($payment, $store, $ledger);

	$ledger = new \NapThe\payment\DonationLedger($path, []);
	$store = new \NapThe\payment\PendingPaymentStore($ledger);
	$recovered = $store->get('recover-1');
	if ($recovered === null || $recovered->getPlayerName() !== 'Player' || $recovered->getAmount() !== 10000 || $recovered->getCreatedAt() !== $createdAt || $recovered->toCardInfo() !== null) {
		throw new \RuntimeException('Pending payment metadata was not safely restored after reopening the ledger');
	}
	$schema = (new \PDO('sqlite:' . $path))->query('PRAGMA table_info(pending_payments)')->fetchAll(\PDO::FETCH_ASSOC);
	$columns = array_column($schema, 'name');
	if ($columns !== ['request_id', 'player_name', 'amount', 'created_at']) {
		throw new \RuntimeException('Pending ledger schema stores unexpected payment fields');
	}

	$plugin = new \NapThe\NapThe($ledger);
	$outcome = new \NapThe\payment\service\PaymentOutcomeHandler($plugin, $store);
	$scheduler = new \NapThe\payment\service\ImmediateCheckScheduler($plugin, $store, $outcome);
	$checker = new \NapThe\payment\service\PendingPaymentChecker($plugin, $store, $outcome, $scheduler);
	\NapThe\api\TrumTheAPI::$next = new \NapThe\api\ChargeStatusResponse(2, 'PENDING');
	$checker->checkPendingPayments();
	if (\NapThe\api\TrumTheAPI::$checks !== 1 || \NapThe\api\TrumTheAPI::$lastCardInfo !== null || $store->get('recover-1') === null || count($ledger->pendingPayments()) !== 1) {
		throw new \RuntimeException('Recovered status check sent card fields or discarded a pending request');
	}

	\NapThe\api\TrumTheAPI::$next = new \NapThe\api\ChargeStatusResponse(1, 'SUCCESS');
	$checker->checkPendingPayments();
	if ($store->hasAny() || $ledger->pendingPayments() !== [] || $ledger->totals() !== ['Player' => 10000]) {
		throw new \RuntimeException('Recovered success was not finalized exactly once');
	}
	unset($plugin, $checker, $scheduler, $outcome, $store, $ledger);
	$ledger = new \NapThe\payment\DonationLedger($path, []);
	if ($ledger->totals() !== ['Player' => 10000] || $ledger->pendingPayments() !== []) {
		throw new \RuntimeException('Finalized recovered payment was duplicated or restored as pending');
	}

	unset($ledger);
	if (!unlink($path)) {
		throw new \RuntimeException('Could not remove the temporary pending payment fixture');
	}

	echo "Pending payment recovery stores metadata only, checks without card fields and finalizes once\n";
}
