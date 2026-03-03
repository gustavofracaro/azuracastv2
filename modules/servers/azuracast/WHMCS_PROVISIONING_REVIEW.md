# Revisão de Conformidade — WHMCS Provisioning Modules

Checklist validado:

- Arquivo principal com funções `azuracast_*`.
- `MetaData` com `RequiresServer=true`.
- Operações WHMCS implementadas e com retorno compatível.
- Uso de dados de servidor WHMCS para autenticação e destino da API.
- Persistência de `station_id` via `Capsule`.

Ajuste principal desta revisão:

- Resolução de servidor em cadeia de fallback (incluindo tabelas do WHMCS) para evitar erro de host/IP ausente mesmo quando `$params` não vier completo no runtime.
