/**
 * DATEIPFAD: components/sections/ResultCard.tsx
 *
 * Eine einzelne Ergebniskarte in der Suchergebnisliste.
 * Zeigt Icon, Name, Beschreibung, Typ-Badge, Download-Zahl und Provider.
 */

import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';

// ── Typen ────────────────────────────────────────────────────────────────────

export interface SearchResult {
    id:          string;
    name:        string;
    description: string;
    type:        string;   // mod | plugin | modpack | resourcepack | datapack
    icon:        string;
    downloads:   number;
    loaders:     string[];
    versions:    string[];
    externalUrl: string;
    provider:    string;   // modrinth | curseforge | hangar | spiget
}

interface Props {
    result:    SearchResult;
    onInstall: (result: SearchResult) => void;
}

// ── Styling ──────────────────────────────────────────────────────────────────

const Card = styled.div`
    ${tw`bg-neutral-700 rounded-lg p-4 flex gap-3 hover:bg-neutral-650 transition-colors`}
    border: 1px solid rgba(255,255,255,0.05);
`;

const Icon = styled.img`
    ${tw`rounded flex-shrink-0`}
    width: 56px; height: 56px;
    object-fit: cover;
    background: #3f3f46;
`;

const IconPlaceholder = styled.div`
    ${tw`rounded flex-shrink-0 bg-neutral-600 flex items-center justify-center text-2xl`}
    width: 56px; height: 56px;
`;

const Body = styled.div`
    ${tw`flex-1 min-w-0`}
`;

const Header = styled.div`
    ${tw`flex items-start justify-between gap-2`}
`;

const Name = styled.span`
    ${tw`font-semibold text-neutral-100 text-sm leading-tight`}
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
`;

const BadgeRow = styled.div`
    ${tw`flex gap-1 flex-wrap mt-1`}
`;

const Badge = styled.span<{ $color?: string }>`
    ${tw`text-xs px-2 py-0.5 rounded-full font-medium`}
    background: ${({ $color }) => $color ?? 'rgba(255,255,255,0.1)'};
    color: white;
`;

const Description = styled.p`
    ${tw`text-neutral-400 text-xs mt-1`}
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
`;

const Footer = styled.div`
    ${tw`flex items-center justify-between mt-2 gap-2`}
`;

const Meta = styled.span`
    ${tw`text-neutral-500 text-xs`}
`;

const Actions = styled.div`
    ${tw`flex gap-2`}
`;

const Btn = styled.button<{ $variant?: 'primary' | 'ghost' }>`
    ${tw`text-xs px-3 py-1 rounded font-medium transition-colors border-0 cursor-pointer`}
    ${({ $variant }) => $variant === 'ghost'
        ? tw`bg-transparent text-neutral-400 hover:text-neutral-100`
        : tw`bg-blue-600 hover:bg-blue-500 text-white`}
`;

// ── Provider-Farben ──────────────────────────────────────────────────────────

const PROVIDER_COLORS: Record<string, string> = {
    modrinth:   '#1bd96a',
    curseforge: '#f16436',
    hangar:     '#0ea5e9',
    spiget:     '#fbbf24',
};

const TYPE_COLORS: Record<string, string> = {
    mod:          '#6366f1',
    plugin:       '#10b981',
    modpack:      '#f59e0b',
    resourcepack: '#8b5cf6',
    datapack:     '#ec4899',
};

const TYPE_ICONS: Record<string, string> = {
    mod:          '⚙️',
    plugin:       '🔌',
    modpack:      '📦',
    resourcepack: '🎨',
    datapack:     '📂',
};

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────

function formatDownloads(n: number): string {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(0)     + 'K';
    return n.toString();
}

// ── Komponente ───────────────────────────────────────────────────────────────

export default function ResultCard({ result, onInstall }: Props) {
    const providerColor = PROVIDER_COLORS[result.provider] ?? '#888';
    const typeColor     = TYPE_COLORS[result.type]         ?? '#666';
    const typeIcon      = TYPE_ICONS[result.type]          ?? '📄';

    return (
        <Card>
            {/* Icon */}
            {result.icon ? (
                <Icon
                    src={result.icon}
                    alt={result.name}
                    onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
            ) : (
                <IconPlaceholder>{typeIcon}</IconPlaceholder>
            )}

            <Body>
                <Header>
                    <Name title={result.name}>{result.name}</Name>
                </Header>

                <BadgeRow>
                    {/* Typ-Badge */}
                    <Badge $color={typeColor}>
                        {typeIcon} {result.type}
                    </Badge>

                    {/* Provider-Badge */}
                    <Badge $color={providerColor}>
                        {result.provider}
                    </Badge>

                    {/* Loader-Badges (max 3) */}
                    {result.loaders.slice(0, 3).map(l => (
                        <Badge key={l}>{l}</Badge>
                    ))}
                </BadgeRow>

                <Description>{result.description || 'Keine Beschreibung verfügbar.'}</Description>

                <Footer>
                    <Meta>
                        {result.downloads > 0 && (
                            <span>⬇ {formatDownloads(result.downloads)} Downloads</span>
                        )}
                        {result.versions.length > 0 && (
                            <span style={{ marginLeft: 8 }}>
                                MC {result.versions[0]}
                                {result.versions.length > 1 && ` – ${result.versions[result.versions.length - 1]}`}
                            </span>
                        )}
                    </Meta>

                    <Actions>
                        {result.externalUrl && (
                            <Btn
                                $variant='ghost'
                                onClick={() => window.open(result.externalUrl, '_blank')}
                                title='Auf der Plattform ansehen'
                            >
                                🔗
                            </Btn>
                        )}
                        <Btn onClick={() => onInstall(result)}>
                            Installieren
                        </Btn>
                    </Actions>
                </Footer>
            </Body>
        </Card>
    );
}
