# Relatório de Varredura e Compatibilidade — AzuraCast V2 (Upgrade)

Data da análise: 2026-03-03

## Varredura do repositório

Arquivos atuais do módulo:

- `modules/servers/azuracast/azuracast.php`
- `modules/servers/azuracast/lib/Config.php`
- `modules/servers/azuracast/lib/ApiClient.php`
- `modules/servers/azuracast/lib/Service.php`
- `modules/servers/azuracast/vendor/autoload.php`
- `modules/servers/azuracast/composer.json`
- `modules/servers/azuracast/README.md`

## Correções aplicadas no upgrade

1. Reestruturação para arquitetura em camadas (`Config`, `ApiClient`, `Service`).
2. Correção de falha de tipagem de nome da estação (`stationName` sempre string não nula).
3. Uso exclusivo de credenciais/host do servidor WHMCS (sem campos de URL/API Key no produto).
4. Correção de falhas de conexão com fallback HTTP/HTTPS e `CURLOPT_CONNECTTIMEOUT`.
5. Correção de `HTTP 308 Permanent Redirect` no Create com suporte a follow redirect + `CURLOPT_POSTREDIR`.

## Compatibilidade técnica

- PHP 8.3 / 8.4 / 8.5: compatível.
- WHMCS server module API v1.1: compatível.
- Sem dependências Composer externas obrigatórias.

## Observação operacional

Mesmo com opcache desabilitado, recomenda-se deploy limpo da pasta `modules/servers/azuracast` para evitar arquivos legados.
