<?php

declare(strict_types=1);

namespace NapThe\payment;

use InvalidArgumentException;
use OverflowException;
use PDO;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final class DonationLedger {
	private PDO $database;

	/** @param array<array-key, mixed> $legacyTotals */
	public function __construct(string $path, array $legacyTotals) {
		$this->database = new PDO('sqlite:' . $path);
		$this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$this->database->exec('PRAGMA busy_timeout = 5000');
		$this->database->exec('PRAGMA synchronous = FULL');
		$this->database->exec('CREATE TABLE IF NOT EXISTS donation_totals (player_name TEXT PRIMARY KEY, amount INTEGER NOT NULL CHECK (amount >= 0))');
		$this->database->exec('CREATE TABLE IF NOT EXISTS processed_donations (request_id TEXT PRIMARY KEY, player_name TEXT NOT NULL, amount INTEGER NOT NULL CHECK (amount > 0), processed_at INTEGER NOT NULL)');
		$this->database->exec('CREATE TABLE IF NOT EXISTS ledger_metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
		$this->importLegacyTotals($legacyTotals);
	}

	/** @return array<array-key, int> */
	public function totals() : array {
		$totals = [];
		$rows = $this->database->query('SELECT player_name, amount FROM donation_totals');
		if ($rows === false) {
			throw new RuntimeException('Could not read donation totals');
		}
		while (($row = $rows->fetch()) !== false) {
			if (!is_array($row)) {
				throw new UnexpectedValueException('Invalid donation total row in ledger');
			}
			$name = $row['player_name'] ?? null;
			$amount = $row['amount'] ?? null;
			if (!is_string($name) || !is_int($amount)) {
				throw new UnexpectedValueException('Invalid donation total in ledger');
			}
			$totals[$name] = $amount;
		}
		return $totals;
	}

	public function record(string $requestId, string $playerName, int $amount) : bool {
		if ($requestId === '' || $playerName === '' || $amount <= 0) {
			throw new InvalidArgumentException('Donation request, player and amount must be valid');
		}
		$this->beginWrite();
		try {
			$find = $this->database->prepare('SELECT player_name, amount FROM processed_donations WHERE request_id = ?');
			$find->execute([$requestId]);
			$existing = $find->fetch();
			if ($existing !== false) {
				if (!is_array($existing)) {
					throw new UnexpectedValueException('Invalid processed donation row in ledger');
				}
				if (($existing['player_name'] ?? null) !== $playerName || ($existing['amount'] ?? null) !== $amount) {
					throw new UnexpectedValueException('Donation request ID was reused with different details');
				}
				$this->database->exec('COMMIT');
				return false;
			}

			$findTotal = $this->database->prepare('SELECT amount FROM donation_totals WHERE player_name = ?');
			$findTotal->execute([$playerName]);
			$current = $findTotal->fetchColumn();
			if ($current !== false && !is_int($current)) {
				throw new UnexpectedValueException('Invalid donation total in ledger');
			}
			$previous = $current === false ? 0 : $current;
			if ($previous > PHP_INT_MAX - $amount) {
				throw new OverflowException('Donation total exceeds the integer range');
			}

			$insert = $this->database->prepare('INSERT INTO processed_donations (request_id, player_name, amount, processed_at) VALUES (?, ?, ?, ?)');
			$insert->execute([$requestId, $playerName, $amount, time()]);
			$update = $this->database->prepare('INSERT INTO donation_totals (player_name, amount) VALUES (?, ?) ON CONFLICT(player_name) DO UPDATE SET amount = excluded.amount');
			$update->execute([$playerName, $previous + $amount]);
			$this->database->exec('COMMIT');
			return true;
		} catch (Throwable $error) {
			if ($this->database->inTransaction()) {
				$this->database->rollBack();
			}
			throw $error;
		}
	}

	/** @param array<array-key, mixed> $legacyTotals */
	private function importLegacyTotals(array $legacyTotals) : void {
		$this->beginWrite();
		try {
			$marker = $this->database->query("SELECT value FROM ledger_metadata WHERE key = 'legacy_imported'");
			if ($marker === false) {
				throw new RuntimeException('Could not read ledger metadata');
			}
			if ($marker->fetchColumn() === false) {
				$insert = $this->database->prepare('INSERT INTO donation_totals (player_name, amount) VALUES (?, ?)');
				foreach ($legacyTotals as $playerName => $amount) {
					if ((string) $playerName === '' || !is_int($amount) || $amount < 0) {
						throw new UnexpectedValueException('Invalid legacy donation total. Preserve naptheData.yml for review');
					}
					$insert->execute([(string) $playerName, $amount]);
				}
				$this->database->exec("INSERT INTO ledger_metadata (key, value) VALUES ('legacy_imported', '1')");
			}
			$this->database->exec('COMMIT');
		} catch (Throwable $error) {
			if ($this->database->inTransaction()) {
				$this->database->rollBack();
			}
			throw $error;
		}
	}

	private function beginWrite() : void {
		$this->database->exec('BEGIN IMMEDIATE');
	}
}
