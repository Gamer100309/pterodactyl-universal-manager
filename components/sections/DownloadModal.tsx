/**
 * DATEIPFAD: components/sections/DownloadModal.tsx
 *
 * Modal für:
 *   1. Versionsauswahl (Liste aller verfügbaren Versionen)
 *   2. Download-Fortschritt
 *   3. Erfolgs-/Fehlermeldung
 */

import React, { useState, useEffect } from 'react';
import styled, { keyframes } from 'styled-components/macro';
import tw from 'twin.macro';
import http from '@/api/http';
import { SearchResult } from './ResultCard';

// ── Typen ────────────────────────────────────────────────────────────────────

interface VersionFile {
    name:    string;
    url:     string;
    size:    number;
    primary: boolean;
}

interface Version {
    id:             string;
    name:           string;
    version_number: string;
    game_versions:  string[];
    loaders:        string[];
    release_type:   string;
    date:           string;
    downloads:      number;
    files:          VersionFile[];
}

interface Props {
    item:       SearchResult;
    serverUuid: string;
    onClose:    () => void;
}

type Phase = 'loading' | 'select' | 'downloading' | 'success' | 'error';

// ── Animations ───────────────────────────────────────────────────────────────

const fadeIn = keyframes`from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); }`;
const spin   = keyframes`to { transform: rotate(360deg); }`;
const pulse  = keyframes`0%,100% { opacity: 1; } 50% { opacity: .5; }`;

// ── Styling ──────────────────────────────────────────────────────────────────

const Overlay = styled.div`
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.65);
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
`;

const Modal = styled.div`
    ${tw`bg-neutral-800 rounded-xl w-full`}
    max-width: 560px;
    max-height: 85vh;
    display: flex; flex-direction: column;
    animation: ${fadeIn} 0.2s ease;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
`;

const ModalHeader = styled.div`
    ${tw`flex items-start justify-between p-5`}
    border-bottom: 1px solid rgba(255,255,255,0.08);
`;

const ModalTitle = styled.h3`
    ${tw`text-neutral-100 font-semibold text-base m-0`}
`;

const ModalSub = styled.p`
    ${tw`text-neutral-400 text-xs mt-1 m-0`}
`;

const CloseBtn = styled.button`
    ${tw`text-neutral-400 hover:text-neutral-100 transition-colors bg-transparent border-0 cursor-pointer text-xl leading-none`}
    padding: 0; margin: 0;
`;

const ModalBody = styled.div`
    ${tw`flex-1 overflow-y-auto p-5`}
`;

const ModalFooter = styled.div`
    ${tw`p-4 flex gap-3 justify-end`}
    border-top: 1px solid rgba(255,255,255,0.08);
`;

// Versionen-Liste
const VersionList = styled.div`
    ${tw`flex flex-col gap-2`}
`;

const VersionRow = styled.div<{ $selected: boolean }>`
    ${tw`rounded-lg p-3 cursor-pointer transition-all`}
    border: 1.5px solid ${({ $selected }) => $selected ? '#6366f1' : 'rgba(255,255,255,0.07)'};
    background: ${({ $selected }) => $selected ? 'rgba(99,102,241,0.12)' : 'rgba(255,255,255,0.03)'};
    &:hover { border-color: rgba(99,102,241,0.5); }
`;

const VersionName = styled.div`
    ${tw`text-neutral-100 text-sm font-medium`}
`;

const VersionMeta = styled.div`
    ${tw`flex gap-2 flex-wrap mt-1`}
`;

const Tag = styled.span<{ $type?: 'release' | 'beta' | 'alpha' }>`
    ${tw`text-xs px-2 py-0.5 rounded`}
    background: ${({ $type }) => {
        switch ($type) {
            case 'release': return 'rgba(16,185,129,0.2)';
            case 'beta':    return 'rgba(245,158,11,0.2)';
            case 'alpha':   return 'rgba(239,68,68,0.2)';
            default:        return 'rgba(255,255,255,0.1)';
        }
    }};
    color: ${({ $type }) => {
        switch ($type) {
            case 'release': return '#6ee7b7';
            case 'beta':    return '#fcd34d';
            case 'alpha':   return '#fca5a5';
            default:        return '#ccc';
        }
    }};
`;

