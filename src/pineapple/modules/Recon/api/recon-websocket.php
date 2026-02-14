<?php
/**
 * recon-websocket.php - Minimal WebSocket broadcaster. By DSR! @xchwarze
 * Run: php recon-websocket.php /path/to/db.sqlite 123
 */

declare(strict_types=1);

const LOCKFILE  = '/tmp/reconpp.lock';
const TOKENFILE = '/tmp/reconpp.token';
const TOKENLEN  = 64;
const WS_MAGIC  = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

const POLL_INTERVAL_SEC = 3;
const WS_LISTEN_HOST    = '0.0.0.0';
const WS_LISTEN_PORT    = 1337;
const SCAN_STATUS_CHECK_EVERY = 3;

final class EncryptionFlags {
    public const WPA                    = 0x01;
    public const WPA2                   = 0x02;
    public const WEP                    = 0x04;
    public const WPA_PAIRWISE_WEP40     = 0x08;
    public const WPA_PAIRWISE_WEP104    = 0x10;
    public const WPA_PAIRWISE_TKIP      = 0x20;
    public const WPA_PAIRWISE_CCMP      = 0x40;
    public const WPA2_PAIRWISE_WEP40    = 0x80;
    public const WPA2_PAIRWISE_WEP104   = 0x100;
    public const WPA2_PAIRWISE_TKIP     = 0x200;
    public const WPA2_PAIRWISE_CCMP     = 0x400;
    public const WPA_AKM_PSK            = 0x800;
    public const WPA_AKM_ENTERPRISE     = 0x1000;
    public const WPA_AKM_ENTERPRISE_FT  = 0x2000;
    public const WPA2_AKM_PSK           = 0x4000;
    public const WPA2_AKM_ENTERPRISE    = 0x8000;
    public const WPA2_AKM_ENTERPRISE_FT = 0x10000;
    public const WPA_GROUP_WEP40        = 0x20000;
    public const WPA_GROUP_WEP104       = 0x40000;
    public const WPA_GROUP_TKIP         = 0x80000;
    public const WPA_GROUP_CCMP         = 0x100000;
    public const WPA2_GROUP_WEP40       = 0x200000;
    public const WPA2_GROUP_WEP104      = 0x400000;
    public const WPA2_GROUP_TKIP        = 0x800000;
    public const WPA2_GROUP_CCMP        = 0x1000000;
}

/**
 * Simple CLI logger
 */
function logMsg(string $message, bool $isError = false): void {
    $stream = $isError ? STDERR : STDOUT;
    fwrite($stream, $message . PHP_EOL);
}

final class TokenAuth {
    public static function ensureTokenFile(): void {
        if (is_file(TOKENFILE)) {
            return;
        }
        $token = self::generateToken(TOKENLEN);
        file_put_contents(TOKENFILE, $token, LOCK_EX);
        chmod(TOKENFILE, 0600);
    }

    public static function readToken(): string {
        return trim((string)@file_get_contents(TOKENFILE));
    }

    public static function removeTokenFile(): void {
        if (is_file(TOKENFILE)) {
            @unlink(TOKENFILE);
        }
    }

    public static function verify(string $candidate): bool {
        $expected = self::readToken();
        if ($expected === '') return false;
        return hash_equals($expected, $candidate);
    }

    private static function generateToken(int $length): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}

final class LockFile {
    /** @var resource|null */
    private $handle;

    public function acquire(): bool {
        $this->handle = fopen(LOCKFILE, 'c');
        if (!$this->handle) return false;
        // Non-blocking exclusive lock
        if (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            fclose($this->handle);
            $this->handle = null;
            return false;
        }
        ftruncate($this->handle, 0);
        fwrite($this->handle, (string)getmypid());
        fflush($this->handle);
        return true;
    }

    public function release(): void {
        if ($this->handle) {
            @flock($this->handle, LOCK_UN);
            @fclose($this->handle);
            $this->handle = null;
        }
        if (is_file(LOCKFILE)) {
            @unlink(LOCKFILE);
        }
    }
}

final class WsClient {
    /** @var resource */
    public $socket;
    /** @var bool */
    public $handshakeDone = false;
    /** @var string */
    public $readBuffer = '';
    /** @var string */
    public $remoteAddr = '';

