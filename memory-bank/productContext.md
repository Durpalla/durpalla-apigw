# Product context — durpalla-apigw

## Consumers

- Customer apps/web → `routes/api/v1/customer.php`
- Merchant apps/web → `merchant.php`
- Agents → `agent.php`
- Supervisors → `supervisor.php`

## Problems solved

- Auth (Passport bearer), profiles, bookings, transport trips/seats, hotels, wallets/commissions, merchant ops
- Consistent JSON envelope: `{ success, message, data }` (login may return `token`/`user` at top level for some clients)

## Product constraints

- API-only Laravel (minimal views/assets)
- Queue third-party calls; transactions for critical booking/payment flows
- Eager load to avoid N+1; Query Builder for heavy datasets
