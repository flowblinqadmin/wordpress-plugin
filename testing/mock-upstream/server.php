<?php
/**
 * Mock upstream server simulating geo.flowblinq.com
 *
 * Run via: php -S 0.0.0.0:8080 server.php
 *
 * Supports:
 *   GET  /api/serve/{slug}/llms.txt
 *   GET  /api/serve/{slug}/llms-full.txt
 *   GET  /api/serve/{slug}/business.json
 *   GET  /api/serve/{slug}/schema.json
 *   POST /api/oauth/token
 *   POST /api/v1/audit
 *   GET  /api/v1/audit/{id}
 *   POST /api/v1/audit/{id}/verify
 *   GET  /__log     (returns request log)
 *   GET  /__reset_log (clears log + poll counts)
 */

define( 'LOG_FILE',        '/tmp/mock-requests.log' );
define( 'POLL_COUNT_FILE', '/tmp/mock-poll-counts.json' );
define( 'FIXTURES_DIR',    __DIR__ . '/fixtures' );

// ----- Logging ---------------------------------------------------------------

function log_request( string $method, string $uri ): void {
    $line = '[' . gmdate( 'c' ) . '] ' . $method . ' ' . $uri . PHP_EOL;
    file_put_contents( LOG_FILE, $line, FILE_APPEND | LOCK_EX );
}

function get_log(): string {
    return file_exists( LOG_FILE ) ? file_get_contents( LOG_FILE ) : '';
}

function reset_log(): void {
    file_put_contents( LOG_FILE, '' );
    file_put_contents( POLL_COUNT_FILE, '{}' );
}

// ----- Poll counter ----------------------------------------------------------

function increment_poll_count( string $audit_id ): int {
    $counts = file_exists( POLL_COUNT_FILE )
        ? ( json_decode( file_get_contents( POLL_COUNT_FILE ), true ) ?? [] )
        : [];
    $counts[ $audit_id ] = ( $counts[ $audit_id ] ?? 0 ) + 1;
    file_put_contents( POLL_COUNT_FILE, json_encode( $counts ), LOCK_EX );
    return $counts[ $audit_id ];
}

// ----- Response helpers -------------------------------------------------------

function respond( int $code, string $body, string $content_type = 'application/json' ): void {
    http_response_code( $code );
    header( 'Content-Type: ' . $content_type );
    echo $body;
    exit;
}

function respond_json( int $code, array $data ): void {
    respond( $code, json_encode( $data ) );
}

function respond_file( string $path, string $content_type ): void {
    if ( ! file_exists( $path ) ) {
        respond_json( 404, [ 'error' => 'fixture_not_found', 'path' => $path ] );
    }
    respond( 200, file_get_contents( $path ), $content_type );
}

// ----- Router ----------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];
// Strip query string
$path = parse_url( $uri, PHP_URL_PATH );

// Log every request
log_request( $method, $uri );

// ---- Admin routes -----------------------------------------------------------

// GET /__log — return log
if ( $method === 'GET' && $path === '/__log' ) {
    respond( 200, get_log(), 'text/plain' );
}

// GET /__reset_log — reset log and poll counts
if ( $method === 'GET' && $path === '/__reset_log' ) {
    reset_log();
    respond_json( 200, [ 'ok' => true ] );
}

// ---- OAuth ------------------------------------------------------------------

// POST /api/oauth/token
if ( $method === 'POST' && $path === '/api/oauth/token' ) {
    $body = json_decode( file_get_contents( 'php://input' ), true ) ?? [];

    $client_id     = $body['client_id']     ?? '';
    $client_secret = $body['client_secret'] ?? '';

    if ( empty( $client_id ) || empty( $client_secret ) ) {
        respond_json( 400, [ 'error' => 'missing_credentials' ] );
    }

    if ( $client_id === 'invalid' || $client_secret === 'invalid' ) {
        respond_json( 401, [ 'error' => 'invalid_client' ] );
    }

    respond_json( 200, [
        'access_token' => 'mock-token-' . bin2hex( random_bytes( 8 ) ),
        'token_type'   => 'Bearer',
        'expires_in'   => 3600,
    ] );
}

