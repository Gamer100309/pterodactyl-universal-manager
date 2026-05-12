<?php
/**
 * DATEIPFAD: migrations/2024_01_01_000001_create_universalmanager_settings_table.php
 * Blueprint kopiert diese Datei nach: database/migrations/
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universalmanager_settings', function (Blueprint $table) {
            $table->id();
            // NULL = globale Admin-Einstellung, integer = Nutzer-spezifisch
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('setting_key', 100);
            $table->text('setting_value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'setting_key']);
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universalmanager_settings');
    }
};
