<?php

declare(strict_types=1);

namespace NapThe {
	final class TestServer {
		public function getIp() : string {
			return '127.0.0.1';
		}

		public function getPlayerExact(string $name) : null {
			return null;
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
		private static ?self $instance = null;

		public function __construct() {
			$this->debugLogger = new TestLogger();
			$this->server = new TestServer();
			self::$instance = $this;
		}

		public static function getInstance() : self {
			return self::$instance ?? throw new \RuntimeException('Test plugin unavailable');
		}

		public function getServer() : TestServer {
			return $this->server;
		}
	}
}

namespace {
	require __DIR__ . '/../src/Constant.php';
	require __DIR__ . '/../src/StatusCode.php';
	require __DIR__ . '/../src/api/ChargeStatusResponse.php';
	require __DIR__ . '/../src/api/TrumTheAPI.php';
	require __DIR__ . '/../src/payment/PaymentStatus.php';
	require __DIR__ . '/../src/payment/CardPayment.php';
	require __DIR__ . '/../src/payment/PendingPaymentStore.php';
	require __DIR__ . '/../src/payment/service/PaymentOutcomeHandler.php';
	require __DIR__ . '/../src/payment/service/ImmediateCheckScheduler.php';
	require __DIR__ . '/../src/payment/service/PendingPaymentChecker.php';

	$plugin = new \NapThe\NapThe();
	$store = new \NapThe\payment\PendingPaymentStore();
	$payment = new \NapThe\payment\CardPayment('retry-1', 'Player', 'TELCO', 'code', 'serial', 10000, time() - 31);
	$store->add($payment->getRequestId(), $payment);
	$outcome = new \NapThe\payment\service\PaymentOutcomeHandler($plugin, $store);
	$scheduler = new \NapThe\payment\service\ImmediateCheckScheduler($plugin, $store, $outcome);
	$checker = new \NapThe\payment\service\PendingPaymentChecker($plugin, $store, $outcome, $scheduler);
	\NapThe\api\TrumTheAPI::setApiCooldown(60);
	$requests = new \ReflectionProperty(\NapThe\api\TrumTheAPI::class, 'lastApiRequests');
	$requests->setValue(null, ['127.0.0.1_status' => time()]);

	if ($checker->checkPendingPayments() !== [] || $store->get('retry-1') !== $payment || !$payment->isPending()) {
		throw new \RuntimeException('A throttled status check finalized a pending payment');
	}

	echo "Pending payment survives a throttled status check\n";
}
