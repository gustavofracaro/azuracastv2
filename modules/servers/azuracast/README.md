# Módulo WHMCS: AzuraCast V2

Versão atualizada do módulo de servidor WHMCS para AzuraCast, com arquitetura em camadas (`lib/`) e compatibilidade com PHP 8.3, 8.4 e 8.5.

## Estrutura do módulo

- `azuracast.php` (entrypoint WHMCS)
- `lib/Config.php` (metadados e opções do produto)
- `lib/ApiClient.php` (cliente HTTP AzuraCast)
- `lib/Service.php` (regras de provisionamento/gestão)
- `vendor/autoload.php` (autoload local)
- `composer.json`

## Regras de conexão (WHMCS)

Nesta versão, os campos `API Base URL` e `API Key` **não aparecem** nas configurações do produto.
A conexão usa os dados do servidor WHMCS cadastrado:

- Hostname/IP do servidor
- Porta do servidor
- Access Hash (ou senha como fallback)

## Correções de upgrade aplicadas

- Nome do módulo mantido como **AzuraCast V2**.
- Corrigida a falha de tipagem de nome da estação com normalização segura no construtor do serviço.
- Suporte a fallback de protocolo HTTP/HTTPS quando necessário.
- Suporte a redirecionamento HTTP (301/302/307/308) com preservação de método (`CURLOPT_POSTREDIR`).
- Timeout de conexão dedicado (`CURLOPT_CONNECTTIMEOUT`) para evitar bloqueios longos.

## Instalação

1. Copie a pasta para `modules/servers/azuracast`.
2. No WHMCS, selecione tipo de servidor **AzuraCast V2**.
3. Associe o produto ao servidor.
4. Crie custom field de produto `station_id`.

## Endpoints padrão

- Create: `POST /api/admin/stations`
- Update: `PUT /api/admin/station/{station_id}`
- Suspend: `PUT /api/admin/station/{station_id}`
- Unsuspend: `PUT /api/admin/station/{station_id}`
- Terminate: `DELETE /api/admin/station/{station_id}`

## Compatibilidade

- PHP 8.3, 8.4, 8.5
- WHMCS (módulo de servidor API v1.1)


- Fallback de API Key ampliado: `serveraccesshash` -> `serverpassword` -> `password` -> `serverusername` -> `username`.


## Resolução de Host/IP (correção)

Para aderir ao padrão WHMCS, o módulo tenta resolver host do servidor por múltiplas chaves (`serverhostname`, `hostname`, `serverip`, `ipaddress`, `servername` e equivalentes em `params["server"]`).

Também é aceito endpoint absoluto (ex.: `https://radio.exemplo.com/api/admin/stations`) para ambientes com proxy/rewrite específico.

- Corrigido erro fatal `Call to undefined method ...::normalizeToken()` com implementação da normalização de Access Hash/API Key.

- Resolução de host reforçada com fallback a `tblservers` via `serverid` quando campos padrão não vierem no `$params`.

- Fallback adicional para servidor WHMCS: quando `serverid` não vier em `$params`, o módulo tenta descobrir por `tblhosting.server` e por `tblproducts.servergroup`/`tblservergroupsrel`.
