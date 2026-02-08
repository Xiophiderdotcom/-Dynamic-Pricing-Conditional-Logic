# SSA Setup Guide - OnlyFotos Booking Manager

This guide walks you through configuring Simply Schedule Appointments (SSA) to work with the OnlyFotos Booking Manager plugin.

## Prerequisites

- WordPress site with SSA plugin installed and activated
- OnlyFotos Booking Manager plugin installed and activated
- Google Maps API key (with Places API enabled)

## Step 1: Configure Plugin Settings

1. Go to **WordPress Admin > Real Estate Booking > Settings**
2. Enter your **Google Maps API Key**
3. Configure dusk hours, pricing, and office locations
4. Save settings

## Step 2: Create Appointment Types

Create two appointment types in SSA:

### Rentals Appointment Type
1. Go to **SSA > Appointment Types**
2. Click "Add New"
3. Name: **Rentals**
4. Configure duration and availability (e.g., 30 minutes, Mon-Thu)

### Sales Appointment Type
1. Add another appointment type
2. Name: **Sales**
3. Configure duration and availability

## Step 3: Add Custom Fields

For EACH appointment type, add the following custom fields. **IMPORTANT**: Add the specified CSS class to each field.

### Common Fields (Both Forms)

| Field Name | Type | CSS Class | Notes |
|------------|------|-----------|-------|
| Property Address | Text | `of-address-input` | Will have autocomplete |
| Place ID | Hidden | `of-place-id` | Auto-populated |
| Latitude | Hidden | `of-latitude` | Auto-populated |
| Longitude | Hidden | `of-longitude` | Auto-populated |
| Agent Mobile | Phone | - | - |
| Agent Email | Email | - | - |
| Notes | Textarea | - | Optional |

### Add-ons Section

**Rentals:**
- Drone (Checkbox)
- Extra Images (Repeatable field group, max 5) - Add class `of-extra-images` to container

**Sales:**
- Proofing (Checkbox)
- Drone (Checkbox)
- Dusk (Checkbox) - Add class `of-dusk-addon`
- Extra Images (Repeatable field group, max 5) - Add class `of-extra-images` to container

### Property Access (Dropdown)

**Rentals Options:**
- Keysafe
- Meet agent at property
- Pick up keys from office (St Kilda)
- Pick up keys from office (Murrumbeena)
- Vendor/tenant provides access
- Other

**Sales Options:**
- Keysafe
- Meet agent at property
- Pick up keys from office (Caulfield)
- Pick up keys from office (St Kilda)
- Pick up keys from office (Bentleigh)
- Pick up keys from office (Carnegie)
- Pick up keys from office (Murrumbeena)
- Pick up keys from office (NRC)
- Vendor/tenant provides access
- Other

### Conditional Fields

Add these fields and set up conditional logic in SSA:

| Field Name | Type | CSS Class | Show When |
|------------|------|-----------|-----------|
| Keysafe Code | Text | `keysafe-code` | Property Access = Keysafe |
| Vendor/Tenant Name | Text | `vendor-name` | Property Access = Vendor/tenant provides access |
| Vendor/Tenant Contact | Phone | `vendor-contact` | Property Access = Vendor/tenant provides access |
| Access Details | Textarea | `access-details` | Property Access = Other |

## Step 4: Create Pages

### Agent Portal Page
1. Create a new page: **Agent Portal**
2. Add shortcode: `[of_agent_portal]`
3. Publish

### Booking Page
1. Create a new page: **Book a Shoot**
2. Add shortcode: `[of_booking_landing]`
3. Publish

## Step 5: Cache Exclusion (Optional but Recommended)

To ensure real-time availability, exclude the booking page from caching:

**Using WP Rocket:**
```
Settings > Advanced Rules > Never Cache URL(s)
Add: /book-a-shoot/
```

**Using W3 Total Cache:**
```
Performance > Page Cache > Advanced
Never cache the following pages: book-a-shoot
```

## Step 6: Test the System

1. **Test Address Autocomplete:**
   - Go to booking page
   - Start typing an address
   - Verify dropdown appears
   - Select an address
   - Check browser console for "Place ID" confirmation

2. **Test Dusk Restriction (Sales Form Only):**
   - Select "Dusk" add-on
   - Verify only late afternoon slots appear
   - Notice appears: "🌙 Dusk Selected..."

3. **Test Extra Images Limit:**
   - Try adding multiple extra images
   - Verify you can't add more than configured limit (default: 5)

4. **Test Property Access Conditional Logic:**
   - Select "Keysafe" - verify keysafe code field appears
   - Select "Vendor/tenant" - verify name/contact fields appear
   - Select "Other" - verify details textarea appears

5. **Test Agent Portal:**
   - Create a test booking
   - Log in as the agent (same email used in booking)
   - Go to Agent Portal page
   - Verify booking appears
   - Try editing mobile number (click on it)
   - Try canceling booking

## Troubleshooting

### Address Autocomplete Not Working
- Check Google Maps API key is valid and Places API is enabled
- Check browser console for errors
- Verify field has class `of-address-input`

### Dusk Slots Not Filtering
- Verify checkbox has class `of-dusk-addon`
- Check console for errors
- Confirm dusk start hour in settings

### Agent Portal Shows All Bookings
- Verify agent's login email matches email used in bookings
- Check SSA database table exists

### Extra Images Not Limiting
- Verify container has class `of-extra-images`
- Check that limit is set in plugin settings

## Email Notifications

SSA handles all email notifications automatically:
- Booking confirmation (to customer)
- Admin notification
- Reminder emails
- Cancellation/reschedule notifications

Configure these in **SSA > Settings > Notifications**.

##Outlook Calendar Sync

To sync with Outlook calendar (availability blocking only):
1. Go to **SSA > Settings > Calendars**
2. Connect your Outlook/Microsoft 365 account
3. Enable "Block times in SSA based on calendar events"

This prevents double-bookings but doesn't send invites (as requested).

## Need Help?

Check the plugin settings page for field mapping details and status checklist.
