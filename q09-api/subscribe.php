<?php
declare(strict_types=1);

class ApiTimeout extends RuntimeException {}

// ============== subscribe: sign up an email without losing or duplicating it ==============
// $store  local durable record (a DB table in real life), keyed by idempotency key
// $api    fn(string $email, string $idempotencyKey): array{code:int, retry_after?:int}
//         throws ApiTimeout when no response arrives
// $sleep  injected so the tests do not really wait
// returns array{status:string, key?:string} — invalid, confirmed or queued
function subscribe( string $raw, array &$store, callable $api, int $max = 3, ?callable $sleep = null ): array {
	$sleep ??= fn( float $s ) => usleep( (int) ( $s * 1e6 ) );

	$email = strtolower( trim( $raw ) );
	if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		// ============== a bad address is never sent, stored or retried ==============
		return [ 'status' => 'invalid' ];
	}

	// ============== same person, same key on every retry ==============
	$key = hash( 'sha256', 'newsletter-signup:' . $email );
	if ( ( $store[ $key ]['status'] ?? '' ) === 'confirmed' ) {
		// ============== already done, so no second API call ==============
		return [ 'status' => 'confirmed', 'key' => $key ];
	}
	// ============== the record is saved BEFORE the API is called, so a crash mid-call cannot lose the signup ==============
	$store[ $key ] ??= [ 'email' => $email, 'status' => 'pending', 'attempts' => 0 ];

	while ( $store[ $key ]['attempts'] < $max ) {
		$attempt = ++$store[ $key ]['attempts'];
		try {
			$r = $api( $email, $key );
		} catch ( ApiTimeout ) {
			// ============== a timeout is an unknown outcome, not a failure: it may already have been accepted ==============
			$r = [ 'code' => 0 ];
		}
		$code = $r['code'];

		if ( ( $code >= 200 && $code < 300 ) || $code === 409 ) {
			// ============== 409 means already subscribed, which counts as success ==============
			$store[ $key ]['status'] = 'confirmed';
			return [ 'status' => 'confirmed', 'key' => $key ];
		}
		if ( $code === 400 || $code === 422 ) {
			// ============== the service itself says the address is bad: stop for good ==============
			$store[ $key ]['status'] = 'rejected';
			return [ 'status' => 'invalid', 'key' => $key ];
		}
		// ============== timeout, 429 or 5xx: wait, then retry with the SAME key ==============
		if ( $attempt < $max ) {
			// ============== 429 obeys Retry-After; everything else gets backoff plus a little jitter ==============
			$sleep( $code === 429 ? (float) ( $r['retry_after'] ?? 2 ** $attempt )
			                      : 2 ** $attempt + random_int( 0, 500 ) / 1000 );
		}
	}
	// ============== out of attempts: left pending so a cron or queue can pick it up later; never dropped ==============
	return [ 'status' => 'queued', 'key' => $key ];
}
