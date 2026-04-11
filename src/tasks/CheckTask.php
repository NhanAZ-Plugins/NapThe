<?php

declare(strict_types=1);

namespace NapThe\tasks;

use NapThe\NapThe;
use pocketmine\scheduler\Task;

/**
 * Task to check payment statuses periodically
 */
class CheckTask extends Task {
	/** @param NapThe $plugin Thực thể chính của plugin */
	private NapThe $plugin;

	public function __construct(NapThe $plugin) {
		$this->plugin = $plugin;
	}
	public function onRun() : void {
		$this->plugin->getLogger()->debug("Running payment status check task...");
	}

	public function onCompletion() : void {
	}
}
