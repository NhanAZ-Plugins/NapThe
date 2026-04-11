<?php

declare(strict_types=1);

namespace Donate\manager;

use Donate\api\ChargeResponse;
use Donate\Constant;
use Donate\Donate;
use Donate\payment\CardPayment;
use Donate\payment\PendingPaymentStore;
use Donate\payment\service\ChargeRequestHandler;
use Donate\payment\service\ImmediateCheckScheduler;
use Donate\payment\service\PaymentOutcomeHandler;
use Donate\payment\service\PendingPaymentChecker;
use Donate\tasks\PaymentCheckTask;
use pocketmine\player\Player;

class PaymentManager {
	private PendingPaymentStore $pendingPaymentStore;

	private ChargeRequestHandler $chargeRequestHandler;

	private PendingPaymentChecker $pendingPaymentChecker;

	private ImmediateCheckScheduler $immediateCheckScheduler;

	private Donate $plugin;

	public function __construct(Donate $plugin) {
		$this->plugin = $plugin;
		$this->pendingPaymentStore = new PendingPaymentStore();

		$outcomeHandler = new PaymentOutcomeHandler($plugin, $this->pendingPaymentStore);
		$this->immediateCheckScheduler = new ImmediateCheckScheduler($plugin, $this->pendingPaymentStore, $outcomeHandler);
		$this->chargeRequestHandler = new ChargeRequestHandler($plugin, $this->pendingPaymentStore, $this->immediateCheckScheduler);
		$this->pendingPaymentChecker = new PendingPaymentChecker($plugin, $this->pendingPaymentStore, $outcomeHandler, $this->immediateCheckScheduler);
	}

	public function startPaymentCheckTask() : void {
		$this->plugin->getScheduler()->scheduleRepeatingTask(
			new PaymentCheckTask($this),
			20 * Constant::CHECK_INTERVAL
		);
	}

	public function processCardPayment(
		Player $player,
		string $telco,
		string $code,
		string $serial,
		int $amount,
		string $requestId
	) : ChargeResponse {
		return $this->chargeRequestHandler->processCardPayment(
			$player,
			$telco,
			$code,
			$serial,
			$amount,
			$requestId
		);
	}

	/**
	 * @return array<string, CardPayment>
	 */
	public function checkPendingPayments() : array {
		return $this->pendingPaymentChecker->checkPendingPayments();
	}

	public function getPayment(string $requestId) : ?CardPayment {
		return $this->pendingPaymentStore->get($requestId);
	}

	/**
	 * @return array<string, CardPayment>
	 */
	public function getPendingPayments() : array {
		return $this->pendingPaymentStore->all();
	}

	public function hasPendingPayments() : bool {
		return $this->pendingPaymentStore->hasAny();
	}

	public function addSamplePayment(string $requestId, CardPayment $payment) : void {
		$this->pendingPaymentStore->add($requestId, $payment);
		$this->plugin->logger->info("[Donate/Sample] Added sample payment - RequestID: {$requestId}, Player: {$payment->getPlayerName()}, Amount: {$payment->getAmount()}");
	}

	public function scheduleImmediateCheck(
		string $requestId,
		int $retryDelay = Constant::IMMEDIATE_CHECK_INTERVAL,
		int $maxRetries = Constant::MAX_IMMEDIATE_CHECKS
	) : void {
		$this->immediateCheckScheduler->scheduleImmediateCheck($requestId, $retryDelay, $maxRetries);
	}
}
