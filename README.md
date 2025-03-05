# reBB - BBCode Form Builder

reBB is a PHP application that allows users to create and share customizable forms which convert user input into formatted text output using BBCode, HTML, or any text template format. It's designed to simplify the creation of structured content for forums, websites, or any platform that uses formatted text.

## Overview

reBB provides a drag-and-drop form builder interface for creating custom forms with various input types. Forms can be shared via unique URLs, and when users fill them out, their input is automatically formatted according to a predefined template. This makes it perfect for creating consistent forum posts, pre-formatted messages, or structured content.

## Key Features

- **Drag-and-Drop Form Builder**: Create forms without coding
- **Custom Templates**: Define output templates using wildcards
- **Shareable Forms**: Each form gets a unique URL that can be shared
- **Browser Storage**: User data is saved in browser cookies for convenience
- **Data Grid Support**: Create tables and repeatable sections
- **Dark Mode**: Toggle between light and dark interface
- **Mobile Responsive**: Works on desktop and mobile devices
- **Admin Panel**: Manage forms and view system statistics
- **Documentation System**: Built-in markdown documentation

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/booskit-codes/reBB.git
   ```

2. Create a configuration file:
   - Copy `includes/config.example.php` to `includes/config.php`
   - Adjust the settings to match your environment

3. Ensure the web server has write permissions for these directories (these directories should automatically get made with the appropriate permissions):
   - `/forms` (stores form definitions)
   - `/logs` (stores system logs)
   - `/documentation` (stores markdown docs)
   - `/lib` (stores libraries)

4. Access the application through your web browser

## Configuration

The configuration system has been completely revamped. Key settings include:

```php
// Site identity
define('SITE_NAME',        'reBB');
define('SITE_DESCRIPTION', 'BBCode done differently');

// URLs and paths
define('SITE_URL',         'https://your-domain.com');

// Environment: 'development' or 'production'
define('ENVIRONMENT',      'production');

// Security settings
define('ENABLE_CSRF',      true);
define('SESSION_LIFETIME', 86400);      // 24 hours in seconds

// Rate limiting
define('MAX_REQUESTS_PER_HOUR', 60);    // Maximum submissions per hour per IP
define('COOLDOWN_PERIOD', 5);           // Seconds between submissions
```

## Usage

### Creating a Form

1. Click "Create a form" on the homepage
2. Use the drag-and-drop interface to add form elements
3. Give your form a name at the bottom
4. Create a template using wildcards that correspond to your form fields
5. Click "Save Form" to generate a shareable link

### Using a Form

1. Access a form using its unique URL
2. Fill out the form fields
3. Submit the form to see the generated output
4. Copy the output for use in your forum, website, etc.

### Template Wildcards

Templates use curly braces to insert field values:
- Single fields: `{field_name}`
- Data grids (repeating sections): Use `{@START_gridname@}...{@END_gridname@}` to define repeatable content

Example template:
```
[b]Character Name:[/b] {character_name}
[b]Race:[/b] {race}
[b]Class:[/b] {class}

[b]Equipment:[/b]
{@START_equipment@}
- {item} ({quantity})
{@END_equipment@}

[b]Background:[/b]
{background}
```

## Admin Features

To access the admin panel, navigate to `/admin.php`. On first visit, you'll be prompted to create an admin account.

Features:
- View all created forms
- Delete forms
- View system logs
- Monitor system usage

## Custom Components

The system supports custom components defined in `components.json`. This allows for pre-built form elements with special functionality, like sections that remember user input through browser cookies.

## Documentation System

reBB includes a built-in documentation system with:
- Markdown support
- Admin editing interface
- Automatic ordering of documentation pages
- Mobile-responsive layout

## Security Features

- CSRF protection
- Rate limiting
- Input validation
- XSS prevention
- Form submission logging
- Blacklist support for malicious IPs
- Session timeout controls

## Browser Compatibility

reBB works in all modern browsers:
- Chrome
- Firefox
- Safari
- Edge

## License

reBB is licensed under the GNU General Public License v3.0 - see the LICENSE file for details.

## Credits

Made with ❤️ by [booskit](https://booskit.dev)
