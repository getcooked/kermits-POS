# Application architecture

## Request flow

1. Laravel middleware starts the session and applies CSRF protection.
2. Guest, authentication, throttling, signed-link, and role middleware reject unauthorized requests early.
3. A Form Request validates and normalizes user input.
4. The controller coordinates the use case and returns a redirect or view.
5. `OrderService` or `InventoryService` performs multi-record writes inside a database transaction.
6. Eloquent models persist records and expose relationships and reusable query scopes.

Controllers should remain small. New validation belongs in a Form Request, and business rules that update multiple records belong in a service transaction.

## Core workflows

### Customer purchase

Customer login -> shop -> validated cart -> atomic pending order -> own order confirmation.

The service locks selected product rows, checks availability and stock, calculates the trusted server-side total, creates the order and items, and decrements stock as one transaction. A failure rolls back the complete order.

### Cashier sale

Cashier or super-admin login -> POS -> validated cart -> atomic paid order -> receipt.

Only paid orders are included in dashboard sales totals and sales reports.

### Inventory

Super-admin -> inventory adjustment -> validation -> locked product update -> stock movement audit record.

Stock-out operations cannot reduce inventory below zero.

### Reservation

Public booking form -> throttled submission -> validated reservation -> signed confirmation link -> super-admin review and status update.

## Role boundary

Customer, cashier, and super-admin routes are separate route groups. Regular admin accounts have no administrative page access. Navigation visibility is only a convenience; authorization is enforced by server-side role middleware on every protected route.

## Development rules

- Do not trust totals, prices, roles, or statuses sent by the browser.
- Do not place SQL or complex business rules in Blade templates.
- Use named routes instead of hard-coded paths.
- Add a feature test for every permission or workflow change.
- Use migrations for all schema changes; never manually alter the production database.
