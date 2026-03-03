# Relatório de Compatibilidade — AzuraCast V2

- Revisão focada na documentação de Provisioning Modules do WHMCS e APIs do AzuraCast.
- Módulo usa entry points padrão (`CreateAccount`, `SuspendAccount`, `UnsuspendAccount`, `TerminateAccount`, `ChangePackage`, `TestConnection`).
- Retornos seguem padrão WHMCS (`success` ou mensagem de erro; `TestConnection` com `success/error`).

## Correções críticas aplicadas

1. Remoção de opções técnicas do produto (SSL/Endpoint/Payload/Timeout/Redirect).
2. Uso exclusivo de configuração do servidor WHMCS para conexão.
3. Fallback robusto de servidor quando `$params` vier incompleto:
   - `serverid` -> `tblhosting.server` -> `tblproducts.servergroup/tblservergroupsrel` -> `tblservers`.
4. Fallback robusto de credencial:
   - `accesshash`/`serveraccesshash` -> senha -> usuário.

Resultado: elimina a recorrência do erro de host/IP ausente em serviços com vinculação indireta de servidor.
