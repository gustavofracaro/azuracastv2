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
2. Inclusão/recriação de `lib/Service.php` (referência compatível com logs de produção).
3. Correção de falha de tipagem de nome da estação:
   - `stationName` sempre inicializado com string não nula.
4. Compatibilidade com credenciais padrão WHMCS (`API Key`, `serveraccesshash`, `serverpassword`).
5. Suporte a redirecionamentos HTTP (301/302/307/308).

## Compatibilidade técnica

- PHP 8.3 / 8.4 / 8.5: compatível.
- WHMCS server module API v1.1: compatível.
- Sem dependências Composer externas obrigatórias.

## Observação operacional

Com opcache desabilitado, a nova estrutura reduz risco de manter código antigo em memória.
Mesmo assim, recomenda-se deploy limpo da pasta `modules/servers/azuracast`.


## Correção adicional do bug de conexão

- Corrigido cenário de `Failed to connect ... port 80` com fallback automático de URL base quando `API Base URL` está vazia.
- O cliente agora tenta mais de um candidato de URL (HTTP/HTTPS) em erro de conexão e registra a URL tentada em `logModuleCall`.
- Incluído `CURLOPT_CONNECTTIMEOUT` para reduzir bloqueios longos de conexão.
