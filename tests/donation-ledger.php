<?php

declare(strict_types=1);

require __DIR__ . '/../src/payment/DonationLedger.php';

use NapThe\payment\DonationLedger;

if (!extension_loaded('pdo_sqlite')) {
	throw new RuntimeException('pdo_sqlite is required for the donation ledger');
}

$path = tempnam(sys_get_temp_dir(), 'napthe-ledger-');
if ($path === false) {
	throw new RuntimeException('Could not create the donation ledger fixture');
}

$ledger = new DonationLedger($path, ['Legacy' => 100]);
if ($ledger->totals() !== ['Legacy' => 100]) {
	throw new RuntimeException('Legacy totals were not imported');
}
if (!$ledger->record('request-1', 'Legacy', 40) || $ledger->record('request-1', 'Legacy', 40)) {
	throw new RuntimeException('The request was not recorded exactly once');
}
if ($ledger->totals() !== ['Legacy' => 140]) {
	throw new RuntimeException('Duplicate delivery changed the total');
}

try {
	$ledger->record('request-1', 'Other', 40);
	throw new RuntimeException('A conflicting request ID was accepted');
} catch (UnexpectedValueException) {
}

unset($ledger);
$ledger = new DonationLedger($path, ['Legacy' => 9999]);
if ($ledger->totals() !== ['Legacy' => 140] || $ledger->record('request-1', 'Legacy', 40)) {
	throw new RuntimeException('Restart lost the processed request or reimported stale YAML');
}
if (!$ledger->record('request-2', 'Other', 50) || $ledger->totals() !== ['Legacy' => 140, 'Other' => 50]) {
	throw new RuntimeException('A new request could not be recorded after rollback or restart');
}
if (!$ledger->record('request-max', 'Huge', PHP_INT_MAX)) {
	throw new RuntimeException('A valid maximum total was rejected');
}
try {
	$ledger->record('request-overflow', 'Huge', 1);
	throw new RuntimeException('An overflowing total was accepted');
} catch (OverflowException) {
}
if (!$ledger->record('request-overflow', 'Other', 1) || $ledger->totals()['Huge'] !== PHP_INT_MAX) {
	throw new RuntimeException('An overflow left a partial request or changed the total');
}

echo "Donation ledger preserves totals and request IDs across restart: $path\n";
