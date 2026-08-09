# Parish Alerts Development Workflow

This directory is both the active LocalWP plugin and the single local Git repository for Parish Alerts.

## Repository Roles

- Develop and test changes in this directory on `dev`.
- Keep only this local checkout. Do not create a separate stable copy just to hold `main`.
- Keep `main` stable. Merge tested `dev` work through a reviewed pull request or an explicit release workflow.
- Do not add this directory to the `ats-wp-dev` parent repository or convert it into a submodule.

## Verification

- Run PHP syntax checks on changed PHP files.
- Verify the zero-alert state at `http://ats-wp-dev/`.
- Verify active alert output at `http://ats-wp-dev/` and `http://ats-wp-dev/alerts/`.
- Verify the service-worker response, subscription REST routes, scheduling, and Alerts > Notifications diagnostics for Web Push changes.
- Browser permission and live push delivery require a trusted HTTPS origin; LocalWP's plain HTTP URL can validate only the server-side flow.
- Plugin uninstall removes the Web Push subscription table and VAPID options but intentionally retains Alert posts and their schedule metadata.
- Confirm `git status -sb` from this directory before committing.
