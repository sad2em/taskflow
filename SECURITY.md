# Security

Please do not publish credentials, session data, database dumps containing real user information, or private configuration in issues or pull requests.

For a private security report, contact the project maintainer directly rather than opening a public issue.

Before deployment:

- Use a dedicated database account with the minimum required privileges.
- Keep `APP_DEBUG` disabled.
- Remove demo accounts and sample data.
- Use HTTPS and secure session settings at the web-server level.
- Back up the database before upgrades.
