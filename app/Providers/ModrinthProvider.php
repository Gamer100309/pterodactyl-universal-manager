<?php
/**
 * DATEIPFAD: app/Providers/ModrinthProvider.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Providers/ModrinthProvider.php
 *
 * API-Basis: https://api.modrinth.com/v2  (kein API-Key nötig)
 * Dokumentation: https://docs.modrinth.com/api/
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers;

class ModrinthProvider extends BaseProvider
{
    private const BASE_URL = 'https://api.modrinth.com/v2';

    // Mapping: interner Typ → Modrinth project_type
    private const TYPE_MAP = [
        'mod'          => 'mod',
        'modpack'      => 'modpack',
        'resourcepack' => 'resourcepack',
        'datapack'     => 'datapack',
        'plugin'       => 'plugin',
    ];

    // Alle unterstützten Loader auf Modrinth
    private const LOADERS = [
        // Mod-Loader
        'forge', 'neoforge', 'fabric', 'quilt', 'liteloader', 'rift',
        // Plugin-Plattformen
        'bukkit', 'spigot', 'paper', 'purpur', 'folia',
        'bungeecord', 'waterfall', 'velocity', 'sponge',
        // Risugami's ModLoader (sehr alt, aber der Standard sieht es vor)
        'modloader',
    ];

    // ── Interface-Implementierung ────────────────────────────────────────────

    public function getId(): string    { return 'modrinth'; }
    public function getName(): string  { return 'Modrinth'; }

    public function getSupportedTypes(): array
    {
        return ['mod', 'modpack', 'resourcepack', 'datapack', 'plugin'];
    }

    public function getSupportedLoaders(): array
    {
        return self::LOADERS;
    }

    // ── Suche ────────────────────────────────────────────────────────────────

    public function search(string $query, array $filters = []): array
    {
        $perPage = 20;
        $offset  = (max(1, (int)($filters['page'] ?? 1)) - 1) * $perPage;
        $facets  = [];

        // Typ-Facette
        $type = $filters['type'] ?? null;
        if ($type && isset(self::TYPE_MAP[$type])) {
            $facets[] = ['project_type:' . self::TYPE_MAP[$type]];
        }

        // Loader-Facette (als "Kategorie" in Modrinth)
        $loader = $filters['loader'] ?? null;
        if ($loader && in_array($loader, self::LOADERS, true)) {
            $facets[] = ['categories:' . $loader];
        }

        // Minecraft-Version-Facette
        $version = $filters['version'] ?? null;
        if ($version) {
            $facets[] = ['versions:' . $version];
        }

        $params = [
            'query'  => $query,
            'limit'  => $perPage,
            'offset' => $offset,
        ];

        if (!empty($facets)) {
            $params['facets'] = json_encode($facets);
        }

        $url  = self::BASE_URL . '/search?' . http_build_query($params);
        $data = $this->httpGet($url);

        if (!$data || empty($data['hits'])) {
            return $this->emptyResult($perPage);
        }

        return [
            'results'  => array_map([$this, 'normalizeResult'], $data['hits']),
            'total'    => $data['total_hits'] ?? 0,
            'page'     => (int)($filters['page'] ?? 1),
            'per_page' => $perPage,
        ];
    }

    // ── Versionen ────────────────────────────────────────────────────────────

    public function getVersions(string $projectId, array $filters = []): array
    {
        $params = [];

        if (!empty($filters['loader'])) {
            $params['loaders'] = json_encode([$filters['loader']]);
        }
        if (!empty($filters['version'])) {
            $params['game_versions'] = json_encode([$filters['version']]);
        }

        $url  = self::BASE_URL . '/project/' . urlencode($projectId) . '/version';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $data = $this->httpGet($url);
        if (!is_array($data)) {
            return [];
        }

        return array_map(function (array $v): array {
            // Primäre Datei bevorzugen
            $files = array_values(array_map(fn($f) => [
                'name'    => $f['filename'] ?? '',
                'url'     => $f['url']      ?? '',
                'size'    => $f['size']     ?? 0,
                'primary' => $f['primary']  ?? false,
            ], $v['files'] ?? []));

            return [
                'id'             => $v['id']             ?? '',
                'name'           => $v['name']           ?? '',
                'version_number' => $v['version_number'] ?? '',
                'game_versions'  => $v['game_versions']  ?? [],
                'loaders'        => $v['loaders']        ?? [],
                'release_type'   => $v['version_type']   ?? 'release',
                'date'           => $v['date_published']  ?? '',
                'downloads'      => $v['downloads']       ?? 0,
                'files'          => $files,
            ];
        }, $data);
    }

    // ── Download-URL ─────────────────────────────────────────────────────────

    public function getDownloadUrl(string $projectId, string $versionId): ?string
    {
        $url  = self::BASE_URL . '/version/' . urlencode($versionId);
        $data = $this->httpGet($url);

        if (!$data || empty($data['files'])) {
            return null;
        }

        // Primäre Datei bevorzugen
        foreach ($data['files'] as $file) {
            if ($file['primary'] ?? false) {
                return $file['url'] ?? null;
            }
        }

        return $data['files'][0]['url'] ?? null;
    }

    // ── Normalisierung ───────────────────────────────────────────────────────

    protected function normalizeResult(array $raw): array
    {
        return $this->buildResult(
            id:          $raw['project_id'] ?? ($raw['slug'] ?? ''),
            name:        $raw['title']       ?? '',
            description: $raw['description'] ?? '',
            type:        $raw['project_type'] ?? 'mod',
            icon:        $raw['icon_url']    ?? '',
            downloads:   (int)($raw['downloads'] ?? 0),
            loaders:     $raw['categories']  ?? [],
            versions:    $raw['versions']    ?? [],
            externalUrl: 'https://modrinth.com/project/' . ($raw['slug'] ?? ''),
            provider:    $this->getId()
        );
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function emptyResult(int $perPage): array
    {
        return ['results' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage];
    }
}
