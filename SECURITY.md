# Security policy

This is a fork of PunBB. Report vulnerabilities here, not to upstream PunBB —
the code has diverged and a report sent upstream will not reach this fork.

## Supported versions

| Version | Supported |
| --- | --- |
| 1.5.x | yes |
| 1.4.x and earlier | no — upgrade first |

## Reporting

Use GitHub's private vulnerability reporting on
<https://github.com/mageown/punbb/security/advisories/new>. Do not open a public
issue, and do not post a proof of concept anywhere public before a fix ships.

Include the forum version and database revision, the PHP version, whether the
request needs an account and which permissions it needs, the exact request that
reproduces the problem, and what it achieves.

## What happens next

A report is acknowledged, reproduced and given a severity by reachability: what
a guest or an ordinary member can reach outweighs what only an administrator
can. An exploitable finding ships as a 1.5.x patch release; hardening with no
known exploit path lands on the `v2` branch. The advisory names the reporter
unless they ask otherwise.

## Out of scope

- Installing an extension is code execution by design: `extension_hooks` holds
  PHP that `eval()` runs, and `validate_manifest()` does not inspect what the
  code does. A finding that starts with "as an administrator, install this
  extension" is the mechanism working as documented.
- `admin/db_update.php` has no permission check. Delete it after an upgrade, as
  the upgrade instructions say — that is the documented mitigation.
- `FORUM_DEBUG`, `FORUM_SHOW_QUERIES` and `FORUM_DISABLE_CSRF_CONFIRM` in
  `config.php` are operator switches that weaken the forum on purpose. Do not
  set them on a public forum.
- The login form and the password-reset form are not rate limited.
- Anything requiring a server misconfiguration the forum cannot cause, such as
  executing `img/avatars/*.gif` as PHP.
