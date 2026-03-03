# Módulo WHMCS: AzuraCast V2

Módulo de automação de servidor para WHMCS, com integração via API administrativa do AzuraCast.

## Recursos implementados

- Provisionamento de estação (`CreateAccount`)
- Suspensão lógica (`SuspendAccount`)
- Reativação lógica (`UnsuspendAccount`)
- Cancelamento definitivo (`TerminateAccount`)
- Alteração de pacote/plano (`ChangePackage`)
- Teste de conexão (`TestConnection`)
- Botões customizados (área admin/cliente):
  - Abrir painel da estação
  - Sincronizar `station_id` por `short_name`

## Instalação

1. Copie a pasta para `modules/servers/azuracast`.
2. No WHMCS, crie/edite um servidor e selecione o tipo **AzuraCast V2**.
3. Associe o produto ao servidor.
4. Crie no produto os custom fields recomendados:
   - `station_id` (persistência do ID retornado pela API)
   - `station_name` (nome da estação no provisionamento)



## Credenciais no padrão WHMCS

Para evitar erro de configuração em produção, o módulo agora segue a convenção WHMCS:

- **URL do AzuraCast**: pode ser definida em `API Base URL` **ou** inferida de `Nome do host/IP` do servidor.
- **API Key**: pode ser definida em `API Key` **ou** lida do campo `Hash de Acesso` do servidor (fallback para senha do servidor).

Com isso, o cenário comum de preencher somente Host + Access Hash funciona normalmente.

## Configuração de endpoints (padrão)

Valores padrão configurados no módulo:

- Create: `POST /api/admin/stations`
- Update: `PUT /api/admin/station/{station_id}`
- Suspend: `PUT /api/admin/station/{station_id}`
- Unsuspend: `PUT /api/admin/station/{station_id}`
- Terminate: `DELETE /api/admin/station/{station_id}`

> Se sua versão do AzuraCast usar caminhos diferentes, ajuste nas opções do módulo.

## Payloads dinâmicos e opções avançadas

O módulo suporta **JSON templates** para criação, suspensão, reativação e mudança de pacote.

Placeholders suportados:

- `{{service_id}}`
- `{{domain}}`
- `{{username}}`
- `{{station_short_name}}`

### Exemplo de Create Payload JSON

```json
{
  "is_public": true,
  "enable_public_page": true,
  "frontend_config": {
    "source_pw": "src_{{service_id}}",
    "admin_pw": "adm_{{service_id}}"
  }
}
```

## Personalização por Custom Fields

Além de `station_id` e `station_name`, você pode usar custom fields do produto com prefixo `azuracast_`:

- `azuracast_name`
- `azuracast_description`
- `azuracast_timezone`
- `azuracast_default_language`
- `azuracast_frontend_type`
- `azuracast_backend_type`
- `azuracast_max_bitrate`
- `azuracast_is_public`
- `azuracast_enable_public_page`
- `azuracast_enable_on_demand`
- `azuracast_enable_streamers`
- `azuracast_record_streams`
- `azuracast_short_name`
- `azuracast_payload_json` (merge JSON completo)

Esses campos são convertidos automaticamente para boolean/número quando aplicável.

## Observações importantes

- O módulo registra todas as chamadas HTTP com `logModuleCall`.
- Use API Key de admin com escopo suficiente para endpoints administrativos.
- `Suspend/Unsuspend` por padrão usam update da estação com `{"is_enabled": false/true}`.
- Para cenários não cobertos, use os payloads JSON de template para enviar qualquer campo aceito pela sua API do AzuraCast.


## Compatibilidade

- PHP 8.3, 8.4 e 8.5
- Sem dependências externas obrigatórias (vendor não é necessário para execução).
