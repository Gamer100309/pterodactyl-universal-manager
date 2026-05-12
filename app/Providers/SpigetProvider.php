<?php
/**
 * DATEIPFAD: app/Providers/SpigetProvider.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Providers/SpigetProvider.php
 *
 * API-Basis: https://api.spiget.org/v2  (kein API-Key nötig)
 * Spiget ist ein Proxy/Crawler für SpigotMC-Ressourcen.
 * Dokumentation: https://spiget.org/documentation/
 *
 * Hinweis: Manche Premium-Plugins erlauben keinen automatischen Download.
 * In diesem Fall gibt getDownloadUrl() die externe SpigotMC-Seite zurück.
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers;

class SpigetProvider extends BaseProvider
{
    private const BASE_URL        = 'https://api.spiget.org/v2';
    private const SPIGOT_BASE_URL = 'https://www.spigotmc.org';

    public function getId(): string    { return 'spiget'; }
    public function getName(): string  { return 'SpigotMC'; }

    public function getSupportedTypes(): array
    {
        return ['plugin'];
    }

    public function getSupportedLoaders(): array
    {
        return ['bukkit', 'spigot', 'paper', 'purpur', 'folia'];
    }

    // ── Suche ────────────────────────────────────────────────────────────────

    public function search(string $query, array $filters = []): array
    {
        $perPage = 20;
        $page    = max(1, (int)($filters['page'] ?? 1));

        $params = [
            'size'   => $perPage,
            'page'   => $page,
            'sort'   => '-downloads',
            'fields' => 'id,name,tag,downloads,icon,version,testedVersions,premium,external,file',
        ];

        $url  = self::BASE_URL . '/search/resources/' . rawurlencode($query) . '?' . http_build_query($params);
        $data = $this->httpGet($url);

        if (!is_array($data)) {
            return ['results' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        return [
            'results'  => array_map([$this, 'normalizeResult'], $data),
            'total'    => count($data) >= $perPage
                            ? ($page * $perPage) + 1  // Es gibt weitere Seiten
                            : (($page - 1) * $perPage) + count($data),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    // ── Versionen ────────────────────────────────────────────────────────────

    public function getVersions(string $projectId, array $filters = []): array
    {
        $url  = self::BASE_URL . '/resources/' . rawurlencode($projectId) . '/versions?size=15&page=1&sort=-releaseDate';
        $data = $this->httpGet($url);

        if (!is_array($data)) {
            return [];
        }

        return array_map(function (array $v) use ($projectId): array {
            $vId = (string)($v['id'] ?? '');
            return [
                'id'             => $vId,
                'name'           => $v['name'] ?? '',
                'version_number' => $v['name'] ?? '',
                'game_versions'  => [],
                'loaders'        => ['spigot'],
                'release_type'   => 'release',
                'date'           => isset($v['releaseDate'])
                    ? date('Y-m-d\TH:i:s\Z', (int)$v['releaseDate'])
                    : '',
                'downloads'      => (int)($v['downloads'] ?? 0),
                'files'          => [[
                    'name'    => 'plugin.jar',
                    'url'     => self::BASE_URL . '/resources/' . rawurlencode($projectId)
                               . '/versions/' . rawurlencode($vId) . '/download',
                    'size'    => 0,
                    'primary' => true,
                ]],
            ];
        }, $data);
    }

    // ── Download-URL ─────────────────────────────────────────────────────────

    public function getDownloadUrl(string $projectId, string $versionId): ?string
    {
        if ($versionId === 'latest') {
            return self::BASE_URL . '/resources/' . rawurlencode($projectId) . '/download';
        }

        return self::BASE_URL . '/resources/' . rawurlencode($projectId)
             . '/versions/' . rawurlencode($versionId) . '/download';
    }

    // ── Normalisierung ───────────────────────────────────────────────────────

    protected function normalizeResult(array $raw): array
    {
        $id      = (string)($raw['id'] ?? '');
        $iconUrl = '';

        // Icon-URL zusammenbauen
        if (!empty($raw['icon']['url'])) {
            $icon = $raw['icon']['url'];
            $iconUrl = str_starts_with($icon, 'http')
                ? $icon
                : self::SPIGOT_BASE_URL . '/' . ltrim($icon, '/');
        }

        return $this->buildResult(
            id:          $id,
            name:        $raw['name'] ?? '',
            description: $raw['tag']  ?? '',
            type:        'plugin',
            icon:        $iconUrl,
            downloads:   (int)($raw['downloads'] ?? 0),
            loaders:     ['spigot', 'bukkit'],
            versions:    $raw['testedVersions'] ?? [],
            externalUrl: self::SPIGOT_BASE_URL . '/resources/' . $id,
            provider:    $this->getId()
        );
    }
}
