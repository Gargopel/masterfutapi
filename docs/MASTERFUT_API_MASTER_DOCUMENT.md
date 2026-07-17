# MasterFut API - Documento Mestre para Integracao do FutAI

Este documento resume o estado atual da MasterFut API para remodelar o app FutAI. Ele cobre cadastro, login, API keys, consumo dos dados esportivos, limites, planos e comportamento esperado no programa.

Data do documento: 2026-07-17

## 1. Visao Geral

A arquitetura atual separa responsabilidades:

- FutAI: aplicativo do usuario final. Faz cadastro, login, guarda a API key e consome dados.
- MasterFut API: backend central. Cria usuarios, emite chaves, aplica planos/limites, entrega dados esportivos e registra consumo.
- Admin MasterFut: painel interno. Gerencia providers, syncs, usuarios, chaves, consumo e planos.

O FutAI nao deve expor ao usuario final quais providers externos alimentam a base. Para o usuario, tudo vem da MasterFut API.

## 2. URLs Base

Ambiente de producao esperado:

```txt
https://masterfut.site
```

Base dos endpoints do app FutAI:

```txt
https://masterfut.site/api/app
```

Base dos endpoints esportivos:

```txt
https://masterfut.site/api/v1
```

## 3. Autenticacao e Assinatura do FutAI

A API protegida usa dois fatores tecnicos em conjunto:

- API key do usuario, emitida pela MasterFut.
- Assinatura Ed25519 por dispositivo FutAI.

A API key tem o formato:

