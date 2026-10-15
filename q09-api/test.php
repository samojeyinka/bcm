<?php
// ============== run: php test.php ==============
declare(strict_types=1);
error_reporting(E_ALL); set_error_handler(fn($n,$s)=>throw new ErrorException($s));
require __DIR__ . '/subscribe.php';

$fails = 0;
function check(string $n, bool $ok, string $d = ''): void { global $fails; if (!$ok) $fails++; printf("[%s] %s%s\n", $ok?'PASS':'FAIL', $n, $ok?'':"  -> $d"); }

// ============== mock newsletter service: honours Idempotency-Key like Stripe-style APIs ==============
// $script is the behaviour for each call in turn: 'ok', 'timeout_after_accept',
// 'timeout_before_accept', 429, 503 or 422
function mock(array $script, array &$records, array &$calls, bool $idempotent = true): callable {
	return function (string $email, string $key) use (&$script, &$records, &$calls, $idempotent) {
		$calls[] = $email; $step = array_shift($script) ?? 'ok';
		$id = $idempotent ? $key : uniqid();
		$create = function() use (&$records, $id, $email) { $records[$id] ??= $email; };
		switch ($step) {
			case 'ok': $create(); return ['code' => 200];
			// ============== the service stored the signup, then the response was lost ==============
			case 'timeout_after_accept': $create(); throw new ApiTimeout();
			case 'timeout_before_accept': throw new ApiTimeout();
			case 429: return ['code' => 429, 'retry_after' => 3];
			default: return ['code' => (int)$step];
		}
	};
}
$noSleep = function(float $s) use (&$slept) { $slept[] = $s; };

// ============== 1. a bad address: no API call, nothing stored ==============
$store = []; $rec = []; $calls = []; $slept = [];
$r = subscribe('not-an-email', $store, mock([], $rec, $calls), 3, $noSleep);
check('invalid email: status invalid', $r['status'] === 'invalid');
check('invalid email: no API call, nothing stored', $calls === [] && $store === []);

// ============== 2. timeout AFTER the service accepted, then a retry: still exactly one record ==============
$store = []; $rec = []; $calls = []; $slept = [];
$r = subscribe('Ada@Example.com ', $store, mock(['timeout_after_accept', 'ok'], $rec, $calls), 3, $noSleep);
check('accepted-then-timeout: ends confirmed', $r['status'] === 'confirmed');
check('accepted-then-timeout: API called twice, ONE record at the service', count($calls) === 2 && count($rec) === 1, json_encode($rec));
check('accepted-then-timeout: both calls used the same normalised email', array_unique($calls) === ['ada@example.com']);
check('accepted-then-timeout: waited (backoff) before retry', count($slept) === 1 && $slept[0] >= 2);

// ============== 3. negative control: a service that ignores the key duplicates the signup ==============
$store = []; $rec = []; $calls = []; $slept = [];
subscribe('ada@example.com', $store, mock(['timeout_after_accept', 'ok'], $rec, $calls, false), 3, $noSleep);
check('control (service ignores key): 2 records -> why the key + check-before-retry matter', count($rec) === 2);

// ============== 4. rate limit: wait for Retry-After, then succeed ==============
$store = []; $rec = []; $calls = []; $slept = [];
$r = subscribe('lin@example.com', $store, mock([429, 'ok'], $rec, $calls), 3, $noSleep);
check('429: slept exactly Retry-After (3s) then confirmed', $slept === [3.0] && $r['status'] === 'confirmed' && count($rec) === 1, json_encode($slept));

// ============== 5. the service keeps failing: not lost, not duplicated, left pending for a queue ==============
$store = []; $rec = []; $calls = []; $slept = [];
$r = subscribe('sam@example.com', $store, mock([503, 503, 503, 'ok'], $rec, $calls), 3, $noSleep);
check('persistent 503: queued, capped at 3 attempts', $r['status'] === 'queued' && count($calls) === 3);
$k = $r['key'];
check('persistent 503: record kept as pending (not lost)', $store[$k]['status'] === 'pending' && $rec === []);
$r = subscribe('sam@example.com', $store, mock(['ok'], $rec, $calls), 6, $noSleep);   // ============== the queue worker coming back later ==============
check('later retry succeeds: confirmed, one record', $r['status'] === 'confirmed' && count($rec) === 1 && count($store) === 1);

// ============== 6. the service rejects the address (422): no retries ==============
$store = []; $rec = []; $calls = []; $slept = [];
$r = subscribe('odd@example.com', $store, mock([422, 'ok'], $rec, $calls), 3, $noSleep);
check('422: invalid, exactly one call, status rejected', $r['status'] === 'invalid' && count($calls) === 1 && array_values($store)[0]['status'] === 'rejected');

// ============== 7. a double submit after success makes no new API call ==============
$store = []; $rec = []; $calls = []; $slept = [];
$api = mock(['ok'], $rec, $calls);
subscribe('dup@example.com', $store, $api, 3, $noSleep);
subscribe('DUP@example.com', $store, $api, 3, $noSleep);
check('double submit (different case): 1 API call, 1 record', count($calls) === 1 && count($rec) === 1);

echo $fails ? "\n$fails FAILED\n" : "\nAll checks passed\n"; exit($fails ? 1 : 0);
