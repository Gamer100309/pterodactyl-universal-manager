{{-- DATEIPFAD: admin/index.blade.php
     Blueprint kopiert diese Datei nach:
       resources/views/admin/extensions/universalmanager/index.blade.php
--}}
@extends('layouts.admin')

@section('title')
    Universal Manager — Einstellungen
@endsection

@section('content-header')
    <h1>Universal Manager
        <small>Mods, Plugins, Modpacks, Resource Packs & Datapacks</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="/admin">Admin</a></li>
        <li><a href="/admin/extensions">Extensions</a></li>
        <li class="active">Universal Manager</li>
    </ol>
@endsection

@section('content')
<div class="row">

    {{-- ── Provider-Status ──────────────────────────────────────────── --}}
    <div class="col-xs-12 col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-plug"></i> Provider-Status
                </h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-hover" style="margin:0">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Status</th>
                            <th>Typen</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($providers as $p)
                        <tr>
                            <td><strong>{{ $p['name'] }}</strong></td>
                            <td>
                                @if($p['active'])
                                    <span class="label label-success">Aktiv</span>
                                @else
                                    <span class="label label-warning">Kein API-Key</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ implode(', ', $p['types']) }}</small>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Hilfe-Box --}}
        <div class="box box-info collapsed-box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> Verwendung</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <p>Der Universal Manager erscheint als Tab <strong>"Mods &amp; Plugins"</strong>
                   in der Server-Ansicht der Nutzer.</p>
                <p><strong>Ohne CurseForge API-Key</strong> stehen Modrinth, Hangar und
                   SpigotMC zur Verfügung — vollständig kostenlos.</p>
                <p><strong>Mit CurseForge API-Key</strong> kommen alle CurseForge-Inhalte dazu.</p>
                <hr>
                <p>Einen kostenlosen CurseForge API-Key beantragen:<br>
                   <a href="https://console.curseforge.com/" target="_blank">
                       console.curseforge.com
                   </a>
                </p>
            </div>
        </div>
    </div>

    {{-- ── API-Key Einstellungen ─────────────────────────────────────── --}}
    <div class="col-xs-12 col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-key"></i> API-Schlüssel
                </h3>
            </div>

            <form action="/admin/extensions/universalmanager" method="POST">
                @csrf
                @method('PATCH')

                <div class="box-body">

                    {{-- Flash-Meldungen --}}
                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i> {{ session('success') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)
                                <p><i class="fa fa-times-circle"></i> {{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- CurseForge API-Key --}}
                    <div class="form-group">
                        <label for="curseforge_api_key">
                            CurseForge API-Schlüssel
                            @if($has_cf_key)
                                <span class="label label-success" style="margin-left:8px">Konfiguriert</span>
                            @else
                                <span class="label label-default" style="margin-left:8px">Nicht gesetzt</span>
                            @endif
                        </label>
                        <input
                            type="password"
                            class="form-control"
                            id="curseforge_api_key"
                            name="curseforge_api_key"
                            placeholder="{{ $has_cf_key ? $masked_key : 'API-Key eingeben...' }}"
                            autocomplete="new-password"
                        >
                        <p class="help-block">
                            Lasse das Feld leer, um den bestehenden Key beizubehalten.
                            Gib einen neuen Wert ein, um ihn zu überschreiben.
                            Leere Eingabe + Speichern entfernt den Key.
                        </p>
                    </div>

                </div>

                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Einstellungen speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