    public function __construct($socket, string $remoteAddr) {
        $this->socket = $socket;
        $this->remoteAddr = $remoteAddr;
    }
}

final class WsServer {
    /** @var resource */
    private $listenSocket;
    /** @var array<int, WsClient> */
    private $clients = [];
    /** @var bool */
    private $forceBroadcast = false;

    public function __construct(string $host, int $port) {
        $errno = 0; $errstr = '';
        $this->listenSocket = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (!$this->listenSocket) {
            throw new RuntimeException("WS listen failed: {$errstr} ({$errno})");
        }
        stream_set_blocking($this->listenSocket, false);
    }

    /**
     * Pump accept + handshake processing. Keep it cheap; call frequently.
     */
    public function tick(): void {
        $read = [$this->listenSocket];
        foreach ($this->clients as $client) {
            $read[] = $client->socket;
        }
        $write = null; $except = null;

        if (stream_select($read, $write, $except, 0, 200000) === false) {
            return;
        }

        foreach ($read as $sock) {
            if ($sock === $this->listenSocket) {
                $this->acceptClient();
                continue;
            }
            $this->readFromClient((int)$sock);
        }
    }

    public function broadcastJson(array $payload): void {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) return;

        // Build the WebSocket frame once per broadcast (perf: avoid per-client frame building).
        $frame = $this->buildTextFrame($json);
        if ($frame === null) return;

