{if isset($errorMessage)}
<div class="alert alert-danger">{$errorMessage}</div>
{/if}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/azuracastv2/template/css/dashboard.css" />

<div class="azv2-wrap" data-module-link="{$moduleLink}" data-station-id="{$stationId|default:0}">
    <h2>Painel da Rádio - {$stationName|escape}</h2>

    <div class="azv2-stats">
        <div class="card">
            <span class="number">{$listeners|default:0}</span>
            <span class="label">Ouvintes Conectados</span>
        </div>
        <div class="card">
            <span class="number">{$listenersUnique|default:0}</span>
            <span class="label">Ouvintes Únicos</span>
        </div>
        <div class="card">
            <span class="number">{$bitrate|default:0}</span>
            <span class="label">Kbps Stream</span>
        </div>
    </div>

    <div class="azv2-actions">
        <a class="btn btn-success" href="{$adminPanel|escape}" target="_blank" rel="noopener">Login no AzuraCast</a>
        <a class="btn btn-default" href="{$publicPage|escape}" target="_blank" rel="noopener">Abrir Página Pública</a>
    </div>

    <div class="azv2-player">
        <h3>Player HTML5</h3>
        <p><strong>Tocando Agora:</strong> {$nowArtist|escape} - {$nowPlaying|escape}</p>
        <audio controls preload="none" src="{$streamUrl|escape}">
            Seu navegador não suporta reprodução de áudio HTML5.
        </audio>
    </div>

    <div class="azv2-playlists">
        <h3>Playlists</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Ativa</th>
                    <th>Músicas</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$playlists item=playlist}
                    <tr>
                        <td>{$playlist.name|default:'-'|escape}</td>
                        <td>{$playlist.type|default:'-'|escape}</td>
                        <td>{if $playlist.is_enabled}Sim{else}Não{/if}</td>
                        <td>{$playlist.num_songs|default:0}</td>
                    </tr>
                {foreachelse}
                    <tr>
                        <td colspan="4">Nenhuma playlist encontrada.</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>

<script src="{$WEB_ROOT}/modules/servers/azuracastv2/template/js/dashboard.js"></script>
