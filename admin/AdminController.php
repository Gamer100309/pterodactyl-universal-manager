<?php
/**
 * DATEIPFAD: admin/AdminController.php
 * Blueprint kopiert diese Datei nach:
 *   app/Http/Controllers/Admin/Extensions/Universalmanager/UniversalmanagerExtensionController.php
 *
 * Namespace muss genau passen: Pterodactyl\Http\Controllers\Admin\Extensions\{Identifier}
 * Klasse muss heißen:          {Identifier}ExtensionController
 */

namespace Pterodactyl\Http\Controllers\Admin\Extensions\Universalmanager;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Services\UniversalManagerService;

class UniversalmanagerExtensionController extends Controller
{
    public function __construct(
        private readonly UniversalManagerService $manager
    ) {}

    /** GET /admin/extensions/universalmanager */
    public function index(Request $request): View
    {
        $cfKey    = $this->manager->getGlobalSetting('curseforge_api_key') ?? '';
        $hasCfKey = !empty($cfKey);

        // Maskierten Key für die Anzeige vorbereiten
        $maskedKey = $hasCfKey
            ? str_repeat('*', max(0, strlen($cfKey) - 4)) . substr($cfKey, -4)
            : '';

        $providerList = [];
        foreach ($this->manager->getAllProviders() as $id => $provider) {
            $providerList[] = [
                'id'     => $id,
                'name'   => $provider->getName(),
                'types'  => $provider->getSupportedTypes(),
                'active' => true,
            ];
        }

        // CurseForge als inaktiv anzeigen, wenn kein Key vorhanden
        if (!$hasCfKey) {
            $providerList[] = [
                'id'     => 'curseforge',
                'name'   => 'CurseForge',
                'types'  => ['mod', 'modpack', 'resourcepack', 'datapack', 'plugin'],
                'active' => false,
            ];
        }

        return view('admin.extensions.universalmanager.index', [
            'providers'  => $providerList,
            'masked_key' => $maskedKey,
            'has_cf_key' => $hasCfKey,
        ]);
    }

    /** PATCH /admin/extensions/universalmanager */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curseforge_api_key' => 'nullable|string|max:500',
        ]);

        $newKey = trim($validated['curseforge_api_key'] ?? '');

        // Nur speichern wenn echter Wert (nicht nur Sternchen = UI-Platzhalter)
        if (!empty($newKey) && !preg_match('/^\*+$/', $newKey)) {
            $this->manager->setGlobalSetting('curseforge_api_key', $newKey);
        } elseif (empty($newKey)) {
            $this->manager->setGlobalSetting('curseforge_api_key', null);
        }

        return redirect()->route('admin.extensions.universalmanager')
            ->with('success', 'Einstellungen wurden erfolgreich gespeichert.');
    }
}