        foreach ($this->clients as $id => $client) {
            if (!$client->handshakeDone) continue;
            $ok = $this->writeAll($client->socket, $frame);
            if (!$ok) {
                $this->dropClient($id);
            }
        }
    }

    public function hasClients(): bool {
        return !empty($this->clients);
    }

    public function shouldForceBroadcast(): bool {
        if (!$this->forceBroadcast) return false;
        $this->forceBroadcast = false;
        return true;
    }

    public function close(): void {
        foreach ($this->clients as $id => $client) {
            @fclose($client->socket);
            unset($this->clients[$id]);
        }
        @fclose($this->listenSocket);
    }

    private function acceptClient(): void {
        $peerName = '';
        $conn = @stream_socket_accept($this->listenSocket, 0, $peerName);
        if (!$conn) return;

        stream_set_blocking($conn, false);
        $id = (int)$conn;

        logMsg("[WS] Connection attempt id={$id} from={$peerName}");
        $this->clients[$id] = new WsClient($conn, $peerName ?: 'unknown');
    }

    private function readFromClient(int $clientId): void {
        if (!isset($this->clients[$clientId])) return;
        $client = $this->clients[$clientId];

        $chunk = @fread($client->socket, 8192);
        if ($chunk === '' || $chunk === false) {
            // Non-blocking sockets may return '' when no data is available yet.
            // Only drop on actual EOF.
            if (feof($client->socket)) {
                $this->dropClient($clientId);
            }
            return;
        }

        if (!$client->handshakeDone) {
            $client->readBuffer .= $chunk;
            if (strpos($client->readBuffer, "\r\n\r\n") === false) {
                return;
            }

            $request = $client->readBuffer;
            $client->readBuffer = '';
            if (!$this->handleHandshake($client, $request)) {
                $this->dropClient($clientId);
                return;
            }
            $client->handshakeDone = true;
            $this->forceBroadcast = true;
            logMsg("[WS] Connected OK from={$client->remoteAddr}");
            return;
        }

        // For this broadcaster use-case, we ignore inbound messages after handshake.
        // Optionally: implement ping/pong consumption here if you expect it.
    }

    private function handleHandshake(WsClient $client, string $rawRequest): bool {
        logMsg("[WS] Handshake start from={$client->remoteAddr}");
        $lines = preg_split("/\r\n/", $rawRequest);
        if (!$lines || count($lines) < 2) return false;

        $requestLine = $lines[0];
        if (!preg_match('#^GET\s+(\S+)\s+HTTP/1\.[01]$#', $requestLine, $m)) {
            return false;
        }

        $uri = $m[1];
        $headers = $this->parseHeaders(array_slice($lines, 1));

        if (($headers['upgrade'] ?? '') !== 'websocket') return false;
        
        $connection = strtolower($headers['connection'] ?? '');
        if (strpos($connection, 'upgrade') === false) return false;

        if (empty($headers['sec-websocket-key'])) return false;

        $token = $this->extractAuthToken($uri);
        if ($token === '' || !TokenAuth::verify($token)) {
            logMsg("[WS] Handshake reject from={$client->remoteAddr}", true);
            $this->sendHttp($client->socket, 401, "Unauthorized");
            return false;
        }

        $key = trim($headers['sec-websocket-key']);
        $accept = base64_encode(sha1($key . WS_MAGIC, true));

        $response =
            "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

        return @fwrite($client->socket, $response) !== false;
    }

    /**
     * Build a server->client text frame (unmasked). Payload is truncated to 65535 bytes.
     *
     * @return string|null
     */
    private function buildTextFrame(string $payload): ?string {
        $len = strlen($payload);

        // Keep it simple: truncate extremely large payloads
        if ($len > 65535) {
            $payload = substr($payload, 0, 65535);
            $len = strlen($payload);
        }

        $frameHead = chr(0x81); // FIN + TEXT

        if ($len <= 125) {
            $frameHead .= chr($len);
            return $frameHead . $payload;
        }

        // 126 + 16-bit length
        $frameHead .= chr(126) . pack('n', $len);
        return $frameHead . $payload;
    }

    /**
     * Ensure full write (handles partial writes / backpressure).
     */
    private function writeAll($socket, string $data): bool {
        $length = strlen($data);
        $offset = 0;

        while ($offset < $length) {
            // Fast path: try full write first; fallback to substr only on partial.
            $chunk = ($offset === 0) ? $data : substr($data, $offset);
            $written = @fwrite($socket, $chunk);
            if ($written === false || $written === 0) {
                if (feof($socket)) {
                    logMsg("[WS] writeAll failed (EOF) offset={$offset}/{$length}", true);
                    return false;
                }

                // Small backoff to avoid busy loop
                usleep(1000);
                continue;
            }

            $offset += $written;
        }

        return true;
    }

    private function sendHttp($socket, int $status, string $message): void {
        $body = $message . "\n";
        $resp =
            "HTTP/1.1 {$status} {$message}\r\n" .
            "Content-Type: text/plain\r\n" .
            "Content-Length: " . strlen($body) . "\r\n\r\n" .
            $body;
        @fwrite($socket, $resp);
    }

    private function dropClient(int $id): void {
        if (!isset($this->clients[$id])) return;

        $addr = $this->clients[$id]->remoteAddr ?? 'unknown';
        logMsg("[WS] Dropping client id={$id} from={$addr}");

        @fclose($this->clients[$id]->socket);
        unset($this->clients[$id]);
    }

    /**
     * @param string[] $headerLines
     * @return array<string,string>
     */
    private function parseHeaders(array $headerLines): array {
        $headers = [];
        foreach ($headerLines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $pos = strpos($line, ':');
            if ($pos === false) continue;

            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $headers[$name] = $value;
        }

        return $headers;
    }

    private function extractAuthToken(string $uri): string {
        $parts = parse_url($uri);
        if (!$parts) return '';
        $query = $parts['query'] ?? '';
        if ($query === '') return '';
        parse_str($query, $params);
        $token = $params['authtoken'] ?? '';
        return is_string($token) ? $token : '';
    }
}

final class ReconRepository {
    /** @var SQLite3 */
    private $db;
    /** @var int */
    private $scanId;

    public function __construct(string $sqlitePath, int $scanId) {
        $this->db = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
        $this->scanId = $scanId;

        // Optimized for read-heavy polling on embedded systems
        $this->db->busyTimeout(1000);
        @$this->db->exec('PRAGMA query_only=ON;');
        @$this->db->exec('PRAGMA temp_store=MEMORY;');
    }

    /**
     * @return array{ap_list: array<int,array>, unassociated_clients: array<int,array>, out_of_range_clients: array<string,array>}
     */
    public function readSnapshot(): array {
        $unassociated = [];
        $outOfRange = [];
        $accessPointsByBssid = $this->fetchAccessPoints();
        $this->attachClients($accessPointsByBssid, $unassociated, $outOfRange);

        $apList = array_values($accessPointsByBssid);

        return [
            'ap_list' => $apList,
            'unassociated_clients' => $unassociated,
            'out_of_range_clients' => $outOfRange,
        ];
    }

