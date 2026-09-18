# Project brief — durpalla-apigw

## What this is

**Durpalla API Gateway**: Laravel API for customer, merchant, agent, and supervisor mobile/web clients. Same MySQL as main Durpalla; **no app migrations here**.

## Ecosystem

| Repo | Role |
|------|------|
| **durpalla-apigw** (this) | API gateway (`apigw.durpalla.com`) |
| **durpalla** | Admin + **schema owner** (`database/migrations`) |
| **durpalla-web** | Customer web client |
| **durpalla-web-merchant** | Merchant web (`/api/v1/merchant/*`) |
| **durpalla-agent** | Agent Android (`/api/v1` agent auth & wallet) |

## Goals

- Thin, fast API: services + repositories + FormRequests
- Shared Passport keys with main app so tokens validate across services
- Protect production controllers/routes — never wipe API sources

## Non-goals

- Owning schema / shipping migrations (except test-only scaffolding)
- Fat controllers or duplicated business logic

## Requirements source of truth

1. This `memory-bank/`
2. `.cursor/rules/*` (never remove; never wipe API sources)
3. `README.md`
