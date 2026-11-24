# Weight Monitor

A one-person web app for tracking body weight, goals and a daily supplement ("mass"), with a chart.
It can be installed on a phone (PWA) and runs on Hostinger web hosting with PHP and MySQL.

## Files

| File | What it is |
|---|---|
| `index.html` | The whole app: page, styles and JavaScript |
| `api.php` | Saves and reads data from the MySQL database |
| `config.sample.php` | Template for the database settings |
| `config.php` | Your real database settings. **Git-ignored, never commit it** |
| `dev-server.js` | Runs the app locally, using the live database |
| `manifest.webmanifest`, `icon*.png`, `icon.svg`, `apple-touch-icon.png` | Lets the app be installed on a phone |

## Hosting on Hostinger

1. Create a MySQL database and user in hPanel → **Databases**.
2. Copy `config.sample.php` to `config.php` and fill in the database name, user and password.
   Both names start with your account prefix, e.g. `u123456789_`.
3. In File Manager, put `config.php` in the folder **above** `public_html`, next to it, not inside it.
   Deploys never touch it there, and it can't be opened from the web.
4. Push to GitHub. Hostinger deploys `main` into `public_html`.

The database tables are created automatically the first time the site loads.

## Running locally

```bash
node dev-server.js
```

Open http://localhost:8000. The page comes from your local files; the data comes from the live site.

> Anything you save or delete locally changes the **real** data.

## Releasing a new version

The version is shown at the bottom of the page. Change it on every release.

**1. Pick the new number.** It has the form `vMAJOR.MINOR.PATCH`:

| Change | Example | Bump |
|---|---|---|
| Small fix (typo, layout tweak) | `v1.1.0` → `v1.1.1` | last number |
| New feature | `v1.1.0` → `v1.2.0` | middle number, last becomes 0 |
| Big change that breaks old data or backups | `v1.1.0` → `v2.0.0` | first number, others become 0 |

**2. Change the version in `index.html`.** Find this line near the bottom:

```html
<footer class="version">v1.1.0</footer>
```

**3. Commit, push, tag:**

```bash
git status                          # check the file list; config.php must NOT appear
git add .
git commit -m "Release v1.2.0: short description of what changed"
git push                            # sends the commit to main, so Hostinger deploys it
git tag v1.2.0                      # labels this commit with the version
git push --tags                     # sends the tag to GitHub
```

Use the same version in the footer, the commit message and the tag.

**4. Check the live site.** Open it and confirm the new version shows at the bottom.
On a phone with the app installed, close and reopen it to pick up the change.

### Tag mistakes

- Tagged the wrong commit, not pushed yet: `git tag -d v1.2.0`, then tag again.
- See all versions: `git tag`, or **Tags** on the GitHub repo page.
