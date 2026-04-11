<?php

declare(strict_types=1);

namespace NapThe;

/**
 * Các hằng số được sử dụng trong plugin.
 */
class Constant {
	/** Tiền tố plugin cho các thông báo */
	public const PREFIX = "§8[§6Nạp Thẻ§8] ";

	/** Chỉnh sửa tài khoản API trực tiếp tại đây thay vì dùng config.yml */
	public const PARTNER_ID = "";
	public const PARTNER_KEY = "";

	/** API endpoint for trumthe.vn */
	public const URL = "https://trumthe.vn/chargingws/v2";

	/** Timeout for API calls in seconds */
	public const API_TIMEOUT = 10;

	/** Cooldown between API requests in seconds */
	public const API_COOLDOWN = 2;

	/** Cooldown between napthe form opens/submissions in seconds */
	public const FORM_COOLDOWN = 5;

	/** Pending payment check interval in seconds */
	public const CHECK_INTERVAL = 30;

	/** Immediate recheck interval for pending payments in seconds */
	public const IMMEDIATE_CHECK_INTERVAL = 5;

	/** Maximum immediate rechecks for pending payments */
	public const MAX_IMMEDIATE_CHECKS = 12;

	/** Multiplier applied to successful donations */
	public const BONUS = 1.0;

	/** Telco codes for API */
	public const TELCO = [
		"VIETTEL",
		"VINA",
		"MOBI",
		"VIETNAMMOBI",
		"ZING",
		"GARENA",
		"VCOIN",
		"GATE"
	];

	/** Display names for telcos */
	public const TELCO_DISPLAY = [
		"Viettel",
		"VinaPhone",
		"MobiFone",
		"Vietnamobile",
		"Zing",
		"Garena",
		"VCoin",
		"Gate"
	];

	/** Không thay đổi giá trị của hằng này */
	public const AMOUNT = [
		10000,
		20000,
		50000,
		100000,
		200000,
		500000
	];

	/** Display strings for card amounts */
	public const AMOUNT_DISPLAY = [
		"10.000₫",
		"20.000₫",
		"50.000₫",
		"100.000₫",
		"200.000₫",
		"500.000₫",
	];
}