    /**
     * Cheap change detector: returns a token based on MAX(last_seen) from aps/clients.
     * If unchanged, the snapshot is very likely unchanged too.
     */
    public function getChangeToken(): string {
        $query =
            "SELECT MAX(last_seen) AS total FROM (" .
            "  SELECT last_seen FROM aps WHERE scan_id = {$this->scanId} " .
            "  UNION ALL " .
            "  SELECT last_seen FROM clients WHERE scan_id = {$this->scanId} " .
            ")";
        $row = $this->db->querySingle($query, true);
        return isset($row['total']) ? (string)$row['total'] : '';
    }

    private function fetchMaxLastSeen(string $table): string {
        // Table name is internal/constant; not user-controlled.
        $query = "SELECT MAX(last_seen) AS m FROM {$table} WHERE scan_id = {$this->scanId}";
        $result = $this->db->querySingle($query, true);
        return isset($result['m']) ? (string)$result['m'] : '';
    }

    /**
     * @return array<string,array>
     */
    private function fetchAccessPoints(): array {
        $aps = [];
        $query = "SELECT ssid,bssid,encryption,channel,signal,wps,last_seen
                  FROM aps WHERE scan_id = {$this->scanId}";

        $result = $this->db->query($query);
        if (!$result) return $aps;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $bssid = (string)$row['bssid'];
            $aps[$bssid] = [
                'bssid' => $bssid,
                'ssid' => (string)$row['ssid'],
                'channel' => (int)$row['channel'],
                'encryption' => self::formatEncryption((int)$row['encryption']),
                'wps' => (int)$row['wps'],
                'lastSeen' => (string)$row['last_seen'],
                'power' => (int)$row['signal'],
                'clients' => [],
            ];
        }
        return $aps;
    }

    /**
     * @param array<string,array> $aps
     * @param array<int,array> $unassociated OUT
     * @param array<string,array> $outOfRange OUT
     */
    private function attachClients(array &$aps, array &$unassociated, array &$outOfRange): void {
        $unassociated = [];
        $outOfRange = [];

        $query = "SELECT mac,bssid,last_seen
                  FROM clients WHERE scan_id = {$this->scanId}";

        $result = $this->db->query($query);
        if (!$result) return;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $mac = (string)$row['mac'];
            $bssid = (string)$row['bssid'];
            $lastSeen = (string)$row['last_seen'];

            if ($bssid === "FF:FF:FF:FF:FF:FF") {
                $unassociated[] = ['mac' => $mac, 'lastSeen' => $lastSeen];
                continue;
            }

            if (isset($aps[$bssid])) {
                $aps[$bssid]['clients'][] = ['mac' => $mac, 'lastSeen' => $lastSeen];
                continue;
            }

            $outOfRange[$mac] = ['bssid' => $bssid, 'lastSeen' => $lastSeen];
        }
    }

    private static function formatEncryption(int $flags): string {
        if ($flags === 0) return 'Open';
        if ($flags & EncryptionFlags::WEP) return 'WEP';

        $out = '';

        $hasWpa  = (bool)($flags & EncryptionFlags::WPA);
        $hasWpa2 = (bool)($flags & EncryptionFlags::WPA2);

        if ($hasWpa && $hasWpa2) $out .= 'WPA Mixed ';
        elseif ($hasWpa)         $out .= 'WPA ';
        elseif ($hasWpa2)        $out .= 'WPA2 ';

        if (($flags & EncryptionFlags::WPA2_AKM_PSK) || ($flags & EncryptionFlags::WPA_AKM_PSK)) {
            $out .= 'PSK ';
        } elseif (($flags & EncryptionFlags::WPA2_AKM_ENTERPRISE) || ($flags & EncryptionFlags::WPA_AKM_ENTERPRISE)) {
            $out .= 'Enterprise ';
        } elseif (($flags & EncryptionFlags::WPA2_AKM_ENTERPRISE_FT) || ($flags & EncryptionFlags::WPA_AKM_ENTERPRISE_FT)) {
            $out .= 'Enterprise FT ';
        }

        $pairwise = [];

        if (($flags & EncryptionFlags::WPA2_PAIRWISE_CCMP) || ($flags & EncryptionFlags::WPA_PAIRWISE_CCMP)) $pairwise[] = 'CCMP';
        if (($flags & EncryptionFlags::WPA2_PAIRWISE_TKIP) || ($flags & EncryptionFlags::WPA_PAIRWISE_TKIP)) $pairwise[] = 'TKIP';

        // Bugfix from original comment: include WEP40/WEP104 properly (no early return).
        if (($flags & EncryptionFlags::WPA2_PAIRWISE_WEP40) || ($flags & EncryptionFlags::WPA_PAIRWISE_WEP40)) $pairwise[] = 'WEP40';
        if (($flags & EncryptionFlags::WPA2_PAIRWISE_WEP104) || ($flags & EncryptionFlags::WPA_PAIRWISE_WEP104)) $pairwise[] = 'WEP104';

        if (!$pairwise) return rtrim($out);
        return rtrim($out) . ' (' . implode(' ', $pairwise) . ')';
    }
}

