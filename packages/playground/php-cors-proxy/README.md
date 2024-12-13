## PHP CORS Proxy

A PHP CORS proxy need to integrate git clone via fetch().

### Configuration

In order to avoid running a CORS proxy that is easy to abuse by default, the proxy requires administrators to explicitly declare what to do about rate-limiting, by doing one of the following:

-   Provide a rate-limiting function `playground_cors_proxy_maybe_rate_limit()`.
-   Define a truthy `PLAYGROUND_CORS_PROXY_DISABLE_RATE_LIMIT` to explicitly disable rate-limiting.

These can be provided in an optional `cors-proxy-config.php` file in the same directory as `cors-proxy.php` or in a PHP file that is loaded before all PHP execution via the [`auto_prepend_file`](https://www.php.net/manual/en/ini.core.php#ini.auto-prepend-file) php.ini option.

### Usage

Request http://127.0.0.1:5263/proxy.php/https://w.org/?test=1 to get the response from https://w.org/?test=1 plus the CORS headers.

### Development and testing

-   Run `dev.sh` to start a local server, then go to http://127.0.0.1:5263/proxy.php/https://w.org/ and confirm it worked.
-   Run `test.sh` to run PHPUnit tests, confirm they all pass.
-   Run `test-watch.sh` to run PHPUnit tests in watch mode.

### Design decisions

-   Stream data both ways, don't buffer.
-   Don't pass auth headers in either direction.
    - Opt-in for request headers possible using `X-Cors-Proxy-Allowed-Request-Headers`.
-   Refuse to request private IPs.
-   Refuse to process non-GET non-POST non-OPTIONS requests.
-   Refuse to process POST request body larger than, say, 100KB.
-   Refuse to process responses larger than, say, 100MB.
