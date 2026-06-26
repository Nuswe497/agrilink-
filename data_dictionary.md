# Agrilink Data Dictionary

This document summarizes the database tables found in `agrilink.sql` and additional SQL setup files. Each entry lists the table name, the number of columns, and a brief description of the table purpose.

| Table Name | Columns | Description |
| --- | --- | --- |
| contributions | 4 | Stores user contributions, including quantity and date. |
| donations | 6 | Records NGO donations with amount, purpose, notes, and timestamp. |
| finance | 6 | Tracks financial transactions, balances, and transaction types for users. |
| hives | 4 | Stores hive inventory linked to users and their locations. |
| inspections | 9 | Records hive inspections, schedule, findings, status, and notes. |
| markets | 5 | Stores market events with location, date, description, and creation time. |
| notifications | 5 | Holds notification messages, titles, timestamps, and targeted roles. |
| orders | 10 | Records user orders, payment status, product references, and totals. |
| password_resets | 5 | Tracks password reset tokens, expiry, and creation timestamps. |
| products | 8 | Stores product catalog details, pricing, stock, status, and image path. |
| profits | 4 | Records profit allocations to users and distribution dates. |
| purchases | 10 | Logs purchases between buyers and suppliers with pricing, fees, and status. |
| shares | 4 | Tracks shares purchased by users and purchase dates. |
| supplier_stock | 8 | Manages supplier inventory items, quantities, prices, and availability. |
| training_materials | 5 | Stores training content with titles, uploaders, and upload timestamps. |
| transfer_history | 9 | Records payment transfers, statuses, methods, and confirmation details. |
| users | 20 | Main user table for accounts, roles, profile details, and payout settings. |
| user_notif_cleared | 2 | Tracks users who have cleared notifications with timestamp. |
| user_notif_read | 4 | Logs user notification read status and read timestamp. |
| pending_registrations | 12 | (setup_paychangu) Holds pending registration entries awaiting payment. |
| payment_transactions | 8 | (setup_paychangu) Logs payment transaction details and statuses. |

> Note: `password_resets` also appears in `member/setup_password_resets.sql`, but it is already present in `agrilink.sql`.
