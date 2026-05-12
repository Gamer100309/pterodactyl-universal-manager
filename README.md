# It is not currently production-ready!⚠️

# 📦 Universal Manager — Pterodactyl Blueprint Extension

> Install mods, plugins, modpacks, resource packs and datapacks from **Modrinth**, **CurseForge**, **Hangar** and **SpigotMC** directly into your Pterodactyl game servers — with one click.

![Blueprint](https://img.shields.io/badge/Blueprint-beta--2025--09-6366f1?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-8.1+-777bb4?style=flat-square&logo=php)
![React](https://img.shields.io/badge/React-TypeScript-61dafb?style=flat-square&logo=react)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

---

## ✨ Features

| Feature | Details |
|---|---|
| 🔍 **Multi-Provider Search** | Modrinth, CurseForge, Hangar, SpigotMC in one interface |
| ⚙️ **Mod Support** | Forge, NeoForge, Fabric, Quilt, LiteLoader, Rift |
| 🔌 **Plugin Support** | Paper, Spigot, Bukkit, Purpur, Folia, Velocity, Waterfall, BungeeCord, Sponge |
| 📦 **Modpack Installer** | Auto-extract archives directly into server root |
| 🎯 **Smart Install Paths** | Auto-detects `/mods/`, `/plugins/`, `/world/datapacks/` etc. |
| 🔒 **Secure Downloads** | File type validation, size limits (500 MB), path traversal protection |
| 🧩 **Modular Providers** | Add/update any provider without touching UI or core |

---

## 📋 Requirements

- [Pterodactyl Panel](https://pterodactyl.io/) `1.11.x`
- [Blueprint Framework](https://blueprint.zip/) `beta-2025-09` or later
- PHP `8.1+`
- A free [CurseForge API Key](https://console.curseforge.com/) *(optional — only needed for CurseForge content)*

---

## 🚀 Installation

### 1. Download the latest release

Download `universalmanager.blueprint` from the [Releases](../../releases) page.

### 2. Upload to your panel server

```bash
scp universalmanager.blueprint root@your-server:/var/www/pterodactyl/
```

### 3. Install via Blueprint CLI

```bash
cd /var/www/pterodactyl
blueprint -install universalmanager.blueprint
```

### 4. (Optional) Add your CurseForge API Key

Go to **Admin Panel → Extensions → Universal Manager** and enter your CurseForge API key.

---

## 🗂️ Project Structure

```
universalmanager/
├── conf.yml                          # Blueprint manifest
├── routes/client.php                 # API routes
├── migrations/                       # Database table for settings
├── admin/
│   ├── AdminController.php           # Admin settings controller
│   └── index.blade.php               # Admin settings page
├── app/
│   ├── Providers/
│   │   ├── BaseProvider.php          # Abstract base — extend this for new providers!
│   │   ├── ModrinthProvider.php      # Modrinth API v2
│   │   ├── CurseForgeProvider.php    # CurseForge Eternal API
│   │   ├── HangarProvider.php        # Hangar (PaperMC) API
│   │   └── SpigetProvider.php        # SpigotMC via Spiget API
│   ├── Services/
│   │   ├── UniversalManagerService.php  # Provider coordinator
│   │   └── DownloadService.php          # Streaming download + Wings upload
│   └── Controllers/
│       └── UniversalManagerController.php
└── components/                       # React/TypeScript frontend
    ├── Components.yml
    ├── UniversalManagerPanel.tsx
    └── sections/
        ├── FilterBar.tsx
        ├── SearchBar.tsx
        ├── ResultCard.tsx
        └── DownloadModal.tsx
```

---

## 🔌 Adding a New Provider

Only **3 steps** — the UI updates automatically:

```php
// 1. Create your provider class
// app/Providers/MyNewProvider.php
class MyNewProvider extends BaseProvider {
    public function getId(): string   { return 'myprovider'; }
    public function getName(): string { return 'My Provider'; }
    // ... implement all abstract methods
}

// 2. Register it in UniversalManagerService::loadProviders()
$this->register(new MyNewProvider());

// 3. Done! ✅ The provider appears in the UI filter dropdown automatically.
```

---

## 📡 API Endpoints

All routes are prefixed with `/api/client/extensions/universalmanager/`

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/providers` | List all active providers |
| `GET` | `/search` | Search across providers |
| `GET` | `/versions` | Get versions for a project |
| `POST` | `/download` | Download & install a file |
| `GET` | `/settings` | Get admin settings *(admin only)* |
| `POST` | `/settings` | Save admin settings *(admin only)* |

---

## 🛡️ Security

- **File type whitelist:** Only `.jar`, `.zip`, `.mrpack`, `.litemod` are allowed
- **Max file size:** 500 MB
- **Path traversal protection:** Filename regex validation
- **Permission check:** Only server owner, subusers, and admins can install files
- **API key masking:** CurseForge key is masked in all API responses

---

## 📜 License

MIT — free to use, modify and distribute.

---

## 🙏 Credits

Built with [Blueprint Framework](https://blueprint.zip/) by the community.
APIs used: [Modrinth](https://modrinth.com), [CurseForge](https://curseforge.com), [Hangar](https://hangar.papermc.io), [Spiget](https://spiget.org)
