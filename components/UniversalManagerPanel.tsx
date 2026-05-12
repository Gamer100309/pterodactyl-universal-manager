/**
 * DATEIPFAD: components/UniversalManagerPanel.tsx
 *
 * Haupt-Panel des Universal Managers.
 * Wird via Components.yml als Server-Route "/universalmanager" eingebunden.
 *
 * Aufbau:
 *   ┌─────────────────────────────────────┐
 *   │  Universal Manager                  │
 *   │  [Provider▼] [Typ▼] [Loader▼] [Ver▼]│  ← FilterBar
 *   │  [Suchfeld_______________] [Suchen] │  ← SearchBar
 *   ├─────────────────────────────────────┤
 *   │  Ergebnis 1  │ Ergebnis 2           │
 *   │  Ergebnis 3  │ Ergebnis 4           │  ← ResultGrid (2-spaltig)
 *   │  ...                                │
 *   │  [< Zurück]  Seite 2/5  [Weiter >]  │  ← Pagination
 *   └─────────────────────────────────────┘
 *   + DownloadModal (overlay, wenn "Installieren" geklickt)
 */

import React, { useState, useCallback, useEffect } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

import FilterBar, { FilterState } from './sections/FilterBar';
import SearchBar                  from './sections/SearchBar';
import ResultCard, { SearchResult }from './sections/ResultCard';
import DownloadModal               from './sections/DownloadModal';

// ── Typen ────────────────────────────────────────────────────────────────────

interface ProviderInfo {
    id:               string;
    name:             string;
    supported_types:  string[];
    supported_loaders:string[];
}

interface SearchResponse {
    results:  SearchResult[];
    total:    number;
    page:     number;
    per_page: number;
    error?:   string;
}

// ── Styling ──────────────────────────────────────────────────────────────────

const Page = styled.div`
    ${tw`p-4`}
    max-width: 1100px;
`;

const Title = styled.h2`
    ${tw`text-neutral-100 font-bold text-xl mb-1`}
`;

const Subtitle = styled.p`
    ${tw`text-neutral-400 text-sm mb-5`}
`;

const ResultGrid = styled.div`
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(440px, 1fr));
    gap: 12px;
`;

const StatusBox = styled.div`
    ${tw`flex flex-col items-center justify-center py-16 text-neutral-500`}
    gap: 8px;
`;

const StatusIcon = styled.div`font-size: 48px; line-height: 1;`;
const StatusText = styled.p`${tw`text-sm text-center`}`;

const ErrorBox = styled.div`
    ${tw`bg-red-900 bg-opacity-30 border border-red-700 rounded-lg px-4 py-3 text-red-300 text-sm mb-4`}
`;

const Pagination = styled.div`
    ${tw`flex items-center justify-center gap-4 mt-6`}
`;

const PageBtn = styled.button`
    ${tw`px-4 py-2 rounded bg-neutral-700 hover:bg-neutral-600 text-neutral-200 text-sm border-0 cursor-pointer transition-colors`}
    &:disabled { ${tw`opacity-40 cursor-not-allowed`} }
`;

const PageInfo = styled.span`${tw`text-neutral-400 text-sm`}`;

const TotalInfo = styled.p`${tw`text-neutral-500 text-xs mb-3`}`;

const SettingsBar = styled.div`
    ${tw`flex justify-end mb-2`}
`;

const SettingsBtn = styled.button`
    ${tw`text-xs text-neutral-500 hover:text-neutral-300 transition-colors bg-transparent border-0 cursor-pointer`}
`;

// ── Settings Modal ────────────────────────────────────────────────────────────

interface SettingsModalProps {
    onClose: () => void;
    isAdmin: boolean;
}

const OverlayDiv = styled.div`
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(0,0,0,0.6);
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
`;

const SettingsCard = styled.div`
    ${tw`bg-neutral-800 rounded-xl w-full p-6`}
    max-width: 460px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
`;

