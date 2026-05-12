/**
 * DATEIPFAD: components/sections/SearchBar.tsx
 *
 * Suchleiste mit Input-Feld und Such-Button.
 * Unterstützt Enter-Taste und Lade-Indikator.
 */

import React, { useRef } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';

// ── Typen ────────────────────────────────────────────────────────────────────

interface Props {
    value:     string;
    onChange:  (v: string) => void;
    onSearch:  () => void;
    loading:   boolean;
}

// ── Styling ──────────────────────────────────────────────────────────────────

const Wrapper = styled.div`
    ${tw`flex gap-2 mb-4`}
`;

const Input = styled.input`
    ${tw`flex-1 bg-neutral-700 text-neutral-100 border border-neutral-600 rounded px-4 py-2 text-sm`}
    ${tw`focus:outline-none focus:border-blue-400 placeholder-neutral-400`}
`;

const Button = styled.button<{ $loading?: boolean }>`
    ${tw`px-5 py-2 rounded text-sm font-medium text-white transition-colors`}
    ${({ $loading }) => $loading
        ? tw`bg-neutral-600 cursor-not-allowed`
        : tw`bg-blue-600 hover:bg-blue-500 cursor-pointer`}
    min-width: 100px;
    display: flex;
    align-items: center;
    gap: 6px;
    border: none;
`;

const Spinner = styled.span`
    display: inline-block;
    width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;

    @keyframes spin { to { transform: rotate(360deg); } }
`;

// ── Komponente ───────────────────────────────────────────────────────────────

export default function SearchBar({ value, onChange, onSearch, loading }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);

    const handleKey = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !loading) {
            onSearch();
        }
    };

    return (
        <Wrapper>
            <Input
                ref={inputRef}
                type='text'
                placeholder='Mod, Plugin oder Modpack suchen... (z.B. "JEI", "EssentialsX", "RLCraft")'
                value={value}
                onChange={e => onChange(e.target.value)}
                onKeyDown={handleKey}
                autoFocus
            />
            <Button onClick={onSearch} disabled={loading} $loading={loading}>
                {loading ? <><Spinner /> Suche...</> : '🔍 Suchen'}
            </Button>
        </Wrapper>
    );
}
