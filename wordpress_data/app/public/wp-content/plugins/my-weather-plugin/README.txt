=== My Weather Plugin ===
Contributors: Your Name
Tags: weather, shortcode, open-meteo
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display weather information from Open-Meteo API using shortcodes in your WordPress posts and pages.

== Description ==

My Weather Plugin allows you to display real-time weather information from the Open-Meteo API, a free and open-source weather data provider. No API key is required.

Features:
- Display weather in posts and pages using the [weather] shortcode
- Configure a fixed location via WordPress admin settings
- 1-hour caching to optimize API requests
- Full and simple display formats
- Shows temperature, weather conditions, and wind speed
- No API key required
- Responsive and beautiful UI
- Security-focused implementation

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` directory
2. Activate the plugin from the WordPress Plugins page
3. Go to Settings > Weather to configure your location
4. Use the [weather] shortcode in your posts or pages

== Configuration ==

1. Navigate to Settings > Weather in the WordPress admin
2. Enter your desired location:
   - City Name: Display name for the location
   - Latitude: Decimal format (e.g., 35.6762 for Tokyo)
   - Longitude: Decimal format (e.g., 139.6503 for Tokyo)
3. Save changes

== Usage ==

Display full weather widget:
`[weather]`

Display simple text format:
`[weather format="simple"]`

== Example Coordinates ==

- Tokyo: Latitude 35.6762, Longitude 139.6503
- New York: Latitude 40.7128, Longitude -74.0060
- London: Latitude 51.5074, Longitude -0.1278
- Paris: Latitude 48.8566, Longitude 2.3522
- Sydney: Latitude -33.8688, Longitude 151.2093

== API Information ==

This plugin uses the free Open-Meteo API:
- Website: https://open-meteo.com
- No API key required
- No rate limits for non-commercial use
- Data sources: National weather services

== Security ==

- All user input is sanitized and validated
- Settings can only be modified by administrators
- No nonces are required for settings form (WordPress handles this internally)
- Output is properly escaped to prevent XSS attacks
- No sensitive data is stored

== Changelog ==

= 1.0.0 =
* Initial plugin release
* Shortcode support with [weather] tag
* Admin settings page for location configuration
* 1-hour caching for API responses
* Support for multiple weather display formats

== License ==

This plugin is licensed under the GPL-2.0-or-later. See LICENSE.txt for details.