// ---- Audit API --------------------------------------------------------------

// POST /api/v1/audit
if ( $method === 'POST' && $path === '/api/v1/audit' ) {
    // Error slug in URL body (error500 trigger)
    $input = file_get_contents( 'php://input' );
    if ( strpos( $input, 'error500' ) !== false ) {
        respond_json( 500, [ 'error' => 'audit_submit_failed' ] );
    }

    respond_json( 200, [
        'audit_id' => 'audit-' . bin2hex( random_bytes( 8 ) ),
        'slug'     => 'test-site-123',
        'status'   => 'pending',
    ] );
}

// GET /api/v1/audit/{id} — poll
if ( $method === 'GET' && preg_match( '#^/api/v1/audit/([^/]+)$#', $path, $m ) ) {
    $audit_id = $m[1];

    if ( $audit_id === 'error-audit' ) {
        respond_json( 500, [ 'error' => 'internal_server_error' ] );
    }

    $count = increment_poll_count( $audit_id );

    if ( $count < 3 ) {
        respond_json( 200, [
            'status'   => 'pending',
            'progress' => 33 * $count,
        ] );
    } else {
        respond_json( 200, [
            'status'    => 'complete',
            'scorecard' => [
                'overallScore' => 75,
                'pillars'      => [],
            ],
        ] );
    }
}

// POST /api/v1/audit/{id}/verify
if ( $method === 'POST' && preg_match( '#^/api/v1/audit/([^/]+)/verify$#', $path, $m ) ) {
    respond_json( 200, [
        'status'    => 'complete',
        'scorecard' => [
            'overallScore' => 82,
            'pillars'      => [],
        ],
    ] );
}

// ---- Serve routes -----------------------------------------------------------

// GET /api/serve/{slug}/{file}
if ( $method === 'GET' && preg_match( '#^/api/serve/([^/]+)/(.+)$#', $path, $m ) ) {
    $slug     = rawurldecode( $m[1] );
    $filename = $m[2];

    // Special slug behaviors
    switch ( $slug ) {
        case 'error-500':
            respond_json( 500, [ 'error' => 'internal_server_error' ] );
            break;

        case 'timeout':
            sleep( 5 );
            break; // fall through to normal response after sleep

        case 'oversized':
            respond( 200, str_repeat( 'x', 513 * 1024 ), 'text/plain' );
            break;

        case 'malformed':
            if ( $filename === 'schema.json' ) {
                respond( 200, 'not valid json at all', 'application/json' );
            }
            break;

        case 'xss-payload':
            if ( $filename === 'schema.json' ) {
                $xss = [
                    [
                        '@context' => 'https://schema.org',
                        '@type'    => 'LocalBusiness',
                        'name'     => '</script><script>alert(1)</script>',
                        'url'      => 'http://example.com',
                    ],
                ];
                respond( 200, json_encode( $xss ), 'application/json' );
            }
            break;

        case 'not-array':
            if ( $filename === 'schema.json' ) {
                respond( 200, '{"@context":"https://schema.org","@type":"WebSite"}', 'application/json' );
            }
            break;
    }

    // Normal serve — match filename to fixture
    $file_map = [
        'llms.txt'       => [ FIXTURES_DIR . '/llms.txt',      'text/plain' ],
        'llms-full.txt'  => [ FIXTURES_DIR . '/llms-full.txt', 'text/plain' ],
        'business.json'  => [ FIXTURES_DIR . '/business.json', 'application/json' ],
        'schema.json'    => [ FIXTURES_DIR . '/schema.json',   'application/json' ],
    ];

    if ( isset( $file_map[ $filename ] ) ) {
        [ $filepath, $content_type ] = $file_map[ $filename ];
        respond_file( $filepath, $content_type );
    }

    respond_json( 404, [ 'error' => 'not_found', 'file' => $filename ] );
}

// ---- 404 fallback -----------------------------------------------------------

respond_json( 404, [ 'error' => 'route_not_found', 'path' => $path ] );
