<?php
/**
 * DATEIPFAD: app/Providers/BaseProvider.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Providers/BaseProvider.php
 *
 * ╔══════════════════════════════════════════════════════════╗
 * ║  NEUEN PROVIDER HINZUFÜGEN — 3 Schritte:                ║
 * ║  1. Klasse erstellen, die BaseProvider erweitert         ║
 * ║  2. Alle abstract-Methoden implementieren                ║
 * ║  3. In UniversalManagerService::loadProviders() eintragen║
 * ║  → UI und Core bleiben vollständig unverändert!          ║
 * ╚══════════════════════════════════════════════════════════╝
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers;

abstract class BaseProvider
{
    protected const USER_AGENT = 'UniversalManager/1.0 (Pterodactyl Blueprint Extension)';

    // ── Pflichtmethoden ──────────────────────────────────────────────────────

    /** Eindeutiger interner Bezeichner (nur a-z) */
    abstract public function getId(): string;

    /** Anzeigename in der UI */
    abstract public function getName(): string;

    /**
     * Unterstützte Inhaltstypen.
     * Werte: 'mod', 'plugin', 'modpack', 'resourcepack', 'datapack'
     */
    abstract public function getSupportedTypes(): array;

    /**
     * Unterstützte Loader/Plattformen.
     * z.B. ['fabric', 'forge', 'paper', 'velocity']
     */
    abstract public function getSupportedLoaders(): array;

    /**
     * Suche nach Projekten.
     *
     * @param string $query    Suchbegriff
     * @param array  $filters  ['type'=>, 'loader'=>, 'version'=>, 'page'=>]
     * @return array ['results'=>[], 'total'=>int, 'page'=>int, 'per_page'=>int]
     */
    abstract public function search(string $query, array $filters = []): array;

    /**
     * Alle verfügbaren Versionen eines Projekts.
     *
     * @param string $projectId
     * @param array  $filters   ['loader'=>, 'version'=>]
     * @return array  Jede Version: [id, name, version_number, game_versions[],
     *                loaders[], release_type, date, downloads, files[]]
     */
    abstract public function getVersions(string $projectId, array $filters = []): array;

    /**
     * Direkte Download-URL für eine Version ermitteln.
     * Gibt null zurück, wenn kein Download möglich (z.B. Premium-Plugin).
     */
    abstract public function getDownloadUrl(string $projectId, string $versionId): ?string;

    /** Rohes API-Ergebnis in normalisiertes Format überführen */
    abstract protected function normalizeResult(array $raw): array;

    // ── Hilfsmethoden für alle Provider ─────────────────────────────────────

    /**
     * HTTP GET mit curl — gibt dekodiertes JSON zurück oder null bei Fehler.
     *
     * @param string   $url
     * @param string[] $headers  Format: ["Key: Value", ...]
     * @param int      $timeout  Sekunden
     */
    protected function httpGet(string $url, array $headers = [], int $timeout = 20): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlErr)) {
            \Log::warning("[UniversalManager:{$this->getId()}] cURL-Fehler @ {$url}: {$curlErr}");
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            \Log::warning("[UniversalManager:{$this->getId()}] HTTP {$httpCode} @ {$url}");
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::warning("[UniversalManager:{$this->getId()}] JSON-Fehler: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Standardisiertes Ergebnis-Array erstellen.
     * Alle Provider MÜSSEN dieses Format zurückgeben!
     */
    protected function buildResult(
        string $id,
        string $name,
        string $description,
        string $type,
        string $icon,
        int    $downloads,
        array  $loaders,
        array  $versions,
        string $externalUrl,
        string $provider
    ): array {
        return compact(
            'id', 'name', 'description', 'type',
            'icon', 'downloads', 'loaders', 'versions',
            'externalUrl', 'provider'
        );
    }
}
