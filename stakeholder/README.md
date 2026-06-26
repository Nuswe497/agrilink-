# Agrilink Stakeholder Portal System

## Overview
The stakeholder portal has been successfully separated into **three independent dashboards** for different stakeholder types in the Livingstonia BeeKeeping Cooperative:

1. **👤 Supplier Dashboard** - For supply chain management
2. **💰 Buyer Dashboard** - For purchasing and order management
3. **🌍 NGO Dashboard** - For cooperative oversight and monitoring

---

## File Structure

```
stakeholder/
├── db.php                    # Database connection (shared)
├── login.php                 # Authentication & routing
├── logout.php                # Session termination
├── supplier_dashboard.php     # Supplier interface
├── buyer_dashboard.php        # Buyer interface
├── ngo_dashboard.php          # NGO oversight interface
├── external_dashboard.php     # Legacy landing page
├── external_dashboard.css     # Shared styles
└── theme.css                  # Additional theme
```

---

## Color Scheme & Design

All dashboards follow a consistent UI/UX design:

- **Primary Color (Green)**: `#2a9d8f` - Headers, titles, primary actions
- **Secondary Color (Orange)**: `#f4a261` - Accents, highlights, secondary buttons
- **Background Gradient**: `linear-gradient(135deg, #fffbe6, #fef6d3)` - Light yellow
- **Cards**: White with subtle shadows
- **Font**: Inter (Google Fonts)

---

## Database Tables Used

### Users Table
```sql
-- Existing users table with new fields:
user_id, name, email, password_hash, role, stakeholder_type
```
- `stakeholder_type` values: `supplier`, `buyer`, `ngo`

### Supplier-Related Tables
```sql
-- Supplier Stock
supplier_stock (id, supplier_id, item_name, quantity, price, description, available, date_added)

-- Purchases (Buyer-Supplier transactions)
purchases (id, buyer_id, supplier_id, item_id, quantity, total_price, purchase_date, status)
```

### Cooperative Monitoring Tables (NGO)
```sql
-- Assumes existing tables:
inspections (inspection_date, apiary_location, notes, hive_health_percentage)
training_sessions (session_date, topic, trainer_name, description)
profit_distribution (amount)
```

---

## Supplier Dashboard Features

### 📦 **Key Functionalities**
- **Stock Management**: Add, view, and manage inventory
- **Order Tracking**: View pending and completed orders
- **Revenue Monitoring**: Track total sales and earnings
- **Order Status Updates**: Mark orders as completed
- **Statistics**: Dashboard showing items, orders, and revenue

### Stats Displayed
- Total Items in stock
- Pending Orders count
- Completed Orders count
- Total Revenue generated

### Actions Available
- ➕ Add new stock items
- ✅ Mark orders as completed
- 📊 View order history and revenue

---

## Buyer Dashboard Features

### 💰 **Key Functionalities**
- **Product Browsing**: View all available products from suppliers
- **Order Placement**: Purchase items with quantity selection
- **Purchase History**: Track all past transactions
- **Order Status**: See pending and completed purchases
- **Supplier Information**: Contact details for each supplier

### Stats Displayed
- Available Products count
- Pending Orders count
- Total Purchases made
- Total Amount Spent

### Actions Available
- 🛒 Place orders for available products
- 📋 View complete purchase history
- 👥 See supplier contact information

---

## NGO Dashboard Features

### 🌍 **Key Functionalities**
- **Cooperative Oversight**: Monitor all cooperative activities
- **Inspection Reports**: View health assessments of beehives
- **Training Sessions**: Track member capacity building activities
- **Statistics**: Key metrics on members, transactions, and profit distribution
- **Performance Metrics**: Monitor transparency, market development, and quality assurance
- **Impact Tracking**: View community benefits and growth

### Stats Displayed
- Active Members count
- Number of Suppliers
- Number of Buyers
- Total Transactions
- Total Transaction Value
- Total Profit Distributed

### Monitoring Capabilities
- Recent inspection reports with hive health percentages
- Scheduled training sessions and topics
- Market performance metrics
- Profit distribution tracking
- Cooperative performance indicators