final class PineapStatus {
    public static function isScanRunning(int $scanId): bool {
        $cmd = "/usr/bin/pineap /tmp/pineap.conf get_status 2>/dev/null";
        $output = (string)shell_exec($cmd);
        if ($output === '') {
            return true; // fail closed
        }

        return strpos($output, '"scanID": ' . $scanId) !== false;
    }
}



/* ---------------------- main ---------------------- */
if ($argc !== 3) {
    logMsg("Usage: php recon-websocket.php <database> <scanID>", true);
    exit(1);
}

$sqlitePath = $argv[1];
$scanId = (int)$argv[2];

$lock = new LockFile();
if (!$lock->acquire()) {
    logMsg("[!] Failed to acquire lock. Is reconpp already running?", true);
    exit(1);
}


logMsg("[*] Starting for: scanID={$scanId} db={$sqlitePath}");
TokenAuth::ensureTokenFile();

$server = null;
$repo = null;
$lastChangeToken = '';
$running = true;
$iteration = 0;
$scanRunning = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() use (&$running) { $running = false; });
    pcntl_signal(SIGTERM, function() use (&$running) { $running = false; });
}

try {
    $server = new WsServer(WS_LISTEN_HOST, WS_LISTEN_PORT);
    $repo = new ReconRepository($sqlitePath, $scanId);
    logMsg("[*] WS listening on " . WS_LISTEN_HOST . ":" . WS_LISTEN_PORT);

    while ($running) {
        $iteration++;

        // 1) keep accepting clients + handshakes
        $server->tick();
        if (!$server->hasClients()) {
            usleep(2000000);
            continue;
        }

        // 2) check scan status (throttled)
        if ($iteration % SCAN_STATUS_CHECK_EVERY === 0) {
            $scanRunning = PineapStatus::isScanRunning($scanId);
            if (!$scanRunning) {
                logMsg("[scan] It seems to be complete");
                $server->broadcastJson(['scan_complete' => true]);
                break;
            }
        }

        if (!$scanRunning) {
            break;
        }

        // 3) read DB snapshot + broadcast only if something changed (perf: reduce DB + network)
        $changeToken = $repo->getChangeToken();
        if ($changeToken !== $lastChangeToken || $server->shouldForceBroadcast()) {
            logMsg("[scan] Broadcasting data");
            $lastChangeToken = $changeToken;
            $snapshot = $repo->readSnapshot();
            $snapshot['scan_complete'] = false;
            $server->broadcastJson($snapshot);
        }

        // 4) poll interval (but keep WS responsive)
        $deadline = microtime(true) + POLL_INTERVAL_SEC;
        while ($running && microtime(true) < $deadline) {
            $server->tick();
            usleep(50000);
        }
    }
} catch (Throwable $e) {
    logMsg("[!] Fatal: {$e->getMessage()}", true);
} finally {
    if ($server) $server->close();
    $lock->release();
    TokenAuth::removeTokenFile();
    logMsg("[*] Stopped");
}
