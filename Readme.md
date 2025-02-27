# Webhook to BigQuery Cloud Function

This is a Google Cloud Function that processes incoming webhooks, validates the request by IP address, and stores the data into Google BigQuery.

## Prerequisites

- **Google Cloud Project**: You need a Google Cloud Project with **BigQuery** enabled.
- **BigQuery Dataset and Table**: Set up your BigQuery dataset and table where the data will be inserted.
- **Allowed IP Address**: Know the IP address of the source sending the webhook to configure validation.

## Features

- **IP Address Validation**: The function validates the request by checking the client’s IP against a single allowed IP set in an environment variable.
- **BigQuery Data Insertion**: The function processes the webhook payload and inserts it into a Google BigQuery table.
- **Environment Variables**:
  - `ALLOWED_IP`: The single IP address allowed to send webhook requests.

## Installation

1. **Clone the repository** (or download the code):

    ```bash
    git clone https://github.com/your-repo/webhook-to-bigquery.git
    cd webhook-to-bigquery
    ```

2. **Install dependencies**:

    Ensure that you have `composer` installed. Then install the required PHP dependencies:

    ```bash
    composer install
    ```

3. **Set up Google Cloud Function**:

    - Ensure your **Google Cloud Project ID**, **BigQuery Dataset ID**, and **BigQuery Table ID** are set correctly in the `insertIntoBigQuery()` function in the code.
    - Update the function name to match the initiator in your Google Cloud Function settings if needed.
    - Deploy this function to Google Cloud Functions via the `gcloud` CLI.

    Example command to deploy the function:

    ```bash
    gcloud functions deploy sellerAmpInvoices \
    --runtime php81 \
    --trigger-http \
    --allow-unauthenticated \
    --set-env-vars ALLOWED_IP=203.0.113.1
    ```

    Replace `203.0.113.1` with the actual IP address you expect to send the webhook.

4. **Set up BigQuery**:
    - In your Google Cloud Console, create a **BigQuery Dataset** and a **Table** that matches the structure of the data you will be sending.
    - Update the `insertIntoBigQuery()` function with the appropriate `projectId`, `datasetId`, and `tableId`.

## How It Works

1. The function receives incoming webhook requests.
2. It extracts the client IP from the `X-Forwarded-For` header (or falls back to `REMOTE_ADDR`) and validates it against the `ALLOWED_IP` environment variable.
3. If the IP matches, the webhook payload is extracted, formatted, and inserted into the specified BigQuery table.

### Request Example

```json
{
  "data": {
    "invoice": {
      "invoice_id": "123456",
      "invoice_number": "INV-12345",
      "invoice_date": "2025-01-01",
      "due_date": "2025-01-15",
      "transaction_type": "Sale",
      "status": "Paid",
      "total": 1000.00,
      "balance": 0.00,
      "currency_code": "USD",
      "currency_symbol": "$",
      "created_time": "2025-01-01T00:00:00Z",
      "updated_time": "2025-01-02T00:00:00Z",
      "customer_id": "98765",
      "customer_name": "John Doe",
      "billing_address": {
        "street": "123 Main St",
        "city": "Metropolis",
        "state": "NY",
        "zip": "10001",
        "country": "USA",
        "phone": "+123456789"
      },
      "shipping_address": {
        "street": "456 Other St",
        "city": "Metropolis",
        "state": "NY",
        "zip": "10002",
        "country": "USA"
      },
      "subscriptions": {
        "subscription_id": "sub-12345"
      }
    }
  }
}
```

### Field Mapping for BigQuery

You’ll need to adjust the `bigquery_data` variable in the code to match your BigQuery table’s schema. The webhook payload may have a nested structure, so map the fields accordingly.

For example:

```php
$bigquery_data = [
    'invoice_id' => getIfIsset($data['data']['invoice'], 'invoice_id'),
    'invoice_number' => getIfIsset($data['data']['invoice'], 'invoice_number'),
    'invoice_date' => getIfIsset($data['data']['invoice'], 'invoice_date'),
    'due_date' => getIfIsset($data['data']['invoice'], 'due_date'),
    'transaction_type' => getIfIsset($data['data']['invoice'], 'transaction_type'),
    'status' => getIfIsset($data['data']['invoice'], 'status'),
    'total' => getIfIsset($data['data']['invoice'], 'total'),
    'balance' => getIfIsset($data['data']['invoice'], 'balance'),
    'currency_code' => getIfIsset($data['data']['invoice'], 'currency_code'),
    'currency_symbol' => getIfIsset($data['data']['invoice'], 'currency_symbol'),
    'record_created_time' => getIfIsset($data['data']['invoice'], 'created_time'),
    'updated_time' => getIfIsset($data['data']['invoice'], 'updated_time'),
    'customer_id' => getIfIsset($data['data']['invoice'], 'customer_id'),
    'customer_name' => getIfIsset($data['data']['invoice'], 'customer_name'),
    'billing_street' => getIfIsset($data['data']['invoice'], ['billing_address', 'street']),
    'billing_city' => getIfIsset($data['data']['invoice'], ['billing_address', 'city']),
    'billing_state' => getIfIsset($data['data']['invoice'], ['billing_address', 'state']),
    'billing_zipcode' => getIfIsset($data['data']['invoice'], ['billing_address', 'zip']),
    'billing_country' => getIfIsset($data['data']['invoice'], ['billing_address', 'country']),
    'billing_phone' => getIfIsset($data['data']['invoice'], ['billing_address', 'phone']),
    'shipping_street' => getIfIsset($data['data']['invoice'], ['shipping_address', 'street']),
    'shipping_city' => getIfIsset($data['data']['invoice'], ['shipping_address', 'city']),
    'shipping_state' => getIfIsset($data['data']['invoice'], ['shipping_address', 'state']),
    'shipping_zipcode' => getIfIsset($data['data']['invoice'], ['shipping_address', 'zip']),
    'shipping_country' => getIfIsset($data['data']['invoice'], ['shipping_address', 'country']),
    'subscription_id' => getIfIsset($data['data']['invoice'], ['subscriptions', 'subscription_id']),
];
```

Ensure the field names in `$bigquery_data` match your BigQuery table’s column names.

### Response

If successful, the function returns:

```json
{
  "success": "Data inserted into BigQuery"
}
```

If the request fails due to invalid IP or data, an error message is returned.

### Errors

- **Missing Required Environment Variables**: Ensure `ALLOWED_IP` is set.
- **Unauthorized IP**: If the client IP doesn’t match `ALLOWED_IP`, the request is rejected with a `403` status code.
- **Invalid JSON**: If the payload isn’t valid JSON, a `400` status code is returned.
- **BigQuery Insert Failure**: If there’s an issue inserting data into BigQuery, an error message is returned.