// Fortschritts-Anzeige
const CenterBox = styled.div`
    ${tw`flex flex-col items-center justify-center py-10 gap-4`}
`;

const SpinnerEl = styled.div`
    width: 40px; height: 40px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: ${spin} 0.8s linear infinite;
`;

const PulseText = styled.p`
    ${tw`text-neutral-400 text-sm text-center`}
    animation: ${pulse} 1.5s ease infinite;
`;

const StatusIcon = styled.div<{ $ok: boolean }>`
    font-size: 48px;
    line-height: 1;
`;

const StatusMsg = styled.p<{ $ok: boolean }>`
    ${({ $ok }) => $ok ? tw`text-green-400` : tw`text-red-400`}
    ${tw`text-sm text-center`}
`;

// Buttons
const Btn = styled.button<{ $variant?: 'primary' | 'ghost' }>`
    ${tw`px-4 py-2 rounded text-sm font-medium transition-colors border-0 cursor-pointer`}
    ${({ $variant }) => $variant === 'ghost'
        ? tw`bg-neutral-700 hover:bg-neutral-600 text-neutral-200`
        : tw`bg-blue-600 hover:bg-blue-500 text-white disabled:bg-neutral-600 disabled:cursor-not-allowed`}
`;

const EmptyText = styled.p`
    ${tw`text-neutral-500 text-sm text-center py-8`}
`;

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────

function formatBytes(bytes: number): string {
    if (!bytes) return '';
    if (bytes > 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1024).toFixed(0) + ' KB';
}

function formatDate(iso: string): string {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return iso.slice(0, 10);
    }
}

// ── Hauptkomponente ──────────────────────────────────────────────────────────

