# Módulo WHMCS: AzuraCast V2

Versão atualizada do módulo de servidor WHMCS para AzuraCast, com arquitetura em camadas (`lib/`) e compatibilidade com PHP 8.3, 8.4 e 8.5.

## Estrutura do módulo

- `azuracast.php` (entrypoint WHMCS)
- `lib/Config.php` (metadados e opções)
- `lib/ApiClient.php` (cliente HTTP AzuraCast)
- `lib/Service.php` (regras de provisionamento/gestão)
- `vendor/autoload.php` (autoload local)
- `composer.json`

## Correções de upgrade aplicadas

- Nome do módulo mantido como **AzuraCast V2**.
- Corrigida a falha de tipagem de nome da estação com normalização segura no construtor do serviço.
- Suporte completo para credenciais no padrão WHMCS:
  - API Key por opção do módulo
  - fallback para `serveraccesshash`
  - fallback para `serverpassword`
- Suporte a URL base por opção ou por Host/IP do servidor WHMCS.
- Suporte a redirecionamento HTTP (301/302/307/308) com `Follow Redirects` e `Max Redirects`.

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


## Fallback de conexão (bugfix)

Quando `API Base URL` não é informada, o módulo tenta conectar usando Host/IP do servidor WHMCS com fallback automático de protocolo (HTTP/HTTPS).
Também foi adicionado `CURLOPT_CONNECTTIMEOUT` para falhas de rede retornarem mais rápido em produção.