```txt
mf_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Enviar em todas as chamadas protegidas:

```http
Authorization: Bearer mf_live_xxx
```

Alternativa aceita:

```http
X-API-Key: mf_live_xxx
```

Tambem enviar em todas as chamadas protegidas:

```http
X-FutAI-Device-Id: uuid-do-dispositivo
X-FutAI-Timestamp: 1784278932
X-FutAI-Nonce: uuid-unico-por-request
X-FutAI-Signature: assinatura-base64
X-FutAI-App-Version: 1.0.0
```

`X-FutAI-App-Version` e opcional, mas recomendado para auditoria e suporte.

### 3.1 Chaves do Dispositivo

No primeiro cadastro ou login que emite chave, o FutAI deve gerar localmente um par Ed25519:

- Chave privada: fica somente no computador do usuario.
- Chave publica: enviada para a MasterFut no campo `public_key`, em Base64.

A chave privada nunca deve ser enviada para a API.

Armazenamento recomendado:

- Windows: DPAPI/Credential Manager.
- macOS: Keychain.
- Linux: Secret Service/libsecret ou cofre equivalente.

### 3.2 Payload Canonico Assinado

Para cada request protegida, o FutAI monta exatamente este texto, separado por `\n`:

```txt
METHOD
/path?query
sha256(body)
timestamp
nonce
device_id
```

Exemplo para `GET /api/v1/matches?league_id=1` sem body:

```txt
GET
/api/v1/matches?league_id=1
e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
1784278932
0f25b277-2db9-4b58-b4aa-703c7f928aaa
9e8d2bd4-4500-42a3-a474-9cfd7ff1413d
```

O FutAI assina esse payload com a chave privada Ed25519 e envia a assinatura em Base64 em `X-FutAI-Signature`.

### 3.3 Erros de Autenticacao

Sem chave ou com chave invalida:

```json
{
  "message": "API key obrigatoria. Envie Authorization: Bearer {token} ou X-API-Key."
}
```

ou:

```json
{
  "message": "API key invalida ou revogada."
}
```

Sem dispositivo ou com dispositivo revogado:

```json
{
  "message": "Dispositivo FutAI obrigatorio para acessar a API.",
  "code": "device_required"
}
```

Assinatura ausente, expirada, repetida ou invalida:

```json
{
  "message": "Assinatura do FutAI invalida.",
  "code": "invalid_device_signature"
}
```

## 4. Fluxo Recomendado no FutAI

### Primeiro acesso

1. Usuario abre FutAI.
2. FutAI mostra cadastro/login.
3. Ao cadastrar, FutAI chama `POST /api/app/register`.
4. A resposta ja traz uma API key.
5. A resposta tambem traz o `device_id`.
6. FutAI salva API key, `device_id` e chave privada localmente de forma segura.
7. FutAI usa a chave e assina cada request para chamar `/api/v1/...`.

### Login em dispositivo novo

1. Usuario informa email/senha.
2. FutAI chama `POST /api/app/login`.
3. Por padrao, o login emite uma nova API key para o dispositivo.
4. Se o usuario ja tiver atingido o limite de chaves, a API retorna erro `api_key_limit_reached`.
5. O FutAI pode perguntar se o usuario quer substituir a chave mais antiga.
6. Se sim, chamar login novamente com `revoke_oldest: true`.

## 5. Endpoints do FutAI

### 5.1 Criar Conta

```http
POST /api/app/register
Content-Type: application/json
```

Body:

```json
{
  "name": "Cliente FutAI",
  "email": "cliente@email.com",
  "password": "senha-segura",
  "password_confirmation": "senha-segura",
  "api_key_name": "FutAI Desktop",
  "device_name": "Desktop principal",
  "platform": "windows",
  "app_version": "1.0.0",
  "public_key": "base64-da-chave-publica-ed25519"
}
```

Regras:

- `name`: obrigatorio, maximo 120 caracteres.
- `email`: obrigatorio, unico, maximo 160 caracteres.
- `password`: obrigatorio, minimo 8 caracteres.
- `password_confirmation`: deve bater com `password`.
- `api_key_name`: opcional, maximo 120 caracteres.
- `device_name`: obrigatorio, maximo 120 caracteres.
- `platform`: opcional, maximo 60 caracteres. Exemplos: `windows`, `linux`, `macos`.
- `app_version`: opcional, maximo 60 caracteres.
- `public_key`: obrigatorio, chave publica Ed25519 em Base64.

Resposta `201`:

```json
{
  "message": "Conta criada com sucesso.",
  "user": {
    "id": 1,
    "name": "Cliente FutAI",
    "email": "cliente@email.com",
    "is_admin": false,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "plan": {
      "id": 1,
      "name": "Free",
      "slug": "free"
    }
  },
  "device": {
    "id": 1,
    "device_id": "9e8d2bd4-4500-42a3-a474-9cfd7ff1413d",
    "name": "Desktop principal",
    "platform": "windows",
    "app_version": "1.0.0",
    "last_used_at": null,
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z"
  },
  "api_key": {
    "id": 1,
    "name": "FutAI Desktop",
    "token": "mf_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_prefix": "mf_live_xxxxxxxx",
    "last_used_at": null,
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "device": {
      "id": 1,
      "device_id": "9e8d2bd4-4500-42a3-a474-9cfd7ff1413d",
      "name": "Desktop principal",
      "platform": "windows",
      "app_version": "1.0.0",
      "last_used_at": null,
      "revoked_at": null,
      "created_at": "2026-07-17T10:00:00.000000Z"
    }
  },
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

Importante: o campo `api_key.token` aparece completo somente na criacao da chave.

### 5.2 Login

```http
POST /api/app/login
Content-Type: application/json
```

Body padrao:

```json
{
  "email": "cliente@email.com",
  "password": "senha-segura",
  "api_key_name": "FutAI Notebook",
  "device_name": "Notebook",
  "platform": "linux",
  "app_version": "1.0.0",
  "public_key": "base64-da-chave-publica-ed25519"
}
```

Quando `issue_api_key` for verdadeiro ou omitido, `device_name` e `public_key` sao obrigatorios.

Resposta `200`:

```json
{
  "message": "Login realizado com sucesso.",
  "user": {
    "id": 1,
    "name": "Cliente FutAI",
    "email": "cliente@email.com",
    "is_admin": false,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "plan": {
      "id": 1,
      "name": "Free",
      "slug": "free"
    }
  },
  "device": {
    "id": 2,
    "device_id": "b02ee8eb-c3e8-42ed-8231-b41f91b41a7f",
    "name": "FutAI Notebook",
    "platform": "linux",
    "app_version": "1.0.0",
    "last_used_at": null,
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z"
  },
  "api_key": {
    "id": 2,
    "name": "FutAI Notebook",
    "token": "mf_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_prefix": "mf_live_xxxxxxxx",
    "last_used_at": null,
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "device": {
      "id": 2,
      "device_id": "b02ee8eb-c3e8-42ed-8231-b41f91b41a7f",
      "name": "FutAI Notebook",
      "platform": "linux",
      "app_version": "1.0.0",
      "last_used_at": null,
      "revoked_at": null,
      "created_at": "2026-07-17T10:00:00.000000Z"
    }
  },
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

Login sem emitir nova chave:

```json
{
  "email": "cliente@email.com",
  "password": "senha-segura",
  "issue_api_key": false
}
```

Resposta nesse caso:

```json
{
  "message": "Login realizado com sucesso.",
  "user": {},
  "api_keys": [
    {
      "id": 1,
      "name": "FutAI Desktop",
      "token": null,
      "token_prefix": "mf_live_xxxxxxxx",
      "last_used_at": "2026-07-17T10:10:00.000000Z",
      "revoked_at": null,
      "created_at": "2026-07-17T10:00:00.000000Z"
    }
  ],
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

Credenciais invalidas:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "Credenciais invalidas."
    ]
  }
}
```

### 5.3 Limite de Chaves no Login

Se o usuario ja tem o maximo de chaves ativas:

```json
{
  "message": "O plano free permite no maximo 3 API keys ativas por usuario.",
  "code": "api_key_limit_reached",
  "user": {},
  "api_keys": [],
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

Status HTTP: `422`.

Para substituir a chave ativa mais antiga:

```json
{
  "email": "cliente@email.com",
  "password": "senha-segura",
  "api_key_name": "FutAI Novo Dispositivo",
  "revoke_oldest": true,
  "device_name": "Novo Dispositivo",
  "platform": "windows",
  "app_version": "1.0.0",
  "public_key": "base64-da-chave-publica-ed25519"
}
```

## 6. Endpoints de Conta e Chaves

Todos abaixo exigem:

```http
Authorization: Bearer mf_live_xxx
X-FutAI-Device-Id: uuid-do-dispositivo
X-FutAI-Timestamp: timestamp-unix
X-FutAI-Nonce: uuid-unico
X-FutAI-Signature: assinatura-base64
```

### 6.1 Perfil Atual

```http
GET /api/app/me
```

Resposta:

```json
{
  "user": {
    "id": 1,
    "name": "Cliente FutAI",
    "email": "cliente@email.com",
    "is_admin": false,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "plan": {
      "id": 1,
      "name": "Free",
      "slug": "free"
    }
  },
  "device": {
    "id": 1,
    "device_id": "9e8d2bd4-4500-42a3-a474-9cfd7ff1413d",
    "name": "Desktop principal",
    "platform": "windows",
    "app_version": "1.0.0",
    "last_used_at": "2026-07-17T10:10:00.000000Z",
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z"
  },
  "current_api_key": {
    "id": 1,
    "name": "FutAI Desktop",
    "token": null,
    "token_prefix": "mf_live_xxxxxxxx",
    "last_used_at": "2026-07-17T10:10:00.000000Z",
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "device": {
      "id": 1,
      "device_id": "9e8d2bd4-4500-42a3-a474-9cfd7ff1413d",
      "name": "Desktop principal",
      "platform": "windows",
      "app_version": "1.0.0",
      "last_used_at": "2026-07-17T10:10:00.000000Z",
      "revoked_at": null,
      "created_at": "2026-07-17T10:00:00.000000Z"
    }
  },
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

### 6.2 Listar API Keys

```http
GET /api/app/api-keys
```

Resposta:

```json
{
  "data": [
    {
      "id": 1,
      "name": "FutAI Desktop",
      "token": null,
      "token_prefix": "mf_live_xxxxxxxx",
      "last_used_at": "2026-07-17T10:10:00.000000Z",
      "revoked_at": null,
      "created_at": "2026-07-17T10:00:00.000000Z",
      "device": {
        "device_id": "9e8d2bd4-4500-42a3-a474-9cfd7ff1413d",
        "name": "Desktop principal",
        "platform": "windows",
        "app_version": "1.0.0"
      }
    }
  ],
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

### 6.3 Criar API Key

```http
POST /api/app/api-keys
Content-Type: application/json
```

Body:

```json
{
  "name": "FutAI Desktop"
}
```

Resposta `201`:

```json
{
  "message": "API key criada com sucesso.",
  "api_key": {
    "id": 2,
    "name": "FutAI Desktop",
    "token": "mf_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_prefix": "mf_live_xxxxxxxx",
    "last_used_at": null,
    "revoked_at": null,
    "created_at": "2026-07-17T10:00:00.000000Z",
    "device": {
      "device_id": "9e8d2bd4-4500-42a3-a474-9cfd7ff1413d",
      "name": "Desktop principal",
      "platform": "windows",
      "app_version": "1.0.0"
    }
  },
  "limits": {
    "active_api_keys": 3,
    "requests_per_minute": 10
  }
}
```

Observacao: a nova chave fica vinculada ao dispositivo usado para fazer esta chamada. Entao `POST /api/app/api-keys` so funciona quando a request atual esta assinada corretamente pelo FutAI.

### 6.4 Revogar API Key

```http
DELETE /api/app/api-keys/{token_id}
```

Resposta:

```json
{
  "message": "API key revogada com sucesso.",
  "api_key": {
    "id": 2,
    "name": "FutAI Desktop",
    "token": null,
    "token_prefix": "mf_live_xxxxxxxx",
    "last_used_at": null,
    "revoked_at": "2026-07-17T10:20:00.000000Z",
    "created_at": "2026-07-17T10:00:00.000000Z"
  }
}
```

## 7. Planos e Limites

Planos sao configurados no admin da MasterFut.

Cada plano pode definir:

- `requests_per_minute`: limite de chamadas por minuto por usuario.
- `max_active_api_keys`: maximo de API keys ativas por usuario.
- `allow_all`: acesso total aos dados.
- Regras de acesso por:
  - `region`: atualmente existe `americas`.
  - `country`: todas as ligas de um pais.
  - `league`: todas as temporadas de uma liga.
  - `season`: uma temporada especifica de uma liga.

Exemplos desejados:

- Free: Brasileiro Serie A 2026.
  - regra tipo `season` apontando para a temporada 2026 da Serie A.
- Plano A: Brasileiro Serie A com todas temporadas.
  - regra tipo `league` apontando para Brasileiro Serie A.
- Plano C: Todas ligas das Americas.
  - regra tipo `region` com valor `americas`.

Comportamento importante:

- Se o plano tem `allow_all = true`, nao ha restricao por liga/temporada.
- Se o plano nao tem regras, atualmente ele fica sem restricao. Isso evita quebrar usuarios existentes antes da configuracao inicial.
- Assim que uma regra e adicionada, a API passa a filtrar os dados retornados.
- Novo usuario cadastrado pelo FutAI entra no plano padrao.

## 8. Como o Plano Afeta a API

Os endpoints abaixo respeitam o plano do usuario:

- `GET /api/v1/countries`
- `GET /api/v1/leagues`
- `GET /api/v1/seasons`
- `GET /api/v1/teams`
- `GET /api/v1/matches`
- `GET /api/v1/matches/{match}`
- `GET /api/v1/standings`
- `GET /api/v1/stats/summary`
- `GET /api/v1/metadata`

Se o usuario tentar acessar o detalhe de uma partida fora do plano:

```json
{
  "message": "This action is unauthorized."
}
```

Status HTTP: `403`.

Para listas, a API apenas retorna os itens permitidos pelo plano.

## 9. Rate Limit

O limite e aplicado por usuario, somando todas as API keys ativas.

Exemplo:

- Plano Free: `requests_per_minute = 10`.
- Usuario tem 2 chaves ativas.
- As duas chaves juntas podem fazer 10 requests por minuto.

Quando estoura:

```json
{
  "message": "Limite de 10 requisicoes por minuto atingido.",
  "retry_after": 37
}
```

Headers:

```http
Retry-After: 37
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
```

## 10. Endpoints Esportivos

Todos exigem API key.
Todos tambem exigem assinatura do dispositivo FutAI.

### 10.1 Metadata

```http
GET /api/v1/metadata
```

Resposta:

```json
{
  "api_version": "v1",
  "plan": {
    "restricted": true,
    "allowed_league_ids": [1],
    "allowed_season_ids": [10]
  },
  "totals": {
    "sports": 1,
    "leagues": 1,
    "seasons": 1,
    "teams": 20,
    "matches": 380
  },
  "last_sync_at": "2026-07-17T10:00:00.000000Z",
  "freshness": {
    "last_successful_sync_at": "2026-07-17T09:50:00.000000Z",
    "last_data_refresh_at": "2026-07-17T10:00:00.000000Z",
    "running_updates_count": 0
  },
  "supported_languages": ["pt-BR", "en", "es", "zh"]
}
```

### 10.2 Sports

```http
GET /api/v1/sports
```

Retorna lista paginada de esportes ativos.

### 10.3 Countries

```http
GET /api/v1/countries
```

Retorna paises disponiveis dentro do plano.

### 10.4 Leagues

```http
GET /api/v1/leagues
```

Filtros:

- `sport`: slug ou id do esporte.
- `country`: codigo ou id do pais.
- `active`: boolean.
- `updated_since`: data.

Exemplo:

```http
GET /api/v1/leagues?country=BR&active=1
Authorization: Bearer mf_live_xxx
X-FutAI-Device-Id: uuid-do-dispositivo
X-FutAI-Timestamp: timestamp-unix
X-FutAI-Nonce: uuid-unico
X-FutAI-Signature: assinatura-base64
```

### 10.5 Seasons

```http
GET /api/v1/seasons
```

Filtros:

- `updated_since`: data.

Observacao: retorna somente temporadas permitidas pelo plano.

### 10.6 Teams

```http
GET /api/v1/teams
```

Filtros:

- `sport`: slug ou id.
- `country`: codigo ou id.
- `league_id`: id da liga.
- `updated_since`: data.

### 10.7 Matches

```http
GET /api/v1/matches
```

Filtros:

- `league_id`
- `season_id`
- `team_id`
- `status`
- `date_from`
- `date_to`
- `updated_since`

Exemplo:

```http
GET /api/v1/matches?league_id=1&season_id=10&status=finished
Authorization: Bearer mf_live_xxx
X-FutAI-Device-Id: uuid-do-dispositivo
X-FutAI-Timestamp: timestamp-unix
X-FutAI-Nonce: uuid-unico
X-FutAI-Signature: assinatura-base64
```

### 10.8 Match Detail

```http
GET /api/v1/matches/{match_id}
```

Retorna detalhe da partida se ela estiver dentro do plano do usuario.

### 10.9 Standings

```http
GET /api/v1/standings
```

Filtros:

- `league_id`
- `season_id`
- `updated_since`

### 10.10 Summary

```http
GET /api/v1/stats/summary
```

Retorna totais respeitando o plano.

## 11. Paginacao

Listagens usam paginacao do Laravel.

Formato comum:

```json
{
  "current_page": 1,
  "data": [],
  "first_page_url": "...",
  "from": 1,
  "last_page": 1,
  "last_page_url": "...",
  "links": [],
  "next_page_url": null,
  "path": "...",
  "per_page": 50,
  "prev_page_url": null,
  "to": 10,
  "total": 10
}
```

No FutAI, tratar sempre `data` como lista e usar `next_page_url` ou `current_page`/`last_page` para navegar.

## 12. Campos Internos Ocultados

A API publica esconde campos internos como:

- `external_provider_id`
- `external_id`
- `raw_payload`

O FutAI nao deve depender de provider externo nem tentar identificar origem dos dados.

## 13. Fluxo de Armazenamento Local no FutAI

Recomendado:

1. Salvar `api_key.token` com seguranca.
2. Salvar `device.device_id`.
3. Salvar a chave privada Ed25519 no cofre seguro do sistema operacional.
4. Salvar dados basicos do usuario:
   - `id`
   - `name`
   - `email`
   - `plan.slug`
   - `plan.name`
5. Ao abrir o app, chamar `GET /api/app/me` com API key e assinatura.
6. Se responder `401`, pedir login novamente e gerar novo par de chaves se necessario.
7. Se responder `429`, mostrar aguarde e usar `retry_after`.

## 14. Tratamento de Erros no FutAI

### 401

Chave ausente, invalida, revogada, dispositivo ausente ou assinatura invalida.

Acao recomendada:

- limpar chave local;
- limpar `device_id` e chave privada local se o erro indicar dispositivo invalido;
- pedir login novamente.

### 403

Usuario autenticado, mas plano nao permite aquele recurso.

Acao recomendada:

- mostrar mensagem de upgrade/plano;
- nao tratar como logout.

### 422

Erro de validacao ou limite de chaves.

Se `code = api_key_limit_reached`:

- mostrar dispositivos/chaves ativas se disponivel;
- oferecer substituir a chave mais antiga via login com `revoke_oldest: true`.

### 429

Rate limit.

Acao recomendada:

- respeitar `retry_after`;
- fazer backoff;
- evitar loops agressivos de sincronizacao.

## 15. Checklist para Remodelar o FutAI

- Criar tela de cadastro chamando `POST /api/app/register`.
- Criar tela de login chamando `POST /api/app/login`.
- Gerar par Ed25519 por dispositivo.
- Enviar `device_name`, `platform`, `app_version` e `public_key` no cadastro/login.
- Guardar `api_key.token` retornado.
- Guardar `device.device_id` retornado.
- Guardar a chave privada somente no dispositivo.
- Assinar toda request protegida com `X-FutAI-*`.
- Implementar `GET /api/app/me` ao iniciar o app.
- Implementar tratamento de `401`, `403`, `422`, `429`.
- Consumir dados sempre via `/api/v1`.
- Nunca exibir providers externos ao usuario.
- Mostrar plano atual usando `user.plan.name`.
- Usar `limits.requests_per_minute` para controlar chamadas do app.
- Usar `limits.active_api_keys` para explicar limite de dispositivos/chaves.
- Em caso de limite de chaves, permitir login com `revoke_oldest: true`.

## 16. Endpoints Admin Relacionados a Planos

Uso interno do painel admin, nao do FutAI:

```http
GET /admin/api/plans
POST /admin/api/plans
PATCH /admin/api/plans/{plan}
GET /admin/api/plan-options
PATCH /admin/api/users/{user}/plan
```

O admin define:

- plano padrao;
- limites;
- regras de acesso;
- plano de cada usuario.

## 17. Estado Atual dos Testes

Na ultima validacao antes deste documento:

- `php artisan test`: 54 testes passando.
- `npm run build`: passando.
- `npm run typecheck`: passando.

Este documento nao foi commitado por solicitacao do projeto. Ele serve como guia de implementacao para remodelar o FutAI.