export default function DownloadModal({ item, serverUuid, onClose }: Props) {
    const [phase,    setPhase]    = useState<Phase>('loading');
    const [versions, setVersions] = useState<Version[]>([]);
    const [selected, setSelected] = useState<Version | null>(null);
    const [message,  setMessage]  = useState('');

    // Versionen laden
    useEffect(() => {
        http.get('/api/client/extensions/universalmanager/versions', {
            params: { provider: item.provider, project_id: item.id },
        })
        .then(r => {
            const vers: Version[] = r.data.versions ?? [];
            setVersions(vers);
            if (vers.length > 0) setSelected(vers[0]);
            setPhase('select');
        })
        .catch(() => {
            setMessage('Versionen konnten nicht geladen werden.');
            setPhase('error');
        });
    }, [item]);

    // Download starten
    const handleDownload = async () => {
        if (!selected) return;

        // Dateiname aus der Versions-Datei ermitteln
        const primaryFile = selected.files.find(f => f.primary) ?? selected.files[0];
        const rawFilename = primaryFile?.name || `${item.name}-${selected.version_number}.jar`;
        // Sicherheitsfilter: nur erlaubte Zeichen
        const filename = rawFilename.replace(/[^a-zA-Z0-9 \-_.+()]/g, '_');

        setPhase('downloading');

        try {
            const res = await http.post('/api/client/extensions/universalmanager/download', {
                server_uuid: serverUuid,
                provider:    item.provider,
                project_id:  item.id,
                version_id:  selected.id,
                type:        item.type,
                filename,
            });

            if (res.data.success) {
                setMessage(res.data.message ?? `${filename} erfolgreich installiert!`);
                setPhase('success');
            } else {
                setMessage(res.data.message ?? 'Unbekannter Fehler.');
                setPhase('error');
            }
        } catch (err: any) {
            const msg = err?.response?.data?.message
                     ?? err?.response?.data?.errors?.[0]
                     ?? 'Serverfehler beim Download.';
            setMessage(msg);
            setPhase('error');
        }
    };

    // ── Render ───────────────────────────────────────────────────────────────

    const renderBody = () => {
        switch (phase) {

            case 'loading':
                return (
                    <CenterBox>
                        <SpinnerEl />
                        <PulseText>Versionen werden geladen…</PulseText>
                    </CenterBox>
                );

            case 'select':
                return versions.length === 0 ? (
                    <EmptyText>Keine Versionen verfügbar oder Download nicht erlaubt (Premium?).</EmptyText>
                ) : (
                    <VersionList>
                        {versions.map(v => (
                            <VersionRow
                                key={v.id}
                                $selected={selected?.id === v.id}
                                onClick={() => setSelected(v)}
                            >
                                <VersionName>{v.name || v.version_number}</VersionName>
                                <VersionMeta>
                                    <Tag $type={v.release_type as any}>{v.release_type}</Tag>
                                    {v.game_versions.slice(0, 3).map(gv => (
                                        <Tag key={gv}>MC {gv}</Tag>
                                    ))}
                                    {v.loaders.slice(0, 2).map(l => (
                                        <Tag key={l}>{l}</Tag>
                                    ))}
                                    {v.files[0]?.size > 0 && (
                                        <Tag>{formatBytes(v.files[0].size)}</Tag>
                                    )}
                                    {v.date && (
                                        <Tag style={{ marginLeft: 'auto', opacity: 0.6 }}>
                                            {formatDate(v.date)}
                                        </Tag>
                                    )}
                                </VersionMeta>
                            </VersionRow>
                        ))}
                    </VersionList>
                );

            case 'downloading':
                return (
                    <CenterBox>
                        <SpinnerEl />
                        <PulseText>
                            Wird heruntergeladen und auf dem Server installiert…
                            <br />
                            <small style={{ opacity: 0.6 }}>
                                Große Dateien (Modpacks) können einige Minuten dauern.
                            </small>
                        </PulseText>
                    </CenterBox>
                );

            case 'success':
                return (
                    <CenterBox>
                        <StatusIcon $ok>✅</StatusIcon>
                        <StatusMsg $ok>{message}</StatusMsg>
                        <small style={{ color: '#6b7280', textAlign: 'center' }}>
                            {item.type === 'mod'    && 'Die Datei wurde in /mods/ installiert.'}
                            {item.type === 'plugin' && 'Die Datei wurde in /plugins/ installiert.'}
                            {item.type === 'modpack'&& 'Das Modpack wurde in das Server-Root-Verzeichnis entpackt.'}
                            {item.type === 'datapack' && 'Die Datei wurde in /world/datapacks/ installiert.'}
                            {item.type === 'resourcepack' && 'Die Datei wurde in /resourcepacks/ installiert.'}
                            {' Starte den Server neu, um die Änderungen anzuwenden.'}
                        </small>
                    </CenterBox>
                );

            case 'error':
                return (
                    <CenterBox>
                        <StatusIcon $ok={false}>❌</StatusIcon>
                        <StatusMsg $ok={false}>{message}</StatusMsg>
                    </CenterBox>
                );
        }
    };

    return (
        <Overlay onClick={e => { if (e.target === e.currentTarget) onClose(); }}>
            <Modal>
                <ModalHeader>
                    <div>
                        <ModalTitle>
                            {phase === 'select'      && `Version wählen — ${item.name}`}
                            {phase === 'loading'     && `Lade Versionen — ${item.name}`}
                            {phase === 'downloading' && 'Wird installiert…'}
                            {phase === 'success'     && 'Installation erfolgreich'}
                            {phase === 'error'       && 'Fehler aufgetreten'}
                        </ModalTitle>
                        {phase === 'select' && (
                            <ModalSub>
                                Typ: {item.type} · Provider: {item.provider} · {versions.length} Version(en)
                            </ModalSub>
                        )}
                    </div>
                    <CloseBtn onClick={onClose} title='Schließen'>×</CloseBtn>
                </ModalHeader>

                <ModalBody>{renderBody()}</ModalBody>

                <ModalFooter>
                    {(phase === 'select' || phase === 'error' || phase === 'success') && (
                        <Btn $variant='ghost' onClick={onClose}>
                            {phase === 'success' ? 'Schließen' : 'Abbrechen'}
                        </Btn>
                    )}
                    {phase === 'select' && (
                        <Btn
                            onClick={handleDownload}
                            disabled={!selected || versions.length === 0}
                        >
                            ⬇ Installieren
                        </Btn>
                    )}
                    {phase === 'error' && (
                        <Btn onClick={() => setPhase('select')}>Erneut versuchen</Btn>
                    )}
                </ModalFooter>
            </Modal>
        </Overlay>
    );
}
