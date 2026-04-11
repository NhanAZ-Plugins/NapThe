<?php

declare(strict_types=1);

namespace NapThe;

trait SingletonTrait {

	public static NapThe $instance;

	public static function setInstance(NapThe $instance) : void {
		self::$instance = $instance;
	}

	public static function getInstance() : Donate {
		return self::$instance;
	}
}
