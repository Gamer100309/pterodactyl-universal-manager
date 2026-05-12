/**
 * DATEIPFAD: components/sections/FilterBar.tsx
 *
 * Filterleiste mit: Provider · Typ · Loader · Minecraft-Version
 * Wird von UniversalManagerPanel genutzt.
 */

import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';

// ── Typen ────────────────────────────────────────────────────────────────────

export interface FilterState {
    provider: string;
    type:     string;
    loader:   string;
    version:  string;
}

interface Props {
    filters:   FilterState;
    onChange:  (filters: FilterState) => void;
    providers: Array<{ id: string; name: string; supported_types: string[] }>;
}

// ── Statische Daten ──────────────────────────────────────────────────────────

const CONTENT_TYPES = [
    { value: '',            label: 'Alle Typen'    },
    { value: 'mod',         label: '⚙️  Mod'        },
    { value: 'plugin',      label: '🔌 Plugin'      },
    { value: 'modpack',     label: '📦 Modpack'     },
    { value: 'resourcepack',label: '🎨 Resource Pack'},
    { value: 'datapack',    label: '📂 Datapack'    },
];

const MOD_LOADERS = [
    { value: '',           label: 'Alle Loader'   },
    // Mod-Loader
    { value: 'fabric',     label: 'Fabric'        },
    { value: 'forge',      label: 'Forge'         },
    { value: 'neoforge',   label: 'NeoForge'      },
    { value: 'quilt',      label: 'Quilt'         },
    { value: 'liteloader', label: 'LiteLoader'    },
    { value: 'rift',       label: "Rift"          },
    { value: 'modloader',  label: "Risugami's ModLoader" },
    // Plugin-Plattformen
    { value: 'paper',      label: 'Paper'         },
    { value: 'spigot',     label: 'Spigot'        },
    { value: 'bukkit',     label: 'Bukkit'        },
    { value: 'purpur',     label: 'Purpur'        },
    { value: 'folia',      label: 'Folia'         },
    { value: 'velocity',   label: 'Velocity'      },
    { value: 'waterfall',  label: 'Waterfall'     },
    { value: 'bungeecord', label: 'BungeeCord'    },
    { value: 'sponge',     label: 'Sponge'        },
];

const MC_VERSIONS = [
    '', '1.21.4', '1.21.3', '1.21.1', '1.21',
    '1.20.6', '1.20.4', '1.20.2', '1.20.1', '1.20',
    '1.19.4', '1.19.2', '1.18.2', '1.17.1', '1.16.5',
    '1.15.2', '1.12.2', '1.8.9', '1.7.10',
];

// ── Styling ──────────────────────────────────────────────────────────────────

const Bar = styled.div`
    ${tw`flex flex-wrap gap-2 mb-4`}
`;

const Select = styled.select`
    ${tw`bg-neutral-700 text-neutral-100 border border-neutral-600 rounded px-3 py-2 text-sm`}
    ${tw`focus:outline-none focus:border-blue-400`}
    min-width: 140px;
    cursor: pointer;
`;

// ── Komponente ───────────────────────────────────────────────────────────────

export default function FilterBar({ filters, onChange, providers }: Props) {
    const set = (key: keyof FilterState) => (e: React.ChangeEvent<HTMLSelectElement>) => {
        onChange({ ...filters, [key]: e.target.value });
    };

    // Provider-Liste: "Alle" + jeden registrierten Provider
    const providerOptions = [
        { value: '', label: 'Alle Provider' },
        ...providers.map(p => ({ value: p.id, label: p.name })),
    ];

    // Wenn ein Provider gewählt ist, nur passende Typen anzeigen
    const activeProvider  = providers.find(p => p.id === filters.provider);
    const availableTypes  = activeProvider
        ? CONTENT_TYPES.filter(t => !t.value || activeProvider.supported_types.includes(t.value))
        : CONTENT_TYPES;

    return (
        <Bar>
            {/* Provider */}
            <Select value={filters.provider} onChange={set('provider')}>
                {providerOptions.map(o => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </Select>

            {/* Inhaltstyp */}
            <Select value={filters.type} onChange={set('type')}>
                {availableTypes.map(t => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                ))}
            </Select>

            {/* Loader / Plattform */}
            <Select value={filters.loader} onChange={set('loader')}>
                {MOD_LOADERS.map(l => (
                    <option key={l.value} value={l.value}>{l.label}</option>
                ))}
            </Select>

            {/* Minecraft-Version */}
            <Select value={filters.version} onChange={set('version')}>
                <option value=''>Alle Versionen</option>
                {MC_VERSIONS.filter(v => v).map(v => (
                    <option key={v} value={v}>{v}</option>
                ))}
            </Select>
        </Bar>
    );
}
