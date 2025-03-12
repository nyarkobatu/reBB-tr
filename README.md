# reBB - BBCode Form Builder

reBB is a PHP application that allows users to create and share customizable forms which convert user input into formatted text output using BBCode, HTML, or any text template format. It's designed to simplify the creation of structured content for forums, websites, or any platform that uses formatted text.

## Overview

reBB provides a drag-and-drop form builder interface for creating custom forms with various input types. Forms can be shared via unique URLs, and when users fill them out, their input is automatically formatted according to a predefined template. This makes it perfect for creating consistent forum posts, pre-formatted messages, or structured content.

## Key Features

- **Drag-and-Drop Form Builder**: Create forms without coding
- **Custom Templates**: Define output templates using wildcards
- **Multiple Form Styles**: Choose from different visual styles (Default, Paperwork, Vector, Retro, Modern)
- **Shareable Forms**: Each form gets a unique URL that can be shared
- **Custom Shareable Links**: Create memorable branded URLs for your forms
- **User Management**: User accounts with form ownership and management
- **Browser Storage**: Form data saved in browser cookies for convenience
- **Mobile Responsive**: Works on desktop and mobile devices
- **Dark Mode**: Toggle between light and dark interface
- **Admin Panel**: Manage forms, users, and view system statistics
- **Documentation System**: Built-in markdown documentation
- **Analytics System**: Track form usage, views, and submissions

## Technical Stack

- **Backend**: PHP 7.4+
- **Database**: NoSQL (SleekDB) - No traditional SQL database required
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap
- **Form Building**: FormIO library
- **Storage**: File-based storage for forms and configurations

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/booskit-codes/reBB.git
   ```

2. Set up your web server with PHP 7.4+ support
   - Ensure mod_rewrite is enabled for Apache
   - Set document root to the application's root directory
   - For Nginx, configure URL rewriting accordingly

3. Create a configuration file:
   - Copy `includes/config.example.php` to `includes/config.php`
   - Update the settings to match your environment

4. Ensure the web server has write permissions for these directories:
   - `/storage` (stores form definitions, logs, etc.)
   - `/db` (stores NoSQL database files)
   - `/lib` (stores libraries)

5. Access the application through your web browser and go to your setup page via `https://domain.com/setup`
   - The setup will guide you through creating an admin account on first run

## Usage

### Creating a Form

1. Click "Create a form" on the homepage
2. Use the drag-and-drop interface to add form elements
3. Give your form a name and choose a style
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

## User Management

reBB includes a complete user management system:

- **Authentication**: Secure user login and session management
- **Role-Based Access**: Admin and regular user roles
- **Form Ownership**: Users can create, edit, and manage their own forms
- **Custom Links**: Create memorable branded URLs for forms
- **Analytics**: View usage statistics for your forms

## Admin Features

Admin panel features include:

- View all created forms and their usage statistics
- Manage user accounts
- Create and share user credentials
- Monitor system logs and usage
- Track analytics for forms, components, and themes
- Delete or modify any content as needed

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

## NoSQL Database

reBB uses SleekDB, a NoSQL document store that operates directly with the file system:

- No database server required
- Simple JSON-based document storage
- Fast and lightweight
- Easy to backup (just copy the files)
- Straightforward to deploy without complex configuration

## Browser Compatibility

reBB works in all modern browsers:
- Chrome
- Firefox
- Safari
- Edge

## License

reBB is licensed under the GNU General Public License v3.0 - see the LICENSE file for details.

## Credits

Created by [booskit](https://booskit.dev)
Feel free to support this project by considering a [donation](https://rebb.booskit.dev/donate)