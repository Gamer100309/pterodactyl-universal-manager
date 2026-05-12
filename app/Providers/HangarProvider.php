<?php
/**
 * DATEIPFAD: app/Providers/HangarProvider.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Providers/HangarProvider.php
 *
 * API-Basis: https://hangar.papermc.io/api/v1  (kein Key für Lesezugriff)
 * Unterstützte Plattformen: PAPER, VELOCITY, WATERFALL
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers;

class HangarProvider extends BaseProvider
{
    private const BASE_URL = 'https://hangar.papermc.io/api/v1';

    // Interner Loader-Name → Hangar Platform-String
    private const PLATFORM_MAP = [
        'paper'      => 'PAPER',
        'purpur'     => 'PAPER',   // Purpur ist Paper-kompatibel
        'folia'      => 'PAPER',   // Folia ist Paper-kompatibel
        'bukkit'     => 'PAPER',
        'spigot'     => 'PAPER',
        'velocity'   => 'VELOCITY',
        'waterfall'  => 'WATERFALL',
        'bungeecord' => 'WATERFALL',
    ];

    public function __construct(private readonly ?string $apiKey = null) {}

    // ── Interface ────────────────────────────────────────────────────────────

    public function getId(): string    { return 'hangar'; }
    public function getName(): string  { return 'Hangar (PaperMC)'; }

    public function getSupportedTypes(): array
    {
        return ['plugin'];
    }

    public function getSupportedLoaders(): array
    {
        return array_keys(self::PLATFORM_MAP);
    }

    // ── Suche ────────────────────────────────────────────────────────────────

    public function search(string $query, array $filters = []): array
    {
        $perPage  = 20;
        $page     = max(1, (int)($filters['page'] ?? 1));
        $offset   = ($page - 1) * $perPage;

        $params = [
            'q'      => $query,
            'limit'  => $perPage,
            'offset' => $offset,
            'sort'   => '-stars',
        ];

        // Plattform-Filter (Loader → Hangar-Plattform)
        $loader = $filters['loader'] ?? null;
        if ($loader && isset(self::PLATFORM_MAP[$loader])) {
            $params['platform'] = self::PLATFORM_MAP[$loader];
        }

        $url  = self::BASE_URL . '/projects?' . http_build_query($params);
        $data = $this->httpGet($url, $this->buildHeaders());

        if (!$data || !isset($data['result'])) {
            return ['results' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        return [
            'results'  => array_map([$this, 'normalizeResult'], $data['result']),
            'total'    => $data['pagination']['count'] ?? count($data['result']),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    // ── Versionen ────────────────────────────────────────────────────────────

    /**
     * $projectId Format: "AuthorName/slug" (z.B. "LOOHP/LimeLib")
     */
    public function getVersions(string $projectId, array $filters = []): array
    {
        [$author, $slug] = $this->parseProjectId($projectId);
        if (!$author || !$slug) {
            return [];
        }

        $params   = ['limit' => 20, 'offset' => 0];
        $platform = 'PAPER';
        if (!empty($filters['loader']) && isset(self::PLATFORM_MAP[$filters['loader']])) {
            $platform = self::PLATFORM_MAP[$filters['loader']];
        }

        $url  = self::BASE_URL . "/projects/{$author}/{$slug}/versions?" . http_build_query($params);
        $data = $this->httpGet($url, $this->buildHeaders());

        if (!$data || !isset($data['result'])) {
            return [];
        }

        return array_map(function (array $v) use ($author, $slug, $platform): array {
            $dl         = $v['downloads'][$platform] ?? null;
            $platformVersions = $v['platformDependencies'][$platform] ?? [];

            return [
                'id'             => $v['name'] . ':' . $platform,
                'name'           => $v['name'] ?? '',
                'version_number' => $v['name'] ?? '',
                'game_versions'  => $platformVersions,
                'loaders'        => [strtolower($platform)],
                'release_type'   => strtolower($v['channel']['name'] ?? 'release'),
                'date'           => $v['createdAt'] ?? '',
                'downloads'      => (int)($v['stats']['totalDownloads'] ?? 0),
                'files'          => $dl ? [[
                    'name'    => $dl['fileInfo']['name']      ?? "{$slug}-{$v['name']}.jar",
                    'url'     => $dl['downloadUrl']           ?? '',
                    'size'    => (int)($dl['fileInfo']['sizeBytes'] ?? 0),
                    'primary' => true,
                ]] : [],
            ];
        }, $data['result']);
    }

    // ── Download-URL ─────────────────────────────────────────────────────────

    /**
     * $versionId Format: "versionName:PLATFORM" (z.B. "1.4.2:PAPER")
     */
    public function getDownloadUrl(string $projectId, string $versionId): ?string
    {
        [$author, $slug] = $this->parseProjectId($projectId);
        if (!$author || !$slug) {
            return null;
        }

        [$version, $platform] = array_pad(explode(':', $versionId, 2), 2, 'PAPER');

        // Dieser Endpoint leitet direkt zur JAR-Datei weiter
        return self::BASE_URL . "/projects/{$author}/{$slug}/versions/{$version}/{$platform}/download";
    }

    // ── Normalisierung ───────────────────────────────────────────────────────

    protected function normalizeResult(array $raw): array
    {
        $namespace = $raw['namespace'] ?? [];
        $author    = $namespace['owner'] ?? '';
        $slug      = $namespace['slug']  ?? '';

        return $this->buildResult(
            id:          "{$author}/{$slug}",
            name:        $raw['name']        ?? '',
            description: $raw['description'] ?? '',
            type:        'plugin',
            icon:        $raw['avatarUrl']   ?? '',
            downloads:   (int)($raw['stats']['downloads'] ?? 0),
            loaders:     array_map('strtolower', array_keys($raw['settings']['platforms'] ?? [])),
            versions:    [],
            externalUrl: "https://hangar.papermc.io/{$author}/{$slug}",
            provider:    $this->getId()
        );
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function buildHeaders(): array
    {
        $headers = ['Accept: application/json'];
        if ($this->apiKey) {
            $headers[] = 'Authorization: ' . $this->apiKey;
        }
        return $headers;
    }

    /** Trennt "Author/slug" in [$author, $slug] auf */
    private function parseProjectId(string $projectId): array
    {
        $parts = explode('/', $projectId, 2);
        return count($parts) === 2 ? $parts : ['', ''];
    }
}
