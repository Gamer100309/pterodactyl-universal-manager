<?php
/**
 * DATEIPFAD: app/Providers/CurseForgeProvider.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Providers/CurseForgeProvider.php
 *
 * API-Basis: https://api.curseforge.com/v1  (API-Key erforderlich!)
 * Dokumentation: https://docs.curseforge.com/
 * API-Key beantragen: https://console.curseforge.com/
 *
 * Minecraft Game-ID: 432
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers;

class CurseForgeProvider extends BaseProvider
{
    private const BASE_URL = 'https://api.curseforge.com/v1';
    private const GAME_ID  = 432; // Minecraft

    // CurseForge classId-Werte für Minecraft-Inhaltstypen
    private const CLASS_IDS = [
        'mod'          => 6,     // Mods
        'modpack'      => 4471,  // Modpacks (neuer Wert seit 2022)
        'resourcepack' => 12,    // Resource Packs
        'datapack'     => 6945,  // Data Packs
        'plugin'       => 5,     // Bukkit Plugins
    ];

    // CurseForge ModLoaderType enum
    private const LOADER_TYPE_MAP = [
        'any'        => 0,
        'forge'      => 1,
        'cauldron'   => 2,
        'liteloader' => 3,
        'fabric'     => 4,
        'quilt'      => 5,
        'neoforge'   => 6,
    ];

    public function __construct(private readonly string $apiKey) {}

    // ── Interface ────────────────────────────────────────────────────────────

    public function getId(): string    { return 'curseforge'; }
    public function getName(): string  { return 'CurseForge'; }

    public function getSupportedTypes(): array
    {
        return ['mod', 'modpack', 'resourcepack', 'datapack', 'plugin'];
    }

    public function getSupportedLoaders(): array
    {
        return ['forge', 'neoforge', 'fabric', 'quilt', 'liteloader'];
    }

    // ── Suche ────────────────────────────────────────────────────────────────

    public function search(string $query, array $filters = []): array
    {
        $perPage = 20;
        $page    = max(1, (int)($filters['page'] ?? 1));
        $type    = $filters['type'] ?? 'mod';
        $classId = self::CLASS_IDS[$type] ?? self::CLASS_IDS['mod'];

        $params = [
            'gameId'       => self::GAME_ID,
            'classId'      => $classId,
            'searchFilter' => $query,
            'pageSize'     => $perPage,
            'index'        => ($page - 1) * $perPage,
            'sortField'    => 2,     // 2 = Popularity
            'sortOrder'    => 'desc',
        ];

        // Loader-Filter
        $loader = $filters['loader'] ?? null;
        if ($loader && isset(self::LOADER_TYPE_MAP[$loader])) {
            $params['modLoaderType'] = self::LOADER_TYPE_MAP[$loader];
        }

        // Minecraft-Version-Filter
        if (!empty($filters['version'])) {
            $params['gameVersion'] = $filters['version'];
        }

        $url  = self::BASE_URL . '/mods/search?' . http_build_query($params);
        $data = $this->httpGet($url, $this->authHeaders());

        if (!$data || !isset($data['data'])) {
            return ['results' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        return [
            'results'  => array_map([$this, 'normalizeResult'], $data['data']),
            'total'    => $data['pagination']['totalCount'] ?? count($data['data']),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    // ── Versionen ────────────────────────────────────────────────────────────

    public function getVersions(string $projectId, array $filters = []): array
    {
        $params = ['pageSize' => 20, 'index' => 0];

        if (!empty($filters['version'])) {
            $params['gameVersion'] = $filters['version'];
        }
        $loader = $filters['loader'] ?? null;
        if ($loader && isset(self::LOADER_TYPE_MAP[$loader])) {
            $params['modLoaderType'] = self::LOADER_TYPE_MAP[$loader];
        }

        $url  = self::BASE_URL . '/mods/' . urlencode($projectId) . '/files?' . http_build_query($params);
        $data = $this->httpGet($url, $this->authHeaders());

        if (!$data || !isset($data['data'])) {
            return [];
        }

        return array_map(function (array $f): array {
            return [
                'id'             => (string)($f['id'] ?? ''),
                'name'           => $f['displayName']  ?? '',
                'version_number' => $f['fileName']     ?? '',
                'game_versions'  => $f['gameVersions'] ?? [],
                'loaders'        => $this->extractLoaders($f['gameVersions'] ?? []),
                'release_type'   => $this->mapReleaseType($f['releaseType'] ?? 1),
                'date'           => $f['fileDate']      ?? '',
                'downloads'      => (int)($f['downloadCount'] ?? 0),
                'files'          => [[
                    'name'    => $f['fileName']    ?? '',
                    'url'     => $f['downloadUrl'] ?? '',
                    'size'    => (int)($f['fileLength'] ?? 0),
                    'primary' => true,
                ]],
            ];
        }, $data['data']);
    }

    // ── Download-URL ─────────────────────────────────────────────────────────

    public function getDownloadUrl(string $projectId, string $versionId): ?string
    {
        $url  = self::BASE_URL . '/mods/' . urlencode($projectId)
              . '/files/' . urlencode($versionId) . '/download-url';
        $data = $this->httpGet($url, $this->authHeaders());

        return $data['data'] ?? null;
    }

    // ── Normalisierung ───────────────────────────────────────────────────────

    protected function normalizeResult(array $raw): array
    {
        $latestVersions = array_unique(array_column($raw['latestFilesIndexes'] ?? [], 'gameVersion'));

        return $this->buildResult(
            id:          (string)($raw['id'] ?? ''),
            name:        $raw['name']    ?? '',
            description: $raw['summary'] ?? '',
            type:        $this->classIdToType((int)($raw['classId'] ?? 6)),
            icon:        $raw['logo']['url'] ?? '',
            downloads:   (int)($raw['downloadCount'] ?? 0),
            loaders:     [],
            versions:    array_values(array_slice($latestVersions, 0, 10)),
            externalUrl: $raw['links']['websiteUrl'] ?? '',
            provider:    $this->getId()
        );
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function authHeaders(): array
    {
        return [
            'x-api-key: '  . $this->apiKey,
            'Accept: application/json',
        ];
    }

    private function mapReleaseType(int $type): string
    {
        return match ($type) {
            1 => 'release',
            2 => 'beta',
            3 => 'alpha',
            default => 'release',
        };
    }

    private function classIdToType(int $classId): string
    {
        return match ($classId) {
            5    => 'plugin',
            6    => 'mod',
            12   => 'resourcepack',
            4471 => 'modpack',
            6945 => 'datapack',
            default => 'mod',
        };
    }

    /** Loader-Namen aus gameVersions-Array filtern (CurseForge mischt beides) */
    private function extractLoaders(array $gameVersions): array
    {
        $knownLoaders = ['Forge', 'NeoForge', 'Fabric', 'Quilt', 'LiteLoader'];
        return array_values(array_filter(
            $gameVersions,
            fn($v) => in_array($v, $knownLoaders, true)
        ));
    }
}