---

## User Flow & Authentication

### Login Process
1. User logs in at `/stakeholder/login.php`
2. System validates credentials
3. Checks user role and `stakeholder_type`
4. Routes to appropriate dashboard:
   - `external` role + `supplier` type → `supplier_dashboard.php`
   - `external` role + `buyer` type → `buyer_dashboard.php`
   - `external` role + `ngo` type → `ngo_dashboard.php`

### Session Management
- Sessions store: `user_id`, `role`, `stakeholder_type`
- Logout destroys session and redirects to login

---

## Database Integration

### Key Queries Used

**Supplier Dashboard:**
```php
// Fetch supplier inventory
SELECT * FROM supplier_stock WHERE supplier_id = $supplier_id

// Get pending orders
SELECT * FROM purchases WHERE supplier_id = ? AND status = 'pending'

// Calculate revenue
SELECT SUM(total_price) as total_revenue FROM purchases WHERE supplier_id = ? AND status = 'completed'
```

**Buyer Dashboard:**
```php
// Get available products
SELECT * FROM supplier_stock WHERE available = 1

// Track purchases
SELECT * FROM purchases WHERE buyer_id = ?

// Calculate spending
SELECT SUM(total_price) as total_spent FROM purchases WHERE buyer_id = ? AND status = 'completed'
```

**NGO Dashboard:**
```php
// Cooperative statistics
SELECT COUNT(*) as total_transactions, SUM(total_price) as total_value FROM purchases

// Member count
SELECT COUNT(*) as count FROM users WHERE role = 'member'

// Market partners
SELECT COUNT(DISTINCT user_id) FROM users WHERE stakeholder_type = 'buyer/supplier'
```

---

## Styling Features

### Responsive Design
- Mobile-friendly grid layouts
- Auto-adjusting columns for smaller screens
- Flexible card layouts

### Interactive Elements
- Hover effects on cards and buttons
- Modal dialogs for adding items (Supplier)
- Status badges with color coding
- Smooth transitions and animations

### Color-Coded Status Badges
- **Pending**: Yellow background (#fff3cd)
- **Completed**: Green background (#d4edda)
- **Cancelled**: Red background (#f8d7da)

---

## Security Considerations

✅ **Session-based authentication** - Verifies user role and stakeholder type
✅ **SQL injection prevention** - Using prepared statements and escaped queries
✅ **Role-based access control** - Each dashboard is protected by role verification
✅ **Password hashing** - Uses `password_verify()` for secure authentication

---

## Setup Instructions

### Prerequisites
1. Database: `agrilink` with required tables
2. MySQL user: `root` (or update `db.php`)
3. PHP 7.4+ with MySQLi support

### Steps to Deploy
1. Copy all stakeholder files to `/stakeholder/` directory
2. Ensure `db.php` has correct database credentials
3. Verify database tables exist (users, supplier_stock, purchases, etc.)
4. Create test user accounts with stakeholder types:
   ```sql
   INSERT INTO users (name, email, password_hash, role, stakeholder_type) VALUES
   ('Supplier Name', 'supplier@coop.org', <hashed_pwd>, 'external', 'supplier'),
   ('Buyer Name', 'buyer@coop.org', <hashed_pwd>, 'external', 'buyer'),
   ('NGO Name', 'ngo@coop.org', <hashed_pwd>, 'external', 'ngo');
   ```
5. Access at: `http://localhost/Agrilink/stakeholder/login.php`

---

## Future Enhancements

- 📊 Advanced analytics and charts
- 📧 Email notifications for order status changes
- 💾 Export reports (PDF/CSV)
- 🔔 Real-time notifications
- 📱 Mobile app integration
- 🌐 Multi-language support
- 💳 Online payment integration
- 📈 Performance analytics for NGO

---

## Support

For issues or bugs, please contact: **support@coop.org**

---

**Last Updated**: March 28, 2026
**System**: Livingstonia BeeKeeping Cooperative Agrilink Management System
