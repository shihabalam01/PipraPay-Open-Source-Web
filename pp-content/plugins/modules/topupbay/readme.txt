=== TopupBay ===
Contributors:TopupBay
Donate link: 
Tags: transaction, api, webhook, topupbay
Requires at least: 1.0
Tested up to: 1.0
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

TopupBay is a transaction management module for PipraPay that provides a complete API system for managing transactions. It creates its own database table (tb_transactions) and provides secure API endpoints for creating and retrieving transactions.

== Features ==

* Secure API key authentication
* RESTful API endpoints (GET and POST)
* Custom database table for transactions
* Webhook support for transaction notifications
* Metadata support for custom data storage
* Admin interface for API key management
* Transaction listing in admin panel
* Automatic verification against PipraPay transaction database (similar to PipraPay's transaction panel)
* Cross-reference TopupBay transactions with main PipraPay transactions

== Installation ==

1. Upload the plugin files to the `/pp-content/plugins/modules/topupbay/` directory
2. Activate the plugin through the 'Modules' screen in PipraPay admin panel
3. Go to More -> Modules -> TopupBay
4. Set your API key
5. Start using the API endpoints

== API Usage ==

=== GET Request - Fetch All Transactions ===

```
GET /pp-content/plugins/modules/topupbay/api.php?topupbay-api=1&api_key=YOUR_API_KEY
```

Response:
```json
{
  "status": true,
  "count": 10,
  "data": [
    {
      "id": 1,
      "customer": "John Doe",
      "payment_method": "bKash",
      "transaction_amount": "100.00",
      "transaction_currency": "BDT",
      ...
    }
  ]
}
```

=== POST Request - Create Transaction ===

```
POST /payment/?topupbay-api=1&api_key=YOUR_API_KEY
Content-Type: application/json

{
  "customer": "John Doe",
  "payment_method": "bKash",
  "transaction_amount": "100.00",
  "transaction_currency": "BDT",
  "payment_sender_number": "01712345678",
  "transaction_id": "TXN123456",
  "transaction_status": "completed",
  "transaction_webhook": "https://example.com/webhook",
  "transaction_metadata": {
    "order_id": "ORD-001",
    "user_id": "123"
  },
  "product_name": "Product Name"
}
```
Required Fields:
- customer
- transaction_amount
- transaction_currency
- transaction_status

Optional Fields:
- payment_method
- payment_sender_number
- transaction_id
- transaction_webhook
- transaction_metadata (JSON object or string)
- product_name

== Transaction Verification ==

TopupBay automatically verifies transactions against PipraPay's SMS data (`pp_sms_data` table), similar to how PipraPay verifies transactions from SMS data.

Verification checks the following fields:
1. **Payment Method**: Matches `payment_method` field
2. **Transaction ID**: Matches `transaction_id` field
3. **Mobile Number**: Matches `mobile_number` field (from `payment_sender_number` in TopupBay)
4. **Amount**: Verifies the amount matches (with tolerance support)

Verification Methods (in order of priority):
1. **Full Match**: Transaction ID + Payment Method + Amount (+ Mobile Number if provided)
2. **Mobile + Amount**: Mobile Number + Payment Method + Amount (if transaction ID not found)
3. **Method + Amount**: Payment Method + Amount only (last resort)

Verification Status:
* **Verified** (Green): Transaction fully matches SMS data
* **Not Verified** (Yellow): Partial match found (some fields match but not all)
* **Not Checked** (Gray): No matching SMS data found

The verification status is displayed in the admin panel's transaction table, showing the SMS data ID and transaction ID if matched.

== Database Schema ==

The plugin creates a table `tb_transactions` with the following fields:

* id (INT, Primary Key, Auto Increment)
* customer (VARCHAR)
* payment_method (VARCHAR)
* transaction_amount (VARCHAR)
* transaction_currency (VARCHAR)
* payment_sender_number (VARCHAR)
* transaction_id (VARCHAR)
* transaction_status (VARCHAR)
* transaction_webhook (VARCHAR)
* transaction_metadata (VARCHAR)
* product_name (VARCHAR)
* created_at (TIMESTAMP)

== Changelog ==

= 1.0 =
* Initial release
* API endpoints (GET and POST)
* Admin interface
* Database table creation
* API key management
* Transaction listing


== Upgrade Notice ==

= 1.0 =
Initial release of TopupBay module.

