# Revisão de Conformidade — WHMCS Provisioning Modules + AzuraCast APIs

Data: 2026-03-03

## Escopo revisado

- `modules/servers/azuracast/azuracast.php`
- `modules/servers/azuracast/lib/Config.php`
- `modules/servers/azuracast/lib/ApiClient.php`
- `modules/servers/azuracast/lib/Service.php`

## Conformidade com o padrão WHMCS (Provisioning Modules)

Checklist validado:

1. **Entry point do módulo**
   - Arquivo principal com prefixo de funções `azuracast_*`.
   - Implementações presentes: `MetaData`, `ConfigOptions`, `CreateAccount`, `SuspendAccount`, `UnsuspendAccount`, `TerminateAccount`, `ChangePackage`, `TestConnection`.

2. **Estrutura de retorno das ações**
   - Operações retornam `success` em caso de êxito.
   - Erros retornam string descritiva.
   - `TestConnection` retorna array com `success/error`.

3. **Uso de parâmetros WHMCS**
   - Consumo de `serviceid`, `pid`, `customfields`, dados de servidor e `configoptionN`.

4. **Persistência de dados de serviço**
   - `station_id` persistido em `tblcustomfieldsvalues` via `Capsule`.

## Conformidade com AzuraCast APIs

1. Endpoints padrão compatíveis com fluxo administrativo:
   - `/api/admin/stations`
   - `/api/admin/station/{station_id}`
2. Header de autenticação API:
   - `X-API-Key`
3. Payload JSON e serialização correta para operações de create/update.
4. Chamadas de leitura para status/listagem:
   - `/api/status`
   - `/api/admin/stations`

## Correção aplicada nesta revisão

- Blindagem adicional no fluxo de redirecionamento manual:
  - adicionada verificação de `maxRedirects <= 0` para evitar recursão indevida em cadeias de 3xx quando `follow redirects` estiver desabilitado.

## Resultado

Módulo revisado e alinhado ao fluxo esperado de provisioning module do WHMCS e uso de API do AzuraCast, com correção defensiva adicional de redirecionamento.

- Ajuste de robustez WHMCS: consulta de `tblservers` por `serverid` como fallback compatível quando host/IP não estiver presente em `$params`.
