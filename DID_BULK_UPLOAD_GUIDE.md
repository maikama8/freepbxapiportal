# DID Number Bulk Upload and Management Guide

## Overview

The DID management system now supports bulk operations for efficient management of large DID inventories. This includes CSV bulk upload and bulk price updates.

## Features Implemented

### 1. CSV Template Generation
- **Route**: `GET /admin/dids/template?country={country_code}`
- **Purpose**: Download a CSV template with sample data and default pricing for a specific country
- **Usage**: Select a country and click "Download Template" to get a properly formatted CSV file

### 2. Bulk Upload from CSV
- **Route**: `POST /admin/dids/bulk-upload`
- **Purpose**: Upload multiple DID numbers from a CSV file
- **Features**:
  - Validates CSV format and required columns
  - Supports country-specific default pricing
  - Handles duplicate DID detection
  - Provides detailed error reporting
  - Progress tracking during upload

### 3. Bulk Price Updates
- **Route**: `POST /admin/dids/bulk-update-prices`
- **Purpose**: Update prices for multiple DIDs based on filters
- **Features**:
  - Filter by country, status, and area code
  - Set, increase, or decrease prices
  - Update monthly and/or setup costs
  - Preview affected DIDs before updating

## CSV Format Requirements

### Required Columns
```csv
did_number,country_code,area_code,provider,monthly_cost,setup_cost,features,expires_at
```

### Column Descriptions
- **did_number**: Complete DID number (e.g., 15551234567)
- **country_code**: 2-letter country code (e.g., US, GB, CA)
- **area_code**: Area/region code (optional)
- **provider**: DID provider name (optional)
- **monthly_cost**: Monthly recurring cost in USD (decimal)
- **setup_cost**: One-time setup cost in USD (decimal)
- **features**: Comma-separated features (voice, sms, fax)
- **expires_at**: Expiration date in YYYY-MM-DD format (optional)

### Example CSV Content
```csv
did_number,country_code,area_code,provider,monthly_cost,setup_cost,features,expires_at
15551234567,US,555,VoIP Provider,2.99,5.00,voice,
15551234568,US,555,VoIP Provider,2.99,5.00,"voice,sms",2025-12-31
442012345678,GB,20,UK Provider,4.99,10.00,"voice,sms,fax",
```

## Usage Instructions

### Bulk Upload Process

1. **Prepare CSV File**
   - Download template for your target country
   - Fill in DID numbers and adjust pricing as needed
   - Ensure all required columns are present

2. **Upload Process**
   - Navigate to Admin → DID Management
   - Click "Bulk Upload" button
   - Select your CSV file
   - Choose default country (for rows without country_code)
   - Click "Upload CSV"

3. **Review Results**
   - System shows upload progress
   - Displays success/error counts
   - Lists specific errors for failed rows
   - Updates DID inventory automatically

### Bulk Price Update Process

1. **Set Filters**
   - Choose country (optional)
   - Select status filter (optional)
   - Enter area code filter (optional)
   - Click "Preview Affected DIDs" to see count

2. **Configure Update**
   - Select update type: Set, Increase, or Decrease
   - Enter amount value
   - Choose which costs to update (monthly/setup)

3. **Execute Update**
   - Review preview count
   - Click "Update Prices"
   - Confirm the operation
   - System updates all matching DIDs

## Validation and Error Handling

### Upload Validation
- File format must be CSV or TXT
- Maximum file size: 10MB
- All required columns must be present
- DID numbers must be unique
- Country codes must exist in system
- Costs must be valid decimal numbers

### Error Reporting
- Row-by-row error details
- Duplicate DID detection
- Invalid country code warnings
- Malformed data notifications
- Partial success handling

### Bulk Update Validation
- At least one cost type must be selected
- Update amount must be non-negative
- Filters are validated before execution
- Preview functionality prevents accidents

## Performance Considerations

### Upload Limits
- Maximum 10MB file size
- Recommended batch size: 1000-5000 DIDs
- Processing time varies by server capacity
- Large uploads are processed in chunks

### Database Impact
- All operations use database transactions
- Failed uploads are rolled back completely
- Bulk updates are optimized for performance
- Audit logs track all changes

## Security Features

### Access Control
- Admin role required for all bulk operations
- Request validation on all endpoints
- CSRF protection enabled
- File type validation enforced

### Audit Logging
- All bulk operations are logged
- User identification tracked
- Change details recorded
- Timestamps and IP addresses stored

## API Endpoints

### Template Download
```http
GET /admin/dids/template?country=US
Authorization: Required (Admin)
Response: CSV file download
```

### Bulk Upload
```http
POST /admin/dids/bulk-upload
Content-Type: multipart/form-data
Authorization: Required (Admin)

Parameters:
- csv_file: File upload
- country_code: Default country code

Response:
{
  "success": true,
  "message": "Bulk upload completed",
  "results": {
    "total": 100,
    "success": 95,
    "errors": 5,
    "error_details": ["Row 10: DID already exists", ...]
  }
}
```

### Bulk Price Update
```http
POST /admin/dids/bulk-update-prices
Content-Type: application/json
Authorization: Required (Admin)

Parameters:
- filter_country: Country filter (optional)
- filter_status: Status filter (optional)
- filter_area_code: Area code filter (optional)
- update_type: "set", "increase", or "decrease"
- update_amount: Numeric amount
- update_monthly_cost: Boolean
- update_setup_cost: Boolean

Response:
{
  "success": true,
  "message": "Successfully updated prices for 150 DID numbers",
  "updated_count": 150
}
```

## Troubleshooting

### Common Issues

1. **CSV Format Errors**
   - Ensure proper column headers
   - Check for extra commas or quotes
   - Verify encoding (UTF-8 recommended)

2. **Upload Failures**
   - Check file size limits
   - Verify country codes exist
   - Ensure DID numbers are unique

3. **Price Update Issues**
   - Confirm filters are correct
   - Check update permissions
   - Verify numeric values

### Support Information

For technical support or feature requests, contact the system administrator or refer to the application logs for detailed error information.