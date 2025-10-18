# Client Warnings Module for WHMCS

This module allows administrators to issue, track, and manage client warnings. Clients must acknowledge these warnings before continuing to use their account.

## Features

- **Admin UI:** Issue warnings, manage warning status (acknowledge, archive, delete) and view detailed statistics.
- **Client Area UI:** Clients are alerted about unacknowledged warnings and can view details and acknowledge them.
- **Automatic Actions:** Unacknowledged warnings trigger a client redirect and expired warnings are auto-archived.
- **Secure File Uploads:** Evidence/proof attachments are processed securely.

## Installation

1. **Upload Files:**

   - Place the entire `clientwarnings` folder (which includes `clientwarnings.php`, `hooks.php`, and `templates/clientwarnings.tpl`) into the WHMCS addons directory:
     ```
     modules/addons/clientwarnings/
     ```

2. **Set Directory Permissions:**

   - Ensure that the directory `modules/addons/clientwarnings/uploads/` is writable. The module will attempt to create this directory and secure it with an `.htaccess` file and an `index.html` file.

3. **Activate the Module:**
   - Log in to your WHMCS Admin Area.
   - Navigate to **Setup > Addon Modules**.
   - Locate "Client Warnings" in the list and click **Activate**.
   - Click **Configure** to set the module settings (e.g., Enforce Warning Acknowledgment, Warning Severity Levels, Default Warning Expiration).

## Usage

- **Admin Area:**

  - Issue new warnings to clients.
  - Manage existing warnings (acknowledge, archive, delete, etc.).
  - View warning statistics via a dedicated statistics tab.

- **Client Area:**
  - Clients with unacknowledged warnings are redirected to view them.
  - Clients can review warnings, view attachments, and acknowledge them.

## Troubleshooting

- **Module Not Showing?**

  - Verify that the module folder is correctly placed in `modules/addons/clientwarnings/`.
  - Check file permissions and ensure the uploads folder is writable.

- **Errors Occurring?**
  - Review the WHMCS log files.
  - Make sure your PHP version meets WHMCS requirements.
  - Refer to the WHMCS Troubleshooting Guide for additional help.

## Need help?

- https://26bz.online/discord
