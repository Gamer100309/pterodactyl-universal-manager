<?php
/**
 * DATEIPFAD: app/Services/UniversalManagerService.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Services/UniversalManagerService.php
 *
 * Dieser Service koordiniert alle Provider und verwaltet die Einstellungen.
 * Der Controller spricht NUR mit diesem Service, nie direkt mit Providern.
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers\BaseProvider;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers\ModrinthProvider;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers\CurseForgeProvider;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers\HangarProvider;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Providers\SpigetProvider;

class UniversalManagerService
{
    /** @var array<string, BaseProvider> Registrierte Provider, key = getId() */
    private array $providers = [];

    public function __construct()
    {
        $this->loadProviders();
    }

    // ── Provider-Verwaltung ──────────────────────────────────────────────────

    /**
     * Alle Provider initialisieren und registrieren.
     * Hier werden API-Keys aus der Datenbank geladen.
     */
    private function loadProviders(): void
    {
        // Modrinth — kein API-Key benötigt
        $this->register(new ModrinthProvider());

        // Hangar — kein Key für Lesezugriff benötigt
        $this->register(new HangarProvider());

        // SpigotMC via Spiget — kein API-Key benötigt
        $this->register(new SpigetProvider());

        // CurseForge — nur aktiv wenn API-Key in den Admin-Einstellungen hinterlegt
        $cfKey = $this->getGlobalSetting('curseforge_api_key');
        if (!empty($cfKey)) {
            $this->register(new CurseForgeProvider($cfKey));
        }
    }

    public function register(BaseProvider $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    /** Einzelnen Provider nach ID abrufen */
    public function getProvider(string $id): ?BaseProvider
    {
        return $this->providers[$id] ?? null;
    }

    /** Alle registrierten Provider zurückgeben */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /** Alle Provider zurückgeben, die einen bestimmten Typ unterstützen */
    public function getProvidersForType(string $type): array
    {
        return array_filter(
            $this->providers,
            fn($p) => in_array($type, $p->getSupportedTypes(), true)
        );
    }

    // ── Suche ────────────────────────────────────────────────────────────────

    /**
     * Suche über einen oder alle passenden Provider.
     *
     * Wenn $filters['provider'] gesetzt ist, wird nur dieser abgefragt.
     * Sonst werden alle Provider für den gewählten Typ zusammengeführt.
     */
    public function search(string $query, array $filters = []): array
    {
        $specificProvider = $filters['provider'] ?? null;

        if ($specificProvider) {
            $provider = $this->getProvider($specificProvider);
            if (!$provider) {
                return ['results' => [], 'total' => 0, 'error' => "Unbekannter Provider: {$specificProvider}"];
            }
            $result             = $provider->search($query, $filters);
            $result['provider'] = $specificProvider;
            return $result;
        }

        // Alle passenden Provider parallel abfragen und Ergebnisse mergen
        $type      = $filters['type'] ?? null;
        $providers = $type ? $this->getProvidersForType($type) : $this->providers;
        $merged    = [];

        foreach ($providers as $id => $provider) {
            try {
                $result = $provider->search($query, array_merge($filters, ['page' => 1]));
                foreach ($result['results'] as $item) {
                    $merged[] = $item;
                }
            } catch (\Throwable $e) {
                Log::warning("[UniversalManager] Provider '{$id}' Suchfehler: " . $e->getMessage());
            }
        }

        // Nach Downloads sortieren
        usort($merged, fn($a, $b) => ($b['downloads'] ?? 0) <=> ($a['downloads'] ?? 0));

        return [
            'results' => array_values(array_slice($merged, 0, 40)),
            'total'   => count($merged),
            'page'    => 1,
        ];
    }

    // ── Versions- und Download-Logik ─────────────────────────────────────────

    public function getVersions(string $providerId, string $projectId, array $filters = []): array
    {
        return $this->getProvider($providerId)?->getVersions($projectId, $filters) ?? [];
    }

    public function getDownloadUrl(string $providerId, string $projectId, string $versionId): ?string
    {
        return $this->getProvider($providerId)?->getDownloadUrl($projectId, $versionId);
    }

    // ── Installations-Intelligenz ────────────────────────────────────────────

    /**
     * Ermittelt den Installationspfad auf dem Server basierend auf dem Inhaltstyp.
     *
     * @param string $type      mod | plugin | modpack | resourcepack | datapack
     * @param string $filename  Dateiname inkl. Erweiterung
     * @return string           Absoluter Pfad auf dem Server (z.B. /plugins/SomeMod.jar)
     */
    public function resolveInstallPath(string $type, string $filename): string
    {
        return match ($type) {
            'mod'          => '/mods/'   . $filename,
            'plugin'       => '/plugins/' . $filename,
            'resourcepack' => '/resourcepacks/' . $filename,
            'datapack'     => '/world/datapacks/' . $filename,
            'modpack'      => '/' . $filename,   // Wird entpackt → Root
            default        => '/' . $filename,
        };
    }

    /**
     * Gibt true zurück, wenn die Datei nach dem Upload entpackt werden soll.
     */
    public function shouldExtract(string $type, string $filename): bool
    {
        if ($type === 'modpack') {
            return true;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['zip', 'mrpack'], true);
    }

    // ── Einstellungen ────────────────────────────────────────────────────────

    public function getGlobalSetting(string $key): ?string
    {
        return DB::table('universalmanager_settings')
            ->whereNull('user_id')
            ->where('setting_key', $key)
            ->value('setting_value');
    }

    public function setGlobalSetting(string $key, ?string $value): void
    {
        DB::table('universalmanager_settings')->updateOrInsert(
            ['user_id' => null, 'setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
