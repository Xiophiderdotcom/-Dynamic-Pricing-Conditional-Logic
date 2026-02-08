=== Advance Booking System ===
Contributors: Xiophider.com
Tags: booking, real estate, photography, appointments
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.0.0
License: GPLv2 or later

Standalone real estate photography booking with built-in forms - no external plugins required.

== Description ==

Simple, standalone booking system for real estate photography. Everything works out of the box!

**Features:**
* Built-in booking form (no SSA required)
* Separate Rentals & Sales forms
* Google Places address autocomplete
* Add-ons: Drone, Dusk, Proofing, Extra Images
* Property access options with conditional fields
* Agent portal to view/cancel bookings
* Email confirmations
* Admin dashboard

== Installation ==

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate the plugin
3. Create two pages:
   - "Book a Shoot" → add shortcode `[abs_booking]`
   - "Agent Portal" → add shortcode `[abs_portal]`
4. (Optional) Add Google Maps API key in Settings

== Shortcodes ==

* `[abs_booking]` - Booking form
* `[abs_portal]` - Agent portal

== Changelog ==

= 2.0.0 =
* Complete rewrite - standalone, no SSA dependency
* Single file plugin - easier to manage
* Built-in booking form with step wizard
* All features work immediately
