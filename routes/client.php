<?php
/**
 * DATEIPFAD: routes/client.php
 * Blueprint kopiert diese Datei nach: routes/blueprint/client/universalmanager.php
 *
 * Alle Routen erhalten automatisch den Präfix:
 *   /api/client/extensions/universalmanager/
 *
 * Sie benötigen ein gültiges Pterodactyl Client-API-Token.
 */

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\Universalmanager\Controllers\UniversalManagerController;

// Verfügbare Provider zurückgeben
Route::get('/providers', [UniversalManagerController::class, 'getProviders']);

// Suche: GET /api/client/extensions/universalmanager/search?query=...&provider=...&type=...&loader=...&version=...&page=1
Route::get('/search', [UniversalManagerController::class, 'search']);

// Versionen eines Projekts abrufen: GET .../versions?provider=modrinth&project_id=AAbbcc&loader=fabric&version=1.20.1
Route::get('/versions', [UniversalManagerController::class, 'getVersions']);

// Datei herunterladen und auf Server installieren: POST .../download
Route::post('/download', [UniversalManagerController::class, 'download']);

// Admin-Einstellungen lesen (nur Root-Admin): GET .../settings
Route::get('/settings', [UniversalManagerController::class, 'getSettings']);

// Admin-Einstellungen speichern (nur Root-Admin): POST .../settings
Route::post('/settings', [UniversalManagerController::class, 'saveSettings']);
