<?php
/**
 * DATEIPFAD: app/Services/DownloadService.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Services/DownloadService.php
 *
 * Verantwortlich für:
 *   1. Sicheres Herunterladen von Dateien (Streaming, Größenlimit)
 *   2. Upload zum Wings-Daemon via DaemonFileRepository
 *   3. Automatisches Entpacken von Modpacks via Wings decompress-API
 *   4. Validierung von Dateitypen und -größen
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Services;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class DownloadService
{
    // Erlaubte Dateiendungen (Sicherheitsfilter)
    private const ALLOWED_EXTENSIONS = ['jar', 'zip', 'mrpack', 'litemod'];

    // Maximale Dateigröße: 500 MB
    private const MAX_SIZE_BYTES = 500 * 1024 * 1024;

    public function __construct(
        private readonly DaemonFileRepository $fileRepo
    ) {}

    // ── Haupt-Methode ────────────────────────────────────────────────────────

    /**
     * Datei von externer URL herunterladen und auf dem Pterodactyl-Server installieren.
     *
     * @param Server $server       Ziel-Server
     * @param string $downloadUrl  Externe Download-URL
     * @param string $installPath  Zielpfad auf dem Server (z.B. /plugins/SomeMod.jar)
     * @param bool   $extract      Nach Upload entpacken (für Modpacks)
     *
     * @return array ['success' => bool, 'message' => string, 'filename' => string]
     */
    public function downloadAndInstall(
        Server $server,
        string $downloadUrl,
        string $installPath,
        bool   $extract = false
    ): array {
        $tmpFile  = tempnam(sys_get_temp_dir(), 'um_');
        $filename = basename($installPath);

        try {
            // ── Schritt 1: Dateierweiterung prüfen ──────────────────────────
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                return $this->fail(
                    "Dateityp '.{$ext}' nicht erlaubt. Erlaubt: " . implode(', ', self::ALLOWED_EXTENSIONS),
                    $filename
                );
            }

            // ── Schritt 2: Datei streamen ────────────────────────────────────
            $downloadResult = $this->streamToTemp($downloadUrl, $tmpFile);
            if (!$downloadResult['success']) {
                return array_merge($downloadResult, ['filename' => $filename]);
            }

            $fileSize = filesize($tmpFile);

            if ($fileSize === false || $fileSize === 0) {
                return $this->fail('Download ergab eine leere Datei.', $filename);
            }

            if ($fileSize > self::MAX_SIZE_BYTES) {
                $mb = round($fileSize / 1048576, 1);
                return $this->fail("Datei zu groß: {$mb} MB (Maximum: 500 MB)", $filename);
            }

            // ── Schritt 3: Inhalt in Wings hochladen ─────────────────────────
            $content = file_get_contents($tmpFile);
            if ($content === false) {
                return $this->fail('Temporäre Datei konnte nicht gelesen werden.', $filename);
            }

            $this->fileRepo->setServer($server)->putContent($installPath, $content);

            // ── Schritt 4: Modpack entpacken (falls gewünscht) ───────────────
            if ($extract) {
                $this->extractArchive($server, $installPath);
            }

            Log::info("[UniversalManager] Installiert: {$filename} → Server {$server->uuid}");

            return [
                'success'  => true,
                'message'  => "'{$filename}' wurde erfolgreich installiert.",
                'filename' => $filename,
            ];

        } catch (\Throwable $e) {
            Log::error("[UniversalManager] Installationsfehler", [
                'server'   => $server->uuid,
                'url'      => $downloadUrl,
                'path'     => $installPath,
                'error'    => $e->getMessage(),
            ]);

            return $this->fail('Serverfehler: ' . $e->getMessage(), $filename);

        } finally {
            // Temp-Datei immer löschen
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    // ── Private Hilfsmethoden ────────────────────────────────────────────────

    /**
     * Streamt eine URL in eine temporäre Datei (speicherschonend, auch für große Dateien).
     */
    private function streamToTemp(string $url, string $destination): array
    {
        $fp = fopen($destination, 'wb');
        if (!$fp) {
            return $this->fail('Temporäre Datei konnte nicht erstellt werden.');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_FILE           => $fp,     // Direkt in Datei streamen (kein RAM-Problem)
            CURLOPT_TIMEOUT        => 300,     // 5 Minuten Timeout
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => 'UniversalManager/1.0 (Pterodactyl Blueprint Extension)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR    => true,
        ]);

        $ok       = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || !empty($curlErr)) {
            return $this->fail("Download fehlgeschlagen: {$curlErr}");
        }

        if ($httpCode >= 400) {
            return $this->fail("Server antwortete mit HTTP {$httpCode}");
        }

        return ['success' => true];
    }

    /**
     * Entpackt ein Archiv auf dem Server via Wings decompress-API.
     * Das Original-Archiv wird nach dem Entpacken gelöscht.
     */
    private function extractArchive(Server $server, string $archivePath): void
    {
        $repo = $this->fileRepo->setServer($server);
        $dir  = dirname($archivePath);

        try {
            // Wings-API: POST /api/servers/{uuid}/files/decompress
            $repo->decompress($dir, $archivePath);

            // Archiv nach dem Entpacken entfernen
            try {
                $repo->delete([$archivePath]);
            } catch (\Throwable $e) {
                Log::warning("[UniversalManager] Archiv konnte nicht gelöscht werden: " . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning("[UniversalManager] Entpacken fehlgeschlagen für {$archivePath}: " . $e->getMessage());
            // Nicht als fatalen Fehler behandeln — Datei ist bereits hochgeladen
        }
    }

    /** Hilfsmethode: Fehlerergebnis erstellen */
    private function fail(string $message, string $filename = ''): array
    {
        return ['success' => false, 'message' => $message, 'filename' => $filename];
    }
}
