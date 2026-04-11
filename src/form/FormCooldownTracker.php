<?php

declare(strict_types=1);

namespace Donate\form;

final class FormCooldownTracker {
	/** @var array<string, int> */
	private array $lastDonateSubmissionAt = [];

	public function __construct(private int $cooldown) {
	}

	public function getCooldown() : int {
		return $this->cooldown;
	}

	public function getRemainingDonateCooldown(string $playerName, int $currentTime) : int {
		$lastTime = $this->lastDonateSubmissionAt[$playerName] ?? null;
		if ($lastTime === null) {
			return 0;
		}

		$timeDiff = $currentTime - $lastTime;
		if ($timeDiff >= $this->cooldown) {
			return 0;
		}

		return $this->cooldown - $timeDiff;
	}

	public function touchDonateCooldown(string $playerName, int $currentTime) : void {
		$this->lastDonateSubmissionAt[$playerName] = $currentTime;
	}
}
