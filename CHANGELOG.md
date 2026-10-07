# Changelog

## v3.0.0

- fix: Harden secure cookies against malformed payloads, path traversal, cookie swapping, invalid records, and unsafe deletion.
- fix: Require a resolvable user for authentication and securely validate user-switch and switch-back identities.
- fix: Validate login failures correctly and restrict authentication redirects to safe same-origin destinations.
- patch: Reject unsafe update archives containing traversal paths, absolute paths, symlinks, duplicate entries, or excessive content.
- new: Stage update packages privately, validate PHP syntax, atomically publish files, and journal backups for rollback.
- new: Recover interrupted filesystem updates automatically and prevent concurrent update workflows with process locks.
- new: Persist and restore maintenance mode across updates, Composer operations, migrations, failures, and interrupted processes.
- enhancement: Support transactional upgrade-script execution and pluggable post-migration health checks.
- fix: Configure signed CLI updates correctly and detect failures from Composer, core migrations, and site migrations.
- patch: Execute updater processes without shell command construction and use practical execution timeouts.
- fix: Make environment-file writes locked, validated, escaped, and resistant to injection or concurrent lost updates.
- fix: Correct file-backed JSON resource loading and add backwards-compatible filesystem injection.
- fix: Prevent recursive application bootstrap when initializing the file-backed RBAC resource.
- fix: Repair shortcode parsing, attribute handling, HTML escaping, and runaway recursive rendering.
- fix: Honor date timezones and locales and serialize content and product event dates portably.
- fix: Correct streamed responses, form-state persistence, PHP type discovery, image validation, and product query SQL.
- fix: Prevent undefined results and invalid error handling in content-type and user services.