function SettingsModal({ onClose, isAdmin }: SettingsModalProps) {
    const [key,     setKey]     = useState('');
    const [saving,  setSaving]  = useState(false);
    const [msg,     setMsg]     = useState('');

    useEffect(() => {
        if (!isAdmin) return;
        http.get('/api/client/extensions/universalmanager/settings')
            .then(r => setKey(r.data.curseforge_api_key ?? ''))
            .catch(() => {});
    }, [isAdmin]);

    const save = async () => {
        setSaving(true); setMsg('');
        try {
            await http.post('/api/client/extensions/universalmanager/settings', {
                curseforge_api_key: key,
            });
            setMsg('✅ Gespeichert!');
        } catch {
            setMsg('❌ Fehler beim Speichern.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <OverlayDiv onClick={e => { if (e.target === e.currentTarget) onClose(); }}>
            <SettingsCard>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
                    <h3 style={{ color: '#f4f4f5', margin: 0, fontSize: 15 }}>⚙️ Universal Manager — Einstellungen</h3>
                    <button onClick={onClose} style={{ background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer', fontSize: 20 }}>×</button>
                </div>

                {!isAdmin ? (
                    <p style={{ color: '#9ca3af', fontSize: 13 }}>Nur Administratoren können die Einstellungen ändern.</p>
                ) : (
                    <>
                        <label style={{ color: '#d1d5db', fontSize: 13, display: 'block', marginBottom: 4 }}>
                            CurseForge API-Schlüssel
                        </label>
                        <input
                            type='password'
                            value={key}
                            onChange={e => setKey(e.target.value)}
                            placeholder='Schlüssel eingeben...'
                            style={{
                                width: '100%', padding: '8px 12px',
                                background: '#3f3f46', border: '1px solid #52525b',
                                borderRadius: 6, color: '#f4f4f5', fontSize: 13,
                                boxSizing: 'border-box', marginBottom: 8,
                            }}
                        />
                        <p style={{ color: '#6b7280', fontSize: 11, marginBottom: 16 }}>
                            API-Key beantragen: <a href='https://console.curseforge.com/' target='_blank' style={{ color: '#818cf8' }}>console.curseforge.com</a>
                        </p>
                        {msg && <p style={{ fontSize: 12, marginBottom: 8, color: msg.startsWith('✅') ? '#6ee7b7' : '#fca5a5' }}>{msg}</p>}
                        <button
                            onClick={save}
                            disabled={saving}
                            style={{
                                width: '100%', padding: '8px', background: saving ? '#4b5563' : '#4f46e5',
                                border: 'none', borderRadius: 6, color: 'white', cursor: saving ? 'not-allowed' : 'pointer',
                                fontSize: 13, fontWeight: 500,
                            }}
                        >
                            {saving ? 'Speichert…' : 'Speichern'}
                        </button>
                    </>
                )}
            </SettingsCard>
        </OverlayDiv>
    );
}

// ── Hauptkomponente ──────────────────────────────────────────────────────────

export default function UniversalManagerPanel() {
    const serverUuid = ServerContext.useStoreState(s => s.server.data!.uuid);
    const isAdmin    = ServerContext.useStoreState(s => s.server.data?.isOwner ?? false);

    // Provider-Liste
    const [providers, setProviders] = useState<ProviderInfo[]>([]);

    // Suchzustand
    const [query,   setQuery]   = useState('');
    const [filters, setFilters] = useState<FilterState>({ provider: '', type: '', loader: '', version: '' });

    // Ergebnisse
    const [results,     setResults]     = useState<SearchResult[]>([]);
    const [total,       setTotal]       = useState(0);
    const [page,        setPage]        = useState(1);
    const [totalPages,  setTotalPages]  = useState(1);
    const [loading,     setLoading]     = useState(false);
    const [hasSearched, setHasSearched] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);

    // Modals
    const [installItem,   setInstallItem]   = useState<SearchResult | null>(null);
    const [showSettings,  setShowSettings]  = useState(false);

    // Provider laden
    useEffect(() => {
        http.get('/api/client/extensions/universalmanager/providers')
            .then(r => setProviders(r.data.providers ?? []))
            .catch(() => {});
    }, []);

    // Suche ausführen
    const doSearch = useCallback(async (searchPage = 1) => {
        if (!query.trim()) return;

        setLoading(true);
        setSearchError(null);

        try {
            const params: Record<string, string | number> = {
                query,
                page: searchPage,
            };
            if (filters.provider) params.provider = filters.provider;
            if (filters.type)     params.type     = filters.type;
            if (filters.loader)   params.loader   = filters.loader;
            if (filters.version)  params.version  = filters.version;

            const res = await http.get<SearchResponse>(
                '/api/client/extensions/universalmanager/search',
                { params }
            );

            const data = res.data;

            if (data.error) {
                setSearchError(data.error);
                setResults([]);
            } else {
                setResults(data.results ?? []);
                setTotal(data.total ?? 0);
                setPage(data.page ?? 1);
                setTotalPages(Math.max(1, Math.ceil((data.total ?? 0) / (data.per_page ?? 20))));
            }

            setHasSearched(true);
        } catch (err: any) {
            const msg = err?.response?.data?.errors?.[0]
                     ?? err?.response?.data?.message
                     ?? 'Suche fehlgeschlagen. Bitte erneut versuchen.';
            setSearchError(msg);
            setResults([]);
        } finally {
            setLoading(false);
        }
    }, [query, filters]);

    const handleSearch = () => {
        setPage(1);
        doSearch(1);
    };

    const handlePageChange = (newPage: number) => {
        setPage(newPage);
        doSearch(newPage);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Ergebnis-Inhalt rendern
    const renderContent = () => {
        if (loading) {
            return (
                <StatusBox>
                    <StatusIcon>⏳</StatusIcon>
                    <StatusText>Durchsuche Provider…</StatusText>
                </StatusBox>
            );
        }

        if (!hasSearched) {
            return (
                <StatusBox>
                    <StatusIcon>🔍</StatusIcon>
                    <StatusText>
                        Suche nach Mods, Plugins, Modpacks, Resource Packs und Datapacks
                        <br />
                        von Modrinth, CurseForge, Hangar und SpigotMC
                    </StatusText>
                </StatusBox>
            );
        }

        if (results.length === 0) {
            return (
                <StatusBox>
                    <StatusIcon>😶</StatusIcon>
                    <StatusText>Keine Ergebnisse für „{query}" gefunden.</StatusText>
                </StatusBox>
            );
        }

        return (
            <>
                <TotalInfo>{total.toLocaleString('de-DE')} Ergebnisse gefunden</TotalInfo>

                <ResultGrid>
                    {results.map(r => (
                        <ResultCard
                            key={`${r.provider}-${r.id}`}
                            result={r}
                            onInstall={setInstallItem}
                        />
                    ))}
                </ResultGrid>

                {totalPages > 1 && (
                    <Pagination>
                        <PageBtn onClick={() => handlePageChange(page - 1)} disabled={page <= 1}>
                            ← Zurück
                        </PageBtn>
                        <PageInfo>Seite {page} / {totalPages}</PageInfo>
                        <PageBtn onClick={() => handlePageChange(page + 1)} disabled={page >= totalPages}>
                            Weiter →
                        </PageBtn>
                    </Pagination>
                )}
            </>
        );
    };

    // ── JSX ──────────────────────────────────────────────────────────────────

    return (
        <Page>
            {/* Header */}
            <SettingsBar>
                <SettingsBtn onClick={() => setShowSettings(true)}>
                    ⚙️ Einstellungen
                </SettingsBtn>
            </SettingsBar>

            <Title>📦 Mods & Plugins</Title>
            <Subtitle>
                Installiere Inhalte aus Modrinth, CurseForge, Hangar und SpigotMC direkt in deinen Server.
            </Subtitle>

            {/* Filter + Suche */}
            <FilterBar
                filters={filters}
                onChange={setFilters}
                providers={providers}
            />
            <SearchBar
                value={query}
                onChange={setQuery}
                onSearch={handleSearch}
                loading={loading}
            />

            {/* Fehlermeldung */}
            {searchError && <ErrorBox>❌ {searchError}</ErrorBox>}

            {/* Ergebnisse */}
            {renderContent()}

            {/* Download-Modal */}
            {installItem && (
                <DownloadModal
                    item={installItem}
                    serverUuid={serverUuid}
                    onClose={() => setInstallItem(null)}
                />
            )}

            {/* Settings-Modal */}
            {showSettings && (
                <SettingsModal
                    isAdmin={isAdmin}
                    onClose={() => setShowSettings(false)}
                />
            )}
        </Page>
    );
}
