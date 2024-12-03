<?php

class PlaygroundCorsProxyTokenBucketConfig {
	public function __construct(
		public int $capacity,
		public int $fill_rate_per_minute,
	) {}
}

function playground_cors_proxy_get_token_bucket_config($remote_proxy_type) {
	// @TODO: Support custom HTTP header to select test bucket config.

	// NOTE: 2024-10-09: These are just guesses for reasonable bucket configs.
	// They are not based on any real-world data. Please adjust them as needed.
	switch ($remote_proxy_type) {
		case 'VPN':
		case 'TOR':
		case 'PUB':
		case 'WEB':
			return new PlaygroundCorsProxyTokenBucketConfig(
				capacity: 1000,
				fill_rate_per_minute: 100,
			);
		case 'SES':
			// If it's even possible to receive CORS proxy requests from
			// search engine bots, let's refuse them because they don't
			// need this service.
			return new PlaygroundCorsProxyTokenBucketConfig(
				capacity: 0,
				fill_rate_per_minute: 0,
			);
		default:
			// NOTE: 2024-10-14: Initially, let's make the default rate limit
			// the same as the limit for proxy IPs because some working on
			// Playground are typically proxied and may not otherwise notice
			// a problem with the default rate limit.
			return new PlaygroundCorsProxyTokenBucketConfig(
				capacity: 1000,
				fill_rate_per_minute: 100,
			);
	}
}

class PlaygroundCorsProxyTokenBucket {
	private $dbh = null;
	private $connected_to_db = false;

	public function __construct() {
		$this->dbh = mysqli_init();
		if ($this->dbh) {
			$this->connected_to_db = mysqli_real_connect(
				$this->dbh,
				DB_HOST,
				DB_USER,
				DB_PASSWORD,
				DB_NAME
			);
		}
	}

	public function dispose() {
		if ($this->dbh && $this->connected_to_db) {
			mysqli_close($this->dbh);
		}
	}

	public function obtain_token($remote_ip, $bucket_config) {
		if (!$this->connected_to_db) {
			return false;
		}

		$cleanup_query = <<<'SQL'
			DELETE FROM cors_proxy_rate_limiting
				WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
			SQL;
		if (mysqli_query($this->dbh, $cleanup_query) === false) {
			error_log(
				'Failed to clean up old token buckets: ' .
				mysqli_error($this->dbh)
			);
			return false;
		}

		// Abort if tokens are never available.
		if ($bucket_config->capacity <= 0) {
			return false;
		}

		/**
		 * Aggregate addresses into /64 subnets.
		 * This prevents a person with an entire /64 subnet from
		 * exhausting the storage or getting more than their fair
		 * share of the tokens.
		 */
		$remote_subnet = playground_ip_to_a_64_subnet($remote_ip);

		$token_query = <<<'SQL'
			INSERT INTO cors_proxy_rate_limiting (
				remote_addr,
				capacity,
				fill_rate_per_minute,
				tokens
			)
			WITH
				config AS (
					SELECT
						? AS remote_addr,
						? AS capacity,
						? AS fill_rate_per_minute
				),
				bucket AS (
					SELECT
						remote_addr,
						tokens AS previous_tokens,
						updated_at AS previous_updated_at,
						-- Ensure we don't exceed the capacity.
						LEAST(
							config.capacity,
							tokens + FLOOR(
								config.fill_rate_per_minute
								* TIMESTAMPDIFF(SECOND, updated_at, NOW())
								/ 60
							)
						) AS available_tokens
					FROM cors_proxy_rate_limiting INNER JOIN config USING (remote_addr)
				)
			SELECT
				config.remote_addr,
				config.capacity,
				config.fill_rate_per_minute,
				-- Make sure we stay within bounds.
				GREATEST(
					0,
					COALESCE(bucket.available_tokens, config.capacity) - 1
				) AS tokens
			FROM config LEFT OUTER JOIN bucket USING (remote_addr)
			ON DUPLICATE KEY UPDATE
				capacity = VALUES(capacity),
				fill_rate_per_minute = VALUES(fill_rate_per_minute),
				tokens = VALUES(tokens),
				-- Force a row update by updating the timestamp when we've consumed a token,
				-- unless the number of available tokens remains at zero.
				-- @TODO Set last updated to use the time of the last possible token. Otherwise, the aggregate error may be noticeable.
				updated_at = IF(
					bucket.available_tokens = 0 AND bucket.previous_tokens = 0,
					bucket.previous_updated_at,
					NOW()
				)
			SQL;

		$token_statement = mysqli_prepare($this->dbh, $token_query);
		mysqli_stmt_bind_param(
			$token_statement,
			'sii',
			$remote_subnet,
			$bucket_config->capacity,
			$bucket_config->fill_rate_per_minute
		);

		if (
			mysqli_stmt_execute($token_statement) &&
			mysqli_stmt_affected_rows($token_statement) > 0
		) {
			return true;
		}

		return false;
	}

}

