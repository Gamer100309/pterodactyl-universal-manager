<?php
/**
 * DATEIPFAD: app/Controllers/UniversalManagerController.php
 * Symlink nach: app/BlueprintFramework/Extensions/Universalmanager/Controllers/UniversalManagerController.php
 *
 * Dieser Controller verarbeitet alle API-Anfragen vom React-Frontend.
 * Er delegiert die eigentliche Logik an UniversalManagerService und DownloadService.
 */

namespace Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Services\DownloadService;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Services\UniversalManagerService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

class UniversalManagerController extends ClientApiController
{
    public function __construct(
        private readonly UniversalManagerService $manager,
        private readonly DownloadService         $downloader
    ) {
        parent::__construct();
    }

    // ── GET /providers ───────────────────────────────────────────────────────

    public function getProviders(Request $request): JsonResponse
    {
        $list = [];
        foreach ($this->manager->getAllProviders() as $id => $provider) {
            $list[] = [
                'id'              => $id,
                'name'            => $provider->getName(),
                'supported_types' => $provider->getSupportedTypes(),
                'supported_loaders' => $provider->getSupportedLoaders(),
            ];
        }
        return response()->json(['providers' => $list]);
    }

    // ── GET /search ──────────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query'    => 'required|string|min:1|max:100',
            'provider' => 'nullable|string|max:50',
            'type'     => 'nullable|string|in:mod,plugin,modpack,resourcepack,datapack',
            'loader'   => 'nullable|string|max:30',
            'version'  => 'nullable|string|max:20',
            'page'     => 'nullable|integer|min:1|max:100',
        ]);

        $results = $this->manager->search($data['query'], array_filter($data));
        return response()->json($results);
    }

    // ── GET /versions ────────────────────────────────────────────────────────

    public function getVersions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider'   => 'required|string|max:50',
            'project_id' => 'required|string|max:200',
            'loader'     => 'nullable|string|max:30',
            'version'    => 'nullable|string|max:20',
        ]);

        $versions = $this->manager->getVersions(
            $data['provider'],
            $data['project_id'],
            array_filter(array_intersect_key($data, array_flip(['loader', 'version'])))
        );

        return response()->json(['versions' => $versions]);
    }

    // ── POST /download ───────────────────────────────────────────────────────

    /**
     * Datei herunterladen und auf dem angegebenen Server installieren.
     *
     * Request-Body:
     * {
     *   "server_uuid": "uuid",
     *   "provider":    "modrinth",
     *   "project_id":  "AAbbCCdd",
     *   "version_id":  "EEffGGhh",
     *   "type":        "mod",
     *   "filename":    "SomeMod-1.2.3.jar"
     * }
     */
    public function download(Request $request): JsonResponse
    {
        $data = $request->validate([
            'server_uuid' => 'required|uuid',
            'provider'    => 'required|string|max:50',
            'project_id'  => 'required|string|max:200',
            'version_id'  => 'required|string|max:200',
            'type'        => 'required|string|in:mod,plugin,modpack,resourcepack,datapack',
            // Dateiname: nur sichere Zeichen erlaubt (verhindert Path-Traversal)
            'filename'    => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9 \-_.+()]*\.[a-zA-Z0-9]+$/'],
        ]);

        // ── Server-Berechtigungen prüfen ─────────────────────────────────────
        $server = Server::where('uuid', $data['server_uuid'])->firstOrFail();
        $user   = $request->user();

        if (!$this->userCanAccessServer($user, $server)) {
            throw new AuthorizationException('Du hast keinen Zugriff auf diesen Server.');
        }

        // ── Download-URL vom Provider holen ──────────────────────────────────
        $downloadUrl = $this->manager->getDownloadUrl(
            $data['provider'],
            $data['project_id'],
            $data['version_id']
        );

        if (!$downloadUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Keine Download-URL verfügbar. '
                           . 'Möglicherweise ist dies ein Premium-Plugin oder der API-Key fehlt.',
            ], 422);
        }

        // ── Installationspfad bestimmen ───────────────────────────────────────
        $filename    = $data['filename'];
        $installPath = $this->manager->resolveInstallPath($data['type'], $filename);
        $extract     = $this->manager->shouldExtract($data['type'], $filename);

        // ── Herunterladen & Installieren ─────────────────────────────────────
        $result = $this->downloader->downloadAndInstall(
            $server,
            $downloadUrl,
            $installPath,
            $extract
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    // ── GET /settings ────────────────────────────────────────────────────────

    public function getSettings(Request $request): JsonResponse
    {
        if (!$request->user()->root_admin) {
            throw new AuthorizationException('Nur Administratoren können Einstellungen abrufen.');
        }

        $cfKey = $this->manager->getGlobalSetting('curseforge_api_key') ?? '';

        return response()->json([
            'curseforge_api_key'     => $this->maskKey($cfKey),
            'has_curseforge_api_key' => !empty($cfKey),
        ]);
    }

    // ── POST /settings ───────────────────────────────────────────────────────

    public function saveSettings(Request $request): JsonResponse
    {
        if (!$request->user()->root_admin) {
            throw new AuthorizationException('Nur Administratoren können Einstellungen ändern.');
        }

        $data = $request->validate([
            'curseforge_api_key' => 'nullable|string|max:500',
        ]);

        $key = $data['curseforge_api_key'] ?? '';

        // Nicht speichern, wenn der Wert nur Sternchen ist (maskierter Key aus GET /settings)
        if (!empty($key) && !preg_match('/^\*+$/', $key)) {
            $this->manager->setGlobalSetting('curseforge_api_key', $key);
        } elseif (empty($key)) {
            $this->manager->setGlobalSetting('curseforge_api_key', null);
        }

        return response()->json(['success' => true, 'message' => 'Einstellungen gespeichert.']);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    /** Prüft ob ein Nutzer Zugriff auf einen Server hat */
    private function userCanAccessServer(User $user, Server $server): bool
    {
        if ($user->root_admin)             return true;
        if ($server->owner_id === $user->id) return true;

        return $server->subusers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Maskiert einen API-Key für die Anzeige (zeigt nur letzte 4 Zeichen) */
    private function maskKey(string $key): string
    {
        if (strlen($key) <= 4) {
            return str_repeat('*', strlen($key));
        }
        return str_repeat('*', strlen($key) - 4) . substr($key, -4);
    }
}
