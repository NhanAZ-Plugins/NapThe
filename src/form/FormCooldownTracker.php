<?php

declare(strict_types=1);

namespace NapThe\form;

final class FormCooldownTracker {
	/** @var array<string, int> */
	private array $lastNapTheSubmissionAt = [];

	public function __construct(private int $cooldown) {
	}

	public function getCooldown() : int {
		return $this->cooldown;
	}

	public function getRemainingNapTheCooldown(string $playerName, int $currentTime) : int {
		$lastTime = $this->lastNapTheSubmissionAt[$playerName] ?? null;
		if ($lastTime === null) {
			return 0;
		}

		$timeDiff = $currentTime - $lastTime;
		if ($timeDiff >= $this->cooldown) {
			return 0;
		}

		return $this->cooldown - $timeDiff;
	}

	public function touchNapTheCooldown(string $playerName, int $currentTime) : void {
		$this->lastNapTheSubmissionAt[$playerName] = $currentTime;
	}
}