function playground_cors_proxy_maybe_rate_limit() {
	// IP metadata from WP Cloud.
	$remote_proxy_type = $_SERVER['HTTP_X_IP_PROXY_TYPE'] ?? 'UNK';
	$bucket_config = playground_cors_proxy_get_token_bucket_config(
		$remote_proxy_type
	);

	// We cannot trust the X-Forwarded-For header everywhere,
	// but we are trusting WP Cloud to provide an accurate value for it.
	$remote_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

	if (filter_var($remote_ip, FILTER_VALIDATE_IP) === false) {
		// Validate the IP address before using it as a key in the database.
		error_log('Invalid IP address: ' . var_export($remote_ip, true));
		return false;
	}

	$token_bucket = new PlaygroundCorsProxyTokenBucket();
	try {
		if (!$token_bucket->obtain_token($remote_ip, $bucket_config)) {
			http_response_code(429);
			echo "Rate limit exceeded";
			exit;
		}
	} finally {
		$token_bucket->dispose();
	}
}

/**
 * Converts the IP address to a key used for rate limiting.
 * It groups addresses into buckets of size /64.
 *
 * @return string The encoded IPv6 address or null if the input is not a valid IP address.
 */
function playground_ip_to_a_64_subnet(string $ip_v4_or_v6): string {
	$ipv6_remote_ip = $ip_v4_or_v6;
	if (filter_var($ip_v4_or_v6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		/**
		 * Convert IPv4 to IPv6 mapped address for storage.
		 * Do not group these addresses into buckets since
		 * the number of IPv4 addresses is less than the number
		 * of IPv6 addresses.
		 */
		$ipv6_remote_ip = '::ffff:' . $ip_v4_or_v6;
	} else {
		/**
		 * Zero out the last 64 bits of the IPv6 address for storage.
		 * This means we're only considering the first 64 bits of the
		 * address for rate limiting. This way, a person with an entire
		 * /64 subnet cannot get more than their fair share of the
		 * tokens.
		 *
		 * NOTE: This measure may be a bit heavy-handed to start with,
		 * but we will not know until we are actually seeing requests
		 * from IPv6 requests in the wild. If individual users are
		 * experiencing too much rate-limiting, we can consider
		 * limiting individual addresses first and only addressing
		 * entire blocks once an IPv6 block has a number of IPs
		 * that are hitting rate limits.
		 */
		$ipv6_block = playground_get_ipv6_block($ipv6_remote_ip, 64);
		if ($ipv6_block === null) {
			error_log('Failed to get IPv6 block for ' . $ip_v4_or_v6);
			// Use the original IP address as a fallback when the block
			// cannot be determined.
			return $ip_v4_or_v6;
		}
		$ipv6_remote_ip = playground_encode_ipv6($ipv6_block);
	}
	return $ipv6_remote_ip;
}

/**
 * Returns the /64 subnet of the given IPv6 address.
 */
function playground_get_ipv6_block(string $ipv6_remote_ip, int $block_size=64): ?string {
	$ip = inet_pton($ipv6_remote_ip);
	// $ip is a binary string of length 16, each bit represents a bit
	// of the ipv6 address.
	if ($ip === false) {
		return null;
	}

	if ($block_size % 8 !== 0) {
		// We're using a naive substr-based approach that reasons about
		// groups of 8 bits (characters) and not separately about each bit.
		// This approach can only support block sizes that are multiples
		// of 8.
		throw new Exception('Block size must be a multiple of 8.');
	}

	$max_block_size = 128;
	if ($block_size > $max_block_size) {
		throw new Exception('Block size must be less than or equal to 128.');
	}

	$subnet_length = $block_size / 8;
	$requested_block = substr($ip, 0, $subnet_length);
	$backfill_zeros = str_repeat(chr(0), 16 - $subnet_length);
	return $requested_block . $backfill_zeros;
}

function playground_encode_ipv6(string $ipv6): string {
	$hex_string = bin2hex($ipv6);
	$hex_string_length = strlen($hex_string);

	// Split the hex string into 4 groups of 4 characters.
	$groups = [];
	for ($i = 0; $i < $hex_string_length; $i += 4) {
		$groups[] = substr($hex_string, $i, 4);
	}
	return strtoupper(implode(':', $groups));
}
