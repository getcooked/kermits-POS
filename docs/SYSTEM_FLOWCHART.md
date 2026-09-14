# Kermit's System Flowcharts

These diagrams describe the current routes, controllers, and services inspected on September 14, 2026. Open this file in a Markdown viewer with Mermaid support to render the charts. Rounded nodes mark entry or exit points, rectangles show actions, and diamonds show decisions.

## 1. Overall system flow (website)

```mermaid
flowchart TD
    Start([Start]) --> Home[Open website]
    Home --> Account{Have an account?}
    Account -->|No| Register[Register and verify Gmail code]
    Register --> Customer[Customer shop]
    Account -->|Yes| Login[Enter username or email and password]
    Login --> Valid{Login accepted?}
    Valid -->|No| Error[Show error or temporary lockout]
    Error --> Login
    Valid -->|Yes| Role{Account role?}
    Role -->|Customer| Customer
    Role -->|Cashier| Cashier[Cashier POS]
    Role -->|Super admin| Admin[Management dashboard]
    Role -->|Other / legacy admin| Limited[Home page with restricted access]
    Customer --> CustomerTask{Choose activity}
    CustomerTask --> Shop[Order food with a table reservation]
    CustomerTask --> Book[Book a table or exclusive venue]
    CustomerTask --> History[View own history and receipts]
    Shop --> Pending[Pending order and reservation]
    Book --> Review[Super admin reviews reservation]
    Pending --> Review
    Pending --> Payment[Cashier reviews order and confirms or rejects payment]
    Cashier --> Payment
    Cashier --> Sale[Process walk-in sale and issue receipt]
    Admin --> Review
    Admin --> Manage[Manage products, stock, accounts and payment settings]
    Admin --> Reports[View reports and activity logs]
    Admin --> Sale
    Review --> Continue[Continue using system]
    Payment --> Continue
    Sale --> Continue
    History --> Continue
    Manage --> Continue
    Reports --> Continue
    Limited --> Continue
    Continue --> Logout[Log out]
    Logout --> Finish([End session])
```

Registration signs the new customer in automatically. Existing website sessions may resume an intended authorized page instead of the default role page. Legacy `admin` accounts have no administrative page access. Customer-order review and payment confirmation routes are cashier-only; super admins can process direct POS sales.

## 2. Website registration and login

```mermaid
flowchart TD
    Start([Open authentication page]) --> Action{Choose action}
    Action -->|Register| Email[Enter unused Gmail address]
    Email --> Send[Send six-digit verification code]
    Send --> Code[Enter code]
    Code --> Verified{Correct and unexpired?}
    Verified -->|No| Retry[Show error; retry or request new code]
    Retry --> Email
    Verified -->|Yes| Details[Enter account details and confirm password]
    Details --> FormOK{Valid details and matching verified email?}
    FormOK -->|No| Details
    FormOK -->|Yes| Create[Create customer account and sign in]
    Create --> Shop([Customer shop])
    Action -->|Login| Credentials[Enter username or email and password]
    Credentials --> InputOK{Input and enabled reCAPTCHA valid?}
    InputOK -->|No| Credentials
    InputOK -->|Yes| Locked{Temporarily locked out?}
    Locked -->|Yes| Wait[Show retry delay]
    Wait --> Credentials
    Locked -->|No| Match{Credentials match?}
    Match -->|No| Failure[Record failure and show error or lockout]
    Failure --> Credentials
    Match -->|Yes| Session[Clear failures and regenerate session]
    Session --> Destination([Authorized destination for account role])
    Action -->|Forgot password| Reset[Request password reset email]
    Reset --> Link[Open reset link and submit new password]
    Link --> Token{Reset token and password valid?}
    Token -->|No| Link
    Token -->|Yes| Credentials
```

The code sent for registration expires after ten minutes. The password recovery branch summarizes the reset-link flow; the website also provides a separate super-admin recovery entry point.

## 3. Customer order and cashier payment

```mermaid
flowchart TD
    Start([Customer opens shop]) --> Cart[Select menu items and quantities]
    Cart --> Details[Enter table size, schedule and contact details]
    Details --> Method{Payment method?}
    Method -->|Cash| Submit[Submit checkout]
    Method -->|GCash| Proof[Provide reference number and payment proof]
    Proof --> Submit
    Submit --> Validate{Valid input, stock and schedule?}
    Validate -->|No| Error[Show error; roll back any transaction changes]
    Error --> Cart
    Validate -->|Yes| Save[Create pending order and linked pending reservation]
    Save --> Stock[Deduct reserved food stock and record stock movements]
    Stock --> Confirmation[Show customer order confirmation]
    Confirmation --> Review[Cashier opens pending customer order]
    Review --> Decision{Cashier action?}
    Decision -->|Edit items| Edit[Validate quantities; adjust stock and order total]
    Edit --> Review
    Decision -->|Reject| Reject[Reject order and restore reserved stock]
    Reject --> Linked[Update linked reservation status and payment status]
    Linked --> Rejected([Rejected order])
    Decision -->|Confirm payment| Active{Linked reservation still active?}
    Active -->|No| Block[Show error and review reservation status]
    Block --> Review
    Active -->|Yes| PaymentType{Payment method?}
    PaymentType -->|Cash| Cash[Enter cash received]
    Cash --> Enough{Cash covers total due?}
    Enough -->|No| Cash
    Enough -->|Yes| Paid[Mark order and linked reservation payment as paid]
    PaymentType -->|GCash| Reference{Valid 13-digit reference?}
    Reference -->|No| PaymentError[Show payment validation error]
    PaymentError --> Review
    Reference -->|Yes| Paid
    Paid --> ClearHold[Clear linked reservation hold expiry]
    ClearHold --> Receipt([Issue official receipt])
```

