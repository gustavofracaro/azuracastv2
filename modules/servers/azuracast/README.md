# Módulo WHMCS: AzuraCast V2

Módulo de servidor para WHMCS com provisionamento de estações AzuraCast usando **somente** dados do servidor WHMCS.

## Importante (corrigido)

As seguintes opções **não aparecem** no produto:

- Validação SSL
- Endpoints
- Payload JSON
- Timeout HTTP
- Redirecionamento

Essas definições são internas/padrão do módulo para evitar inconsistência por produto.

## Fonte de dados para conexão

A chamada para API usa os dados salvos no servidor WHMCS:

- Host/IP/porta/secure
- Access Hash (preferencial)
- Senha/usuário como fallback

Se `$params` vier incompleto, o módulo resolve servidor com fallback via:

1. `serverid`
2. `tblhosting.server` (service id)
3. `tblproducts.servergroup` + `tblservergroupsrel`
4. `tblservers`

## Endpoints internos padrão

- Create: `POST /api/admin/stations`
- Update: `PUT /api/admin/station/{station_id}`
- Suspend/Unsuspend: update `is_enabled`
- Terminate: `DELETE /api/admin/station/{station_id}`

## Compatibilidade

- PHP 8.3, 8.4, 8.5
- WHMCS Provisioning Modules (API v1.1)
