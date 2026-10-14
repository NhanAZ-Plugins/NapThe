<?php

declare(strict_types=1);

namespace pocketmine\utils {
	final class InternetException extends \RuntimeException {
	}

	final class InternetRequestResult {
	}

	final class Internet {
		/** @var array{url: string, body: string, timeout: int, headers: list<string>}|null */
		public static ?array $lastRequest = null;

		/** @param list<string> $headers */
		public static function postURL(string $url, string $body, int $timeout, array $headers) : InternetRequestResult {
			self::$lastRequest = [
				'url' => $url,
				'body' => $body,
				'timeout' => $timeout,
				'headers' => $headers
			];

			return new InternetRequestResult();
		}
	}
}

namespace {
	require __DIR__ . '/../src/Constant.php';
	require __DIR__ . '/../src/api/TrumTheAPI.php';

	putenv('TRUMTHE_API_URL=https://provider.example/chargingws/v2');
	$data = [
		'telco' => 'VIETTEL',
		'code' => 'code +/=?',
		'serial' => 'serial&value',
		'amount' => 10000,
		'request_id' => 'nap-the-123',
		'partner_id' => 'merchant id',
		'sign' => md5('partner-key' . 'code +/=?' . 'serial&value'),
		'command' => 'charging'
	];

	$method = new \ReflectionMethod(\NapThe\api\TrumTheAPI::class, 'postRequest');
	$method->invoke(null, $data);
	$request = \pocketmine\utils\Internet::$lastRequest;
	$expectedBody = 'telco=VIETTEL&code=code+%2B%2F%3D%3F&serial=serial%26value&amount=10000&request_id=nap-the-123&partner_id=merchant+id&sign=' . $data['sign'] . '&command=charging';
	if (
		$request === null ||
		$request['url'] !== 'https://provider.example/chargingws/v2' ||
		$request['body'] !== $expectedBody ||
		$request['timeout'] !== 10 ||
		$request['headers'] !== ['Content-Type: application/x-www-form-urlencoded']
	) {
		throw new \RuntimeException('Provider request did not use the expected form-encoded fields and headers');
	}

	putenv('TRUMTHE_API_URL');
	echo "Provider request uses the expected URL-encoded POST format\n";
}