- Website shop checkout always creates a linked table reservation. The mobile order API also supports cash orders without a reservation; GCash checkout requires reservation details.
- Uploading GCash proof does not automatically mark payment as paid. Cashier confirmation is a separate action.
- Total due includes the food order and the linked reservation charge. Reserved stock is deducted when the order is created, not again when payment is confirmed.
- Cashier rejection is subject to state checks; an order linked to a completed reservation cannot be rejected. A linked confirmed reservation is cancelled when its order is rejected.

## 4. Reservation booking and approval

```mermaid
flowchart TD
    Start([Signed-in customer opens booking]) --> Type{Reservation type?}
    Type -->|Table| Size[Select table size]
    Type -->|Exclusive venue| Guests[Enter guest count]
    Size --> Details[Enter future schedule and contact details]
    Guests --> Details
    Details --> Food[Optionally select menu items and add notes]
    Food --> Payment[Select cash or GCash; supply required payment details]
    Payment --> Valid{Valid input and available schedule?}
    Valid -->|No| Revise[Show errors and revise booking]
    Revise --> Details
    Valid -->|Yes| Save[Save pending reservation, fees, items and status history]
    Save --> Confirm[Show booking reference and confirmation]
    Confirm --> Pending{Pending reservation outcome}
    Pending -->|Hold expires| Expired([Expired])
    Pending -->|Super admin rejects| Rejected([Rejected])
    Pending -->|Super admin cancels| Cancelled([Cancelled])
    Pending -->|Super admin approves| Recheck{Future schedule and capacity still valid?}
    Recheck -->|No| Error[Show conflict or past-schedule error]
    Error --> Pending
    Recheck -->|Yes| Approved[Confirmed; clear hold and record status history]
    Approved --> Next{Super admin action}
    Next -->|Complete| Completed([Completed])
    Next -->|Cancel| Cancelled
```

Reservation status and payment status are separate. Super-admin approval changes the booking status; it does not mark payment as paid. Cashier payment confirmation for a linked order marks payment as paid without automatically confirming the booking.

Standalone bookings through `/book` or the mobile reservations API store selected food as reservation items and do not deduct product stock or create an order. Shop checkout uses the linked-order workflow in diagram 3. The pending-hold expiry branch applies only while a hold remains eligible to expire. The website booking confirmation uses a temporary signed link; customers can also view their own reservation details and receipts.

## 5. Walk-in POS sale

```mermaid
flowchart TD
    Start([Cashier or super admin opens POS]) --> Cart[Select products and quantities]
    Cart --> Payment[Select payment method and enter payment details]
    Payment --> Valid{Valid cart, sufficient stock and valid payment?}
    Valid -->|No| Error[Show validation error; roll back changes]
    Error --> Cart
    Valid -->|Yes| Save[Create paid order and order items]
    Save --> Stock[Deduct stock and record sale movements]
    Stock --> Receipt[Display printable receipt]
    Receipt --> Reports[Include paid sale in sales reporting]
    Reports --> Finish([Sale complete])
```

## 6. Mobile customer access

```mermaid
flowchart TD
    Start([Open Android app]) --> Login[Submit customer credentials]
    Login --> Valid{Valid credentials and not locked out?}
    Valid -->|No| Error[Show error or retry delay]
    Error --> Login
    Valid -->|Yes| Customer{Customer role and verified email?}
    Customer -->|No| Denied[Reject mobile login]
    Customer -->|Yes| Token[Issue mobile access token]
    Token --> Menu{Choose activity}
    Menu --> Catalog[Browse products and place order]
    Menu --> Booking[Check availability and create reservation]
    Menu --> History[View own orders and reservations]
    Catalog --> Menu
    Booking --> Menu
    History --> Menu
    Menu --> Logout[Log out and revoke current token]
    Logout --> Finish([End session])
```

Mobile registration uses email-code verification before account creation. Staff use the website. Protected API requests authenticate the mobile token; reservation push notifications are supported for registered installations.

## Implementation sources

- [Website routes](../routes/web.php) and [mobile API routes](../routes/api.php)
- [Website authentication](../app/Http/Controllers/AuthController.php), [login validation](../app/Http/Requests/LoginRequest.php), [password recovery](../app/Http/Controllers/PasswordResetController.php), and [role destinations](../app/Models/User.php)
- [Customer checkout](../app/Http/Controllers/CustomerOrderController.php), [order transactions](../app/Services/OrderService.php), and [cashier actions](../app/Http/Controllers/CashierController.php)
- [Reservation booking](../app/Http/Controllers/ReservationController.php), [booking validation](../app/Http/Requests/StoreReservationRequest.php), and [scheduling and status transitions](../app/Services/ReservationSchedule.php)
- [Mobile authentication](../app/Http/Controllers/Api/MobileAuthController.php), [mobile orders](../app/Http/Controllers/Api/MobileOrderController.php), and [mobile reservations](../app/Http/Controllers/Api/MobileReservationController.php)
