DROP TABLE IF EXISTS cors_proxy_rate_limiting;
CREATE TABLE cors_proxy_rate_limiting (
	remote_addr INET6 PRIMARY KEY,
	capacity SMALLINT UNSIGNED NOT NULL,
	fill_rate_per_minute SMALLINT UNSIGNED NOT NULL,
	tokens SMALLINT UNSIGNED NOT NULL,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	-- @TODO: Make this per-domain
	CHECK (capacity > 0 AND tokens < capacity),
	INDEX (updated_at)
);
