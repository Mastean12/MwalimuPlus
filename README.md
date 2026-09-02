# MwalimuPlus

A curriculum-grounded, offline-first AI lesson-prep companion for Kenyan Grade 10 teachers, powered by Claude and grounded exclusively in official KICD curriculum designs.

## Requirements

- PHP 8.x (Laragon recommended on Windows)
- MySQL 8.x (bundled with Laragon)
- cURL extension enabled in PHP (`php -m` should list `curl`)
- A Claude API key from [Anthropic](https://console.anthropic.com/) (optional for exploring the UI — see [Configuration](#configuration))

## Project structure

```
mwalimu-plus/
  index.php  login.php  logout.php  dashboard.php  subject.php  topic.php  lesson.php
  api/       auth.php  subjects.php  topics.php  lessons.php  generate-lesson.php
  config/    database.php  claude.php
  content/   strand-<subject>.txt          <-- loaded KICD design (demo corpus)
  includes/  auth-check.php  header.php  footer.php
  assets/    css/app.css  js/{app.js, lesson.js}
  offline/   service-worker.js  manifest.json  offline.html
  database/  schema.sql  seed.php
```

## Setup

### 1. Clone (if you haven't already)

Place the project inside your web root. With Laragon this is `C:\laragon\www`:

```bash
git clone https://github.com/Mastean12/MwalimuPlus.git
```

### 2. Start your server + database

**Laragon:** start Laragon and click **Start All**. If MySQL is not running as a service, start it from Laragon's menu (MySQL > Start). Confirm both with a green indicator.

**No Laragon?** Run MySQL yourself, then serve the project:

```bash
php -S localhost:8000 -t C:\laragon\www\MwalimuPlus
```

### 3. Create the database and tables

The app connects as `root` with no password on `127.0.0.1:3306` (Laragon defaults). Override these via environment variables if your setup differs: `MWALIMU_DB_HOST`, `MWALIMU_DB_PORT`, `MWALIMU_DB_NAME`, `MWALIMU_DB_USER`, `MWALIMU_DB_PASS`.

Apply the schema:

```bash
mysql -u root < database/schema.sql
```

If `mysql` is not on your PATH (Laragon), use the full path, e.g.:

```bash
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root < database/schema.sql
```

### 4. Seed the demo curriculum

```bash
php database/seed.php
```

This loads the subjects and topics (Mathematics, Biology) that map to the KICD strand corpus files in `content/`.

## Configuration

Set your Claude API key via an environment variable:

```bash
set CLAUDE_API_KEY=sk-ant-...    # Windows (cmd)
$env:CLAUDE_API_KEY = "sk-ant-..."   # Windows (PowerShell)
export CLAUDE_API_KEY=sk-ant-...     # macOS / Linux
```

Or edit the placeholder `'YOUR_CLAUDE_API_KEY'` inside `config/claude.php`.

> The placeholder is preferable over committing a real key to the repo. Without a key the app still runs: generation requests are validated against the curriculum corpus and return a clear "key not configured" demo message.

## Running the system

Open your browser and visit the project under your web root:

```
http://mwalimuplus.test        (Laragon + a vhost named mwalimuplus.test)
http://localhost/mwalimuplus   (Apache/nginx web root)
http://localhost:8000          (PHP built-in server)
```

The exact URL depends on how your local server maps folders to domains.

1. **Register an account** on the login page (name, email, password of 8+ characters) — this is a local demo database, so no email confirmation is needed.
2. You land on the **Dashboard**, which lists the seeded subjects.
3. Open a subject, then a topic, and use **Generate a lesson plan**.
4. The generated lesson is saved and rendered on a printable page (Print / Save as PDF).
5. On a saved lesson, the **Media & resources** panel lets you attach photos
   (JPG/PNG/WEBP/GIF, 8 MB), PDFs (10 MB), YouTube videos and web links — to the
   whole lesson or to a single section. Uploads are stored under
   `uploads/lesson-resources/` (git-ignored) and served only through
   `download.php` to the teacher who owns the lesson.

### Schemes of work

**Schemes of work** in the sidebar generates a full KICD CBC scheme-of-work grid
(Week / Lesson / Sub-strand / outcomes / experiences / resources / assessment /
reference) for a subject's strand, grounded only in the strand design on the
server. Pick a subject, term, and lessons-per-week; each row cites the design
page it came from, and an unsupported strand returns a Sijui message rather than
invented rows. The saved scheme has its own printable page, and each row links
back to the subject's topic list to generate the matching lesson plan.

On a saved scheme you can attach **learning materials** — a YouTube link, any web
link, or an uploaded PDF (10 MB max) — either to the whole scheme or to a single
lesson row. These are teacher-added; the AI never contributes links. Uploaded
PDFs are stored under `uploads/scheme-resources/` (git-ignored) and served only
through `download.php` to the teacher who owns the scheme. `uploads/.htaccess`
blocks direct web access on Apache; on other servers, keep `uploads/` outside the
document root or add an equivalent rule.

### API

The endpoints require a logged-in session cookie. Full contract for `POST /api/generate-lesson.php`:

```json
{
  "subject": "Mathematics",
  "topic": "Quadratic Equations",
  "strand": "Quadratic Equations and Expressions",
  "duration": 40,
  "teacher_need": "I have never taught this topic before.",
  "resources": ["chalkboard", "chalk"]
}
```

Response:

```json
{
  "success": true,
  "disclosure": "This is an AI assistant. It can be wrong. The teacher makes the final decision.",
  "status": "SUPPORTED | NEEDS_VERIFICATION | UNKNOWN",
  "lesson": { "...": "..." },
  "sijui": null
}
```

When a request is outside the curriculum corpus, the API returns `success: true`, `status: "UNKNOWN"`, `lesson: null` and a `sijui` message pointing the teacher to the right office — it never fabricates a lesson.

## Adding a subject

1. Add a strand design text file to `content/`, e.g. `content/strand-chemistry.txt`, with page markers like `DESIGN PAGE 12` (these become the citations).
2. Add the subject and its topics to the `$data` array in `database/seed.php` (the `source` must match the file name in `content/`).
3. Re-run `php database/seed.php`.

## Privacy note

The AI is instructed to never request or process learner names, learner work, or individual learner data — lesson prep only.
