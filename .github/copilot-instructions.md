# Tiny Tiny RSS (tt-rss) - AI Coding Agent Instructions

## Project Overview
Tiny Tiny RSS is a web-based RSS/Atom feed reader and aggregator built with PHP (backend) and JavaScript with Dojo Toolkit (frontend). Forked in October 2025 to continue development after original tt-rss.org shutdown.

## CRITICAL: Essential Prerequisites

### Always Examine Actual Source Code
**NEVER make assumptions about code behavior, dependencies, or structure.**

- ❌ **Bad**: "FeedParser probably uses PluginHost for extensibility"
- ✅ **Good**: Read `classes/FeedParser.php` and grep for `PluginHost::` to verify

**Before making any claim about a class**:
1. **Read the actual file** - Use `read_file` tool to examine the source
2. **Check dependencies** - Use `grep -E '(Config::|Prefs::|PluginHost::|Db::)'` to verify what it uses
3. **Verify assumptions** - Don't trust intuition; verify with code

**Examples of dangerous assumptions**:
- Assuming a parser class uses plugins (it might be pure DOM parsing)
- Assuming a utility class needs database (it might be pure logic)
- Assuming behavior based on class name (check actual implementation)

**This applies to**:
- Determining test type (standard vs mocked vs integration)
- Identifying dependencies before refactoring
- Understanding code behavior for bug fixes
- Adding new features to existing classes

### Self-Improving Instructions

**When encountering issues or patterns that could improve future work:**

1. **Identify the gap**: What went wrong? What knowledge was missing?
2. **Root cause analysis**: Why did the issue occur? (e.g., incomplete guidance, missing example, ambiguous wording)
3. **Propose improvement**: Draft specific instruction updates to prevent recurrence
4. **Validate improvement**: Ensure new guidance is clear, actionable, and doesn't contradict existing instructions
5. **Update this file**: Make targeted changes to `.github/copilot-instructions.md`
6. **Provide concise summary in chat**: Summarize changes verbally rather than creating documentation files

**Examples of self-improvements made**:
- **Issue**: FeedParser falsely assumed to use PluginHost
- **Root cause**: Didn't systematically check all methods, relied on intuition
- **Improvement**: Added "Always Examine Actual Source Code" as critical prerequisite with grep workflow
- **Issue**: UrlHelper::url_to_youtube_vid() test missed despite being high-value pure logic
- **Root cause**: Focused only on methods already in test file, no systematic review
- **Improvement**: Added "Completeness Check: Review for Missing Tests" with grep workflow for all public methods
- **Issue**: CSS linting infrastructure created but instructions not updated
- **Root cause**: Forgot to proactively update instructions after completing new code quality workflow
- **Improvement**: Added explicit reminder to update instructions when adding code quality tools, workflows, or discovering project patterns
- **Issue**: Used outdated "CSS3" terminology in documentation
- **Root cause**: Didn't verify terminology against authoritative sources (MDN)
- **Improvement**: Added requirement to check authoritative documentation sources for technical terminology
- **Issue**: Project migrated from Less to native CSS but many converted files had syntax errors
- **Root cause**: Automated conversion script had bugs; didn't verify all output files after bulk changes
- **Improvement**: Added practice to systematically check all affected files after bulk transformations with grep and lint tools

**Goal**: Continuously improve these instructions based on real-world usage patterns and mistakes.

**CRITICAL: When to Update Instructions Automatically**:
- **New code quality tools added** (linters, formatters, static analyzers) → Update "Code Quality & Testing" section
- **New project conventions discovered** (coding style, architecture patterns) → Add to relevant convention sections
- **Build/workflow changes** (GitHub Actions, Docker, dependencies) → Update relevant workflow sections
- **Deprecation patterns identified** → Add to "Deprecation & Migration" section
- **Testing patterns established** (new test types, bootstrap methods) → Update "Testing & Test Development" section
- **Configuration files created** (`.stylelintrc.json`, `phpstan.neon`, etc.) → Document in relevant sections

**CRITICAL: Verify Terminology Against Authoritative Sources**:
When writing documentation about web technologies, programming languages, or frameworks:
1. **Check official documentation first** - Use authoritative sources for terminology
   - CSS/HTML: https://developer.mozilla.org/en-US/docs/Web/CSS (MDN Web Docs)
   - PHP: https://www.php.net/manual/en/ (Official PHP Manual)
   - JavaScript: https://developer.mozilla.org/en-US/docs/Web/JavaScript (MDN Web Docs)
2. **Avoid outdated terminology** - Don't use deprecated version names or legacy terms
   - ❌ "CSS3", "HTML5" (versioned names are deprecated - use "CSS", "HTML")
   - ❌ "AJAX" (use "fetch API" or "XMLHttpRequest")
   - ❌ "MySQL" for PostgreSQL (verify actual database in use)
3. **Use precise technical terms** - Prefer official specification terminology
   - ✅ "pseudo-elements" not "pseudo elements"
   - ✅ "alpha channel" not "alpha value"
   - ✅ "space-separated syntax" not "new syntax"
4. **When uncertain** - Search the official documentation before documenting
   - Example: Search MDN for "CSS versions" before using "CSS3"
   - Example: Check PHP RFC/manual before claiming a feature is deprecated

**When NOT to create separate documentation**: Only update this file - avoid creating standalone markdown files unless specifically requested by user for reference purposes.

### Documentation Practices

**DO NOT create ad-hoc documentation files** like:
- ❌ `DEBUG-TEST-SUMMARY.md`, `MISSING-TESTS-REVIEW.md`, `TEST-QUALITY-GUIDELINES.md`, etc.
- ❌ Summary files that duplicate information in this instruction file
- ❌ One-off documentation that becomes stale

**DO provide information through**:
- ✅ **Concise summaries in chat** - Clear, actionable responses to user questions
- ✅ **Code comments** - When explaining complex logic in production code
- ✅ **This instruction file** - For patterns, conventions, and reusable guidance

**Rationale**: Ad-hoc documentation files clutter the repository and become outdated. Information should live in either:
1. This centralized instruction file (for AI agent guidance)
2. Code comments (for implementation details)
3. Chat responses (for one-time explanations)

## General Coding Conventions

### String Quotes (All Languages)
- **Prefer single quotes** (`'`) over double quotes (`"`) for string literals across all languages (JavaScript, PHP, YAML, etc.)
- **Exceptions**:
  - Use double quotes when the string contains single quotes to avoid escaping
  - Template literals/interpolation: Use language-specific interpolation syntax (e.g., backticks in JS, double quotes in PHP)
  - Multi-line strings: Use appropriate syntax for the language (backticks in JS, heredoc/nowdoc in PHP)
- **Examples**:
  ```javascript
  const name = 'tt-rss';  // Good
  const message = "User's feed";  // Good - contains single quote
  const html = `<div>${name}</div>`;  // Good - interpolation
  ```
  ```php
  $name = 'tt-rss';  // Good
  $message = "User's feed";  // Good - contains single quote
  $html = "Hello, $name";  // Good - interpolation
  ```

## Architecture & Stack

### Backend (PHP 8.2+)
- **PHP Version**: Minimum version enforced in `Config::sanity_check()` (currently 8.2.0) - this is the source of truth
- **Database**: PostgreSQL exclusively (DB_TYPE constant deprecated)
- **ORM**: Idiorm (`ORM::for_table('table_name')`) - simple active record pattern
- **Config**: Environment variables prefixed with `TTRSS_` (e.g., `TTRSS_DB_HOST`) set in `.env` or `config.php`
- **Handlers**: Request routing via Handler classes (Handler → Handler_Protected → Handler_Administrative hierarchy)
  - Methods are public entry points accessed via `?op=ClassName&method=methodName`
  - Access control enforced by `before()` method:
    - `Handler`: No restrictions (returns true)
    - `Handler_Protected`: Requires authenticated user (`$_SESSION['uid']`)
    - `Handler_Administrative`: Requires admin access level
  - Methods starting with underscore (`_`) are blocked from external access
  - Methods with required parameters are blocked (security measure)
  - Use `csrf_ignore($method)` to bypass CSRF token validation for specific methods
  - Examples: `classes/Feeds.php`, `classes/Article.php`, `classes/RPC.php`
- **Plugin System**: Extensible via `PluginHost` with hooks (see `classes/PluginHost.php` for ~30 hook types)
  - Plugins extend `Plugin` class, implement `init($host)` and `about()`
  - Place in `plugins/` (official/bundled) or `plugins.local/` (personal/unbundled, separate repos at `https://github.com/tt-rss/tt-rss-plugin-*`)
  - Example: `plugins/note/init.php`

### Frontend (JavaScript + Dojo Toolkit)
- **Framework**: Legacy Dojo Toolkit (AMD modules: `define(["dojo/_base/declare", ...])`)
- **Widgets**: dijit (Dojo UI library) - `dojoType="dijit.form.TextBox"`, `dijit.Dialog`, etc.
- **Global Object**: `App` in `js/App.js` - contains utilities, translations, form helpers
- **Main Modules**: `Feeds.js`, `Headlines.js`, `Article.js`, `CommonDialogs.js`
- **Styling**: Native CSS with custom properties (`themes/*.css`) - edit CSS files directly, no build step required

### Database & ORM Patterns
```php
// Idiorm usage - fluent query builder
$user = ORM::for_table('ttrss_users')->find_one($user_id);
$feeds = ORM::for_table('ttrss_feeds')
    ->where('owner_uid', $_SESSION['uid'])
    ->find_many();
$feed->save();  // UPDATE if exists, INSERT if new
```

### Configuration System
- **Primary**: `classes/Config.php` defines all config constants (e.g., `Config::DB_HOST`)
- **Override**: Set via environment variables with `TTRSS_` prefix or in `config.php` via `putenv()`
- **User Prefs**: `classes/Prefs.php` - per-user settings stored in `ttrss_user_prefs2`
  - Most preferences are associated with a user profile (`$_SESSION['profile']`)
  - Some preferences in `_PROFILE_BLACKLIST` are user-level only (e.g., `ENABLE_API_ACCESS`, `USER_TIMEZONE`, `DIGEST_ENABLE`)
  - Profile-blacklisted preferences ignore profile parameter and always apply to the user

## Key Workflows

### Development Setup
```bash
# Local development with Docker (no persistence)
cp .env-dist .env  # Configure TTRSS_DB_* variables
docker-compose up  # Starts db, app, updater, web-nginx

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# CSS linting
npm run lint:css  # Stylelint for code quality
```

### Code Quality & Testing
```bash
# PHP Static Analysis
phpstan analyze --no-progress  # Level 6, config in phpstan.neon

# PHP Code Modernization
vendor/bin/rector process  # PHP 8.2 upgrades, config in rector.php

# JavaScript Linting
npx eslint js/**/*.js plugins/**/*.js  # Config in eslint.config.js

# CSS Linting
npm run lint:css  # Stylelint, config in .stylelintrc.json
# Scope: lib/flat-ttrss, themes, plugins/*/*.css
# Known issues: Mostly legacy Dojo styles with IE hacks and outdated syntax

# Unit Tests
./phpunit  # Uses phpunit.xml config and tests/autoload.php bootstrap
# For tests that need custom bootstrap (e.g., to mock DB dependencies):
./phpunit --no-configuration --bootstrap tests/CustomBootstrap.php tests/SpecificTest.php
```

### Translation Management
```bash
# Update translation template (messages.pot) from source
utils/rebase-translations.sh  # Extracts strings from PHP/JS files
# Note: .po/.mo files are managed via Weblate
```

### Dojo Toolkit Updates
```bash
# Rebuild customized Dojo layer (requires Java runtime)
cd lib/dojo-src
./rebuild-dojo.sh  # Downloads Dojo 1.17.3 source and builds custom layer
```

### Plugin Development
1. Create plugin directory:
   - `plugins/myplugin/init.php` for official/bundled plugins (included in main repo)
   - `plugins.local/myplugin/init.php` for personal/unbundled plugins (separate repo at `https://github.com/tt-rss/tt-rss-plugin-myplugin`)
2. Implement `init($host)` to register hooks: `$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this)`
3. Add handler methods (e.g., `hook_article_button($article)`)
4. Optional: `get_js()`, `get_css()` for client-side assets
5. Enable in preferences: System → Preferences → Plugins

### Feed Update Process
- **Daemon**: `update_daemon2.php` or Docker updater service calls `RSSUtils::update_daemon_common()`
- **Core Logic**: `classes/RSSUtils.php` - fetches feeds, parses articles, applies filters
- **Scheduler**: `classes/Scheduler.php` - manages periodic tasks (stored in `ttrss_scheduled_tasks`)
- **Feed Parser**: `classes/FeedParser.php` - handles RSS/Atom with `FeedItem_RSS` and `FeedItem_Atom`

## Project-Specific Conventions

### PHP Style
- **Type Hints**: Required for method signatures (param and return types)
- **Doc Blocks**: Include `@param`, `@return`, `@var` annotations (PHPStan level 6)
- **Strict Types**: Not universally declared - check file headers
- **Namespaces**: None - all classes in global namespace, PSR-4 autoload from `classes/`
- **Legacy Functions**: `include/functions.php` has deprecated helpers (e.g., `get_pref()` → use `Prefs::get()`)

### JavaScript Patterns
- **AMD Modules**: Use `define()` for custom widgets/modules
- **Script Type**: Code uses `sourceType: 'script'` (not ES modules) - compatible with legacy Dojo AMD pattern
- **Dojo Queries**: `dojo.query()`, `dojo.queryToObject()`, `dijit.byId()`
- **XHR**: `xhr.json()` wrapper (custom) or `dojo.xhrPost()`
- **Dialogs**: Create via `App.dialogOf(this).hide()` or `new SingleUseDialog({})`

### CSS Style
- **Indentation**: 2 spaces (enforced by `.editorconfig` and Stylelint)
- **Modern CSS**: Project migrated from Less to native CSS (October 2025)
  - **Variables**: Use CSS custom properties in `:root` (e.g., `--color-accent`, `--font-size-content`)
  - **Nesting**: Native CSS nesting supported (e.g., `.parent { .child { } }`)
  - **No build step**: CSS files are served directly, no compilation required
  - **Modular structure**: Theme files import component CSS (e.g., `themes/light.css` → `themes/light/defines.css`)
- **Pseudo-elements**: `::before`, `::after` (not `:before`, `:after`)
- **Color functions**: `rgb(0 0 0 / 30%)` space-separated (not `rgba(0, 0, 0, 0.3)` comma-separated)
  - Use `color-mix(in srgb, ...)` for color manipulation instead of preprocessor functions
- **Alpha channel**: Percentages `30%` (not decimals `.3`)
- **Zero Values**: Omit units (`padding: 0` not `padding: 0px`)
- **Hex Colors**: Use shorthand when possible (`#090` not `#009900`)
- **Legacy Dojo Styles**: `lib/flat-ttrss/*.css` contains Dojo Toolkit widget styles, now maintained by tt-rss
  - Known legacy issues: `filter: alpha()` (IE6-9), single-colon pseudo-elements, deprecated properties
  - These files are part of tt-rss codebase and should be incrementally modernized
  - Most issues are auto-fixable; apply fixes incrementally with testing
- **Linting**: Run `npm run lint:css` before committing CSS changes
  - Config: `.stylelintrc.json` extends `stylelint-config-standard`
  - Auto-fix: `npx stylelint --fix` for most issues

### Theme Structure & Customization
- **Top-level theme files** (in `themes/`) are simple import files:
  - `light.css` → imports `light/light_base.css`
  - `night.css` → imports `night_base.css`
  - `compact.css` → imports `light/light_base.css` + `compact_base.css`
  - `compact_night.css` → imports `night_base.css` + `compact_base.css`
  - `night_blue.css` → imports `night_base.css` + dark blue variant
  - `light-high-contrast.css` → imports with variable overrides in `:root`
- **Component files** (in `themes/light/`) contain the actual styles:
  - `defines.css` - CSS custom properties (`:root` block) and imports all components
  - `tt-rss.css` - Main app styles (feeds, articles, headlines)
  - `cdm.css` - Combined display mode (CDM) styles
  - `prefs.css` - Preferences/settings page styles
  - `utility.css` - Utility classes and helpers
  - `zoom.css` - Image zoom functionality styles
  - `dijit_light.css` / `dijit_basic.css` - Dojo widget overrides
- **To modify a theme**:
  1. Edit component files in `themes/light/` (for light theme) or create overrides in theme-specific files
  2. Modify CSS custom properties in `:root` blocks to change colors/sizes globally
  3. Use browser DevTools to inspect variable usage (e.g., `var(--color-accent)`)
  4. No build step required - changes take effect immediately on page reload
- **To create a theme variant**:
  1. Create a new top-level CSS file (e.g., `themes/my-theme.css`)
  2. Import base components and add `:root` overrides for custom properties
  3. Example: See `light-high-contrast.css` for variable override pattern

### Code Generation & Templates
- **PHP HTML Helpers**: Prefer `\Controls\*` functions (e.g., `\Controls\submit_tag()`, `\Controls\hidden_tag()`, `\Controls\select_tag()`)
  - Only use raw HTML when helper functions don't support required functionality (explain why to user)
  - Example: `plugins/note/init.php` uses `\Controls\` namespace functions
- **JavaScript HTML Helpers**: Prefer `App.FormFields.*` methods (e.g., `App.FormFields.submit_tag()`, `App.FormFields.checkbox_tag()`)
- **Templates**: Use inline PHP/HTML (with `<?= ?>` short tags) rather than `Templator` class
  - Inline approach is preferred for maintainability and readability

### Deprecation & Migration
- **Active Refactoring**: When modifying code, replace deprecated usage with best practice equivalents
  - Apply to tt-rss deprecations (e.g., `get_pref()` → `Prefs::get()`, avoid `DB_TYPE` constant)
  - Apply to dependency deprecations and language-level deprecations (PHP, JavaScript)
- **No Timeline Pressure**: tt-rss deprecations have no deadline unless explicitly stated in deprecation comments
- **Examples**: `include/functions.php` contains deprecated helpers - use modern equivalents in new/modified code

### Frontend State Management
- **Context-Specific Module Loading**: Entry point files determine which modules are available
  - `index.php` loads `js/tt-rss.js` → includes `Feeds`, `Headlines`, `Article` modules (main app)
  - `prefs.php` loads `js/prefs.js` → includes `PrefUsers`, `PrefHelpers`, preference-specific modules
  - Check module availability with `typeof ModuleName !== 'undefined'` before use
- **Context Detection**: Use `App.isPrefs()` to check if code is running in preferences vs main app
  - Returns `true` in preferences context, `false` in main app
  - Example: `CommonDialogs.js` uses this to determine post-action behavior (reload feeds vs refresh prefs)
- **Plugin Context**: Plugins load via `get_js()` (main app) or `get_prefs_js()` (preferences)
- **Shared Code**: `js/common.js` and `js/App.js` loaded in both contexts

### XHR Communication Patterns
- **Response Format**: Backend handlers return JSON via `print json_encode($data)` - format varies by method
  - Simple responses: `{"wide": 0}`, `{"param": "key", "value": true}`
  - Counter updates: `{"message": "UPDATE_COUNTERS", "feeds": [...], "labels": [...]}`
  - Runtime info: `{"runtime-info": {...}}`
  - Errors: Use `Errors::to_json()` → `{"error": {"code": "E_UNAUTHORIZED", "params": {...}}}`
- **Frontend Processing**: `xhr.json()` wrapper automatically calls `App.handleRpcJson()`
  - Processes standard fields: `error`, `seq`, `counters`, `runtime-info`, `message`
  - `message: "UPDATE_COUNTERS"` triggers `Feeds.requestCounters()` for specified feeds/labels
  - Fatal errors (non-`E_SUCCESS`) are handled centrally by `App.Error.fatal()`
- **No Strict Schema**: Response structure is method-specific - handlers return whatever data frontend needs
  - Check existing handlers in same class for patterns
  - Frontend typically expects specific fields based on the action performed

### Transaction & Data Consistency
- **PDO Transactions**: Use `Db::pdo()` for transactions (Idiorm doesn't provide transaction methods)
  ```php
  $pdo = Db::pdo();
  $pdo->beginTransaction();
  // ... database operations ...
  $pdo->commit();  // or $pdo->rollBack() on error
  ```
- **Handler Instance PDO**: Handlers have `$this->pdo` available (initialized in `Handler` base class)
- **ORM Access**: Idiorm uses `ORM::get_db()` to access PDO - acceptable alternative if it simplifies code and avoids unnecessary `Db::pdo()` calls
- **Plugin Data Separation**: `PluginHost` uses separate `$pdo_data` instance for plugin storage
  - Prevents transaction conflicts between plugin data saves and main app operations
  - Only initialized when first needed
  - Comment in code: "separate handle for plugin data so transaction while saving wouldn't clash with possible main tt-rss code transactions"
- **Multi-table Operations**: Always use transactions when updating related tables (e.g., articles + labels + counters)

### Database Schema
- **Migrations**: Tracked via `Config::SCHEMA_VERSION` (currently 151)
  - When making schema changes:
    1. Update `sql/pgsql/schema.sql` to reflect the desired end state (for new installations)
    2. Increment `Config::SCHEMA_VERSION` in `classes/Config.php`
    3. Create migration file `sql/pgsql/migrations/{new_version}.sql` with ALTER statements
  - Example: Incrementing from 151→152 requires updating `schema.sql`, setting `SCHEMA_VERSION = 152`, and creating `migrations/152.sql`
- **Conventions**: Tables prefixed `ttrss_` (e.g., `ttrss_feeds`, `ttrss_entries`, `ttrss_user_entries`)
- **Special Feeds**: Negative IDs (constants in `classes/Feeds.php`): -1 (Starred), -2 (Published), -3 (Fresh), -4 (All), -6 (Recently Read)

### Input Validation & Sanitization
- **HTML Content**: Use `Sanitizer::sanitize()` for user-generated HTML (e.g., feed content, article text)
  ```php
  $clean_html = Sanitizer::sanitize($content, $strip_images = false, $owner_uid, $site_url);
  ```
- **User Input**: Use `clean()` function for HTTP parameters where HTML is not needed
  ```php
  $feed_id = clean($_REQUEST['id']);  // Strips tags, trims whitespace
  // For arrays:
  $selected_ids = clean($_REQUEST['ids']);  // Applies to each element
  ```
- **Prefer Centralized Helpers**: Avoid duplicating validation logic - use existing helpers or add to appropriate utility class
- **Type Casting**: Validate and cast types explicitly (e.g., `(int)$id`, `(bool)$flag`) after cleaning
- **Examples**: See `classes/Feeds.php`, `classes/Article.php` for typical sanitization patterns

### Translation & Internationalization
- **PHP Backend**: Use `__($msgid)` function for translatable strings (from `lib/gettext/gettext.inc.php`)
  ```php
  echo __("Hello, world!");  // Returns translated string
  // Plural forms:
  $msg = _ngettext("article", "articles", $count);
  ```
- **JavaScript Frontend**: Use global `__()` function (defined in `js/common.js`)
  ```javascript
  alert(__("This function is only available in combined mode."));
  // Fallback to English if App.l10n not available
  ```
- **Plugin Translations**: Plugins use `_dgettext()` via `Plugin::__()` method
  ```php
  $this->__("Plugin-specific string");  // Uses plugin's translation domain
  ```
- **Best Practice**: Always use translated strings for user-facing messages; English fallback acceptable for internal logging/debugging
- **Translation Files**: Managed via Weblate, extracted with `utils/rebase-translations.sh` (updates `messages.pot`)

### Security Patterns
- **CSRF**: Token in `$_SESSION["csrf_token"]`, validated unless `csrf_ignore()` returns true
- **Auth**: Session-based, sequence in `UserHelper::login_sequence()`
- **Sanitization**: See "Input Validation & Sanitization" section above

### Logging & Debugging
- **Debug Logging**: Use `Debug::log()` for development/diagnostic output (feed updates, plugin execution)
  ```php
  Debug::log("Processing feed: $feed_url", Debug::LOG_VERBOSE);
  Debug::log("Article data:", Debug::LOG_EXTENDED);
  if (Debug::get_loglevel() >= Debug::LOG_EXTENDED) {
      print_r($data);  // Only shown at extended level
  }
  ```
  - Log levels: `Debug::LOG_DISABLED` (-1), `Debug::LOG_NORMAL` (0), `Debug::LOG_VERBOSE` (1), `Debug::LOG_EXTENDED` (2)
  - Controlled via command-line options: `--log-level 1`, `--log /path/to/file.log`
  - Check if enabled: `Debug::enabled()`
- **Error Logging**: Use `Logger::log_error()` for production error tracking (logs to SQL/syslog/stdout)
  ```php
  Logger::log_error(E_USER_WARNING, "Failed to process feed: $error", __FILE__, __LINE__, $context);
  // Or shorter form:
  Logger::log(E_USER_NOTICE, "User logged in: $username");
  ```
- **User Errors**: Use `user_error()` for error conditions that should be logged and optionally displayed
  ```php
  user_error("Invalid parameter: $param", E_USER_WARNING);
  ```
- **Plugin Error Handling**: Hooks wrap plugin calls in try/catch - exceptions/errors automatically logged as `E_USER_WARNING`
- **Context**: Debug output includes timestamp and PID; Logger includes backtrace via `format_backtrace()`

## Critical Files & Entry Points
- **Main App**: `index.php` → loads session, plugins, renders main UI
- **API**: `api/index.php` → JSON API (see `classes/API.php`)
- **Prefs**: `prefs.php` → admin/preferences UI
- **Public**: `public.php` → unauthenticated endpoints (e.g., RSS feed publishing)
- **Update**: `update.php`, `update_daemon2.php` → feed updating

## Docker & Deployment

### Official Docker Images
- **Images**: Built via GitHub Actions (`.github/workflows/publish.yml`)
  - GitHub Container Registry: `ghcr.io/tt-rss/tt-rss` (app) and `ghcr.io/tt-rss/tt-rss-web-nginx` (web)
  - Docker Hub: `supahgreg/tt-rss` (app) and `supahgreg/tt-rss-web-nginx` (web)
- **PHP Version Strategy**:
  - Docker images use `PHP_SUFFIX` env var (currently `84`) to determine PHP version → runs on PHP 8.4
  - Codebase maintains backward compatibility with PHP 8.2 for non-Docker users
  - Source of truth for minimum version: `Config::sanity_check()` checks PHP 8.2.0
  - When updating Docker PHP version: change `ENV PHP_SUFFIX=84` in `.docker/app/Dockerfile`
- **Architecture**: Multi-container setup
  - **app**: Alpine-based PHP-FPM container (`.docker/app/Dockerfile`)
    - Installs PHP extensions via Alpine packages: `php${PHP_SUFFIX}-<extension>`
    - Environment variables: `OWNER_UID/GID`, `PHP_WORKER_MAX_CHILDREN`, `PHP_WORKER_MEMORY_LIMIT`
    - Auto-configuration: `ADMIN_USER_PASS`, `AUTO_CREATE_USER`, etc.
    - Plugins: Automatically clones `nginx_xaccel` to `plugins.local/` on build
  - **web-nginx**: Nginx reverse proxy (`.docker/web-nginx/Dockerfile`)
    - Configurable via `APP_UPSTREAM`, `APP_WEB_ROOT`, `APP_BASE` env vars
    - To run tt-rss on root instead of `/tt-rss`: set `APP_WEB_ROOT=/var/www/html/tt-rss` and `APP_BASE=`
- **Volumes**: Map `/var/www/html/tt-rss` for persistent data and development
- **Environment**: `.env` file with `TTRSS_*` variables (see `config.php-dist`)
  - Database: `TTRSS_DB_HOST`, `TTRSS_DB_PORT`, `TTRSS_DB_USER`, `TTRSS_DB_PASS`
  - Docker Secrets: Support `<VAR>__FILE` suffix (e.g., `TTRSS_DB_PASS__FILE=/run/secrets/db_password`)
  - XDebug: `TTRSS_XDEBUG_ENABLED`, `TTRSS_XDEBUG_HOST`, `TTRSS_XDEBUG_PORT`

## Testing & Test Development

### Test Execution
- **Standard Tests**: Run `./phpunit` (uses `phpunit.xml` config, excludes `@group integration` and `tests/mocked/` directory)
- **Tests with Mocked Dependencies**: Place in `tests/mocked/` directory and run:
  ```bash
  ./phpunit --no-configuration --bootstrap tests/MockedDepsBootstrap.php tests/mocked/
  ```
- **Integration Tests**: Run `./phpunit --group integration` (requires database setup)

### Writing Unit Tests

#### Test Quality Guidelines

**Focus on high-value tests** - omit low-value or trivial tests:

**✅ Write tests for:**
- **Input validation & edge cases** - Invalid inputs, boundary conditions, security checks
- **Complex logic** - Algorithms, calculations, transformations with multiple code paths
- **Filtering & conditional behavior** - Logic that changes based on state/configuration
- **Error handling** - Exception paths, error recovery, validation failures
- **Format transformations** - Parsing, serialization, output formatting
- **State management** - Stateful operations where order matters

**❌ Skip low-value tests:**
- **Simple getters/setters without logic** - `getX()` that returns `$this->x`
- **Constant definitions** - Testing that `const FOO = 'FOO'` equals `'FOO'`
- **Trivial wrappers** - Pass-through methods with no logic
- **Impossible failures** - Conditions that can't occur in practice
- **Implementation details** - Testing private methods or internal structure

**Example - Debug.php tests (23 tests generated):**
- ✅ **Included**: `map_loglevel()` validation (rejects invalid levels), log level filtering (VERBOSE includes NORMAL), HTML vs plain text output formatting
- ❌ **Omitted**: Simple `get_loglevel()` / `set_loglevel()` without testing the filtering logic, constant value checks

**Goal**: Each test should verify meaningful behavior that could realistically break or regress.

#### Completeness Check: Review for Missing Tests

**After generating tests, systematically review the class for untested high-value methods:**

1. **List all public/static methods** in the class:
   ```bash
   grep -E '^\s*(public|static)\s+(static\s+)?function\s+\w+' classes/ClassName.php
   ```

2. **For each method, ask**:
   - Is it pure logic (no external I/O, HTTP, DB)?
   - Does it have multiple code paths or complex behavior?
   - Could it realistically break or regress?

3. **Common oversights**:
   - **Utility methods in classes with HTTP/DB methods** - Example: `UrlHelper::url_to_youtube_vid()` is pure regex but was overlooked because other methods do HTTP
   - **Static utility methods in otherwise complex classes** - Example: `FeedItem_Common::normalize_categories()` is pure transformation but overlooked among DOM parsing methods
   - **Public methods assumed to be covered** - Always verify with explicit checks

4. **If high-value methods are missing**:
   - Add tests immediately
   - Document why they were initially overlooked
   - Update this guide if the oversight reveals a systematic gap

**Example - UrlHelper oversight**:
- Generated 30 tests for `build_url()`, `rewrite_relative()`, `validate()`
- **Missed** `url_to_youtube_vid()` - pure regex with no Config dependencies
- **Cause**: Focused on methods already started in test file, didn't systematically check all public methods
- **Fix**: Added 12 YouTube tests in separate file `UrlHelper_YoutubeTest.php`

#### Standard Test Development
- **Standard Approach**: Place tests in `tests/` directory, extend `PHPUnit\Framework\TestCase`
- **PHPUnit Version**: Currently using PHPUnit 12.4.1 - prefers PHP 8 attributes over docblock annotations
- **Code Style**: Use **completely empty lines** (no whitespace) between methods and sections
  - ❌ **Bad**: Lines with only spaces/tabs (flagged by Rector and code quality tools)
  - ✅ **Good**: Completely empty lines with no characters at all
  - When generating test files, ensure blank lines contain zero characters
- **Database Dependencies**: Many tt-rss classes (`Prefs`, `Config`, `PluginHost`) have tight coupling to database
  - **Problem**: Classes like `Prefs` instantiate and call `Db::pdo()` in constructor, causing PDO errors in tests
  - **Solution**: Place test files in `tests/mocked/` directory and they will use `tests/MockedDepsBootstrap.php` automatically
  - **If test fails with missing class**: Add mock for that class to `tests/MockedDepsBootstrap.php` (see "Extending MockedDepsBootstrap" section)

### Multi-Version PHP Testing

**tt-rss tests against multiple PHP versions** in CI/CD (see `.github/workflows/php-code-quality.yml` for current matrix) - handle version-specific behavior properly:

#### Version-Specific Behavior Changes

**Example**: PHP 8.4 changed `DOMDocument::loadXML()` behavior:
- **PHP 8.3 and earlier**: `loadXML('')` returns `false` and sets libxml error
- **PHP 8.4+**: `loadXML('')` throws `ValueError: must not be empty`

#### Best Practice: Handle in Production Code

**Prefer fixing production code** over version-conditional tests:

```php
// FeedParser.php - GOOD approach
function __construct(string $data) {
    if (empty($data)) {
        $this->error = 'Empty feed data provided';
        return;  // Consistent behavior across all PHP versions
    }
    // ... rest of constructor
}
```

Benefits:
- Consistent behavior across PHP versions
- Better error messages
- Simpler tests (no version conditionals)

#### Alternative: Version-Conditional Tests (Use Sparingly)

Only when production code can't be changed:

```php
public function testVersionSpecificBehavior(): void {
    if (PHP_VERSION_ID >= 80400) {
        $this->expectException(ValueError::class);
    }

    $result = some_function();

    if (PHP_VERSION_ID < 80400) {
        $this->assertFalse($result);
    }
}
```

**Use `PHP_VERSION_ID`** for version checks (e.g., `80400` = PHP 8.4.0)

### Writing Unit Tests
- **Standard Approach**: Place tests in `tests/` directory, extend `PHPUnit\Framework\TestCase`
- **PHPUnit Version**: Currently using PHPUnit 12.4.1 - prefers PHP 8 attributes over docblock annotations
- **Database Dependencies**: Many tt-rss classes (`Prefs`, `Config`, `PluginHost`) have tight coupling to database
  - **Problem**: Classes like `Prefs` instantiate and call `Db::pdo()` in constructor, causing PDO errors in tests
  - **Solution**: Place test files in `tests/mocked/` directory and they will use `tests/MockedDepsBootstrap.php` automatically
  - **If test fails with missing class**: Add mock for that class to `tests/MockedDepsBootstrap.php` (see "Extending MockedDepsBootstrap" section)

### Mocking Database-Dependent Classes
When testing classes that depend on `Prefs`, `Config`, `PluginHost`, or `Db`:

1. **Place Test in `tests/mocked/` Directory**:
   ```php
   use PHPUnit\Framework\TestCase;

   final class YourTest extends TestCase {
   ```

2. **How MockedDepsBootstrap Works** (see `tests/MockedDepsBootstrap.php`):
   ```php
   <?php
   // Define mocks BEFORE loading vendor autoload
   if (!class_exists('Prefs')) {
       class Prefs {
           const STRIP_IMAGES = 'STRIP_IMAGES';
           // Mock static methods to prevent instantiation
           public static function get(string $pref_name, ?int $owner_uid = null, $profile = null) {
               return false;  // Don't instantiate - just return mock value
           }
           public function __construct() {
               // Empty - don't call Db::pdo()
           }
       }
   }
   // Repeat for Config, PluginHost, Db...
   require_once __DIR__ . '/../vendor/autoload.php';
   ```

3. **Key Insights**:
   - `Prefs::get()` is static but internally calls `get_instance()` → `__construct()` → `Db::pdo()`
   - Must mock the **static method** to prevent instantiation entirely
   - Use `if (!class_exists())` guards so mocks are defined before autoloader loads real classes
   - Mock `Config::get_user_agent()` if vendor autoload needs it

4. **Run Tests**:
   ```bash
   ./phpunit --no-configuration --bootstrap tests/MockedDepsBootstrap.php tests/mocked/
   ```

5. **Not Typical Pattern**: This bootstrap mocking approach is a pragmatic workaround for legacy code with tight coupling
   - Standard practice would use PHPUnit's `createMock()`/`createStub()` with dependency injection
   - tt-rss uses static methods and global state, making traditional mocking difficult
   - Consider this acceptable for testing legacy code without major refactoring

6. **Directory Organization**: Tests requiring mocked dependencies go in `tests/mocked/` directory
   - This directory is excluded from standard `phpunit.xml` test discovery
   - Run separately with custom bootstrap: `./phpunit --no-configuration --bootstrap tests/MockedDepsBootstrap.php tests/mocked/`
   - Keeps test organization clean and prevents accidental execution without proper mocking

7. **Extending MockedDepsBootstrap**: When writing new tests that encounter undefined classes or PDO errors:
   - **Identify the dependency**: Check error message for class name (e.g., "Undefined class 'Sessions'" or "could not find driver" from Sessions accessing Db)
   - **Add mock to bootstrap**: Add new `if (!class_exists('ClassName'))` block BEFORE vendor autoload
   - **Mock only what's needed**: Start with empty class/methods, add constants and methods as tests require them
   - **Follow existing patterns**: Mock static methods to return false/defaults, empty constructors to prevent DB access
   - **Current mocks**: `Config`, `PluginHost`, `Prefs`, `Db` - covers most common utility class testing needs
   - **Potential future needs**: `Sessions`, `Logger`, `UserHelper` - add only when actually needed for specific tests
   - **Keep it minimal**: Don't pre-emptively mock classes - wait until a test fails to add mocks

### Test Coverage
- **Standard Tests**: `tests/` directory
  - Pure utility functions and DOM parsing, no Config/Prefs/PluginHost/Db dependencies
  - Examples: `ErrorsTest`, `FeedParserTest`, `FeedItemTest`
  - Run with: `./phpunit --exclude-group integration`
- **Mocked Tests**: `tests/mocked/` directory
  - Classes that call Config/Prefs/PluginHost/Db static methods
  - Examples: `SanitizerUnitTest`, `UrlHelperTest`, `TimeHelperTest`, `CryptTest`
  - Run with: `./phpunit --no-configuration --bootstrap tests/MockedDepsBootstrap.php tests/mocked/`
- **Integration Tests**: `tests/integration/` - require database setup
- **Manual Testing**: Use Docker Compose setup with local source mounted

### Identifying Testable Classes - Key Insights

These guidelines help determine which classes are suitable for unit testing and which test type to use.

#### ✅ Highly Testable Classes (Standard tests/)

These classes use **only** pure PHP built-ins and utility functions - NO Config/Prefs/PluginHost/Db:

1. **FeedParser** - Pure XML/DOMDocument parsing
   - Dependencies: DOMDocument, DOMXPath, libxml, `Errors::format_libxml_error()`, `clean()`, `UConverter`
   - **Common misconception**: "Uses PluginHost" - FALSE! No plugin dependencies at all
   - Tests cover: RSS 2.0, Atom 1.0/0.3, RDF/RSS 1.0, error handling, type detection

2. **FeedItem_RSS / FeedItem_Atom / FeedItem_Common** - Feed item extraction
   - Dependencies: DOMDocument, DOMXPath, `clean()`, `UrlHelper::rewrite_relative()`
   - Tests cover: ID/link/date/title/content extraction, fallback behavior, priority rules

3. **Errors** - Error formatting utilities
   - Dependencies: None - pure string formatting
   - Tests cover: JSON error formatting, libXML error handling, UTF-8 transcoding

#### ⚠️ Testable with Mocks (tests/mocked/)

These classes call Config/Prefs/PluginHost static methods - need MockedDepsBootstrap:

4. **UrlHelper** - URL manipulation and HTTP fetching
   - Dependencies: `Config::get()` for HTTP_PROXY, timeouts, user agent
   - **Testable methods**: `build_url()`, `rewrite_relative()`, `validate()` - pure logic
   - **Needs mocking**: HTTP fetch methods that call Config

5. **Sanitizer** - HTML sanitization and XSS prevention
   - Dependencies: `PluginHost::getInstance()`, `Config::get_self_url()`, `Prefs::get()`
   - Tests cover: Script removal, attribute filtering, URL rewriting, security attributes

6. **TimeHelper** - Date/time formatting
   - Dependencies: `Prefs::get()` for date format strings and timezone
   - Testable: Epoch handling, timezone math (but format tests need Prefs mocking)

7. **Crypt** - Encryption/decryption
   - Dependencies: `Config::get(Config::ENCRYPTION_KEY)`
   - Tests cover: Key generation, encrypt/decrypt workflow, error handling

#### ❌ Require Integration Tests

These have tight database coupling or external dependencies:

8. **Handler classes** - Extend Handler/Handler_Protected/Handler_Administrative
   - Require: Sessions, database, full app context
   - Use `$this->pdo` for all operations

9. **Database-heavy utilities** - OPML, Labels (most methods), Article, Feeds
   - Most methods query/update database directly
   - Exception: `Labels::label_to_feed_id()` and `Labels::feed_to_label_id()` are pure math (testable)

10. **Mailer** - Email sending
    - Dependencies: `mail()` function, `PluginHost` hooks
    - External system interaction makes unit testing impractical

### How to Determine Test Type for a Class

**Step 1**: Search for dependency usage:
```bash
grep -E '(Config::|Prefs::|PluginHost::|Db::)' classes/YourClass.php
```

**Step 2**: Classify based on results:
- **No matches** → Standard test in `tests/` (e.g., FeedParser, FeedItem_*)
- **Only Config::get()** → Mocked test in `tests/mocked/` (e.g., UrlHelper, Crypt)
- **Config + Prefs + PluginHost** → Mocked test in `tests/mocked/` (e.g., Sanitizer, TimeHelper)
- **Db::pdo() or $this->pdo** → Integration test (e.g., Handlers, OPML)

**Step 3**: Verify by reading the actual source code
- Don't assume a class uses PluginHost just because it seems like it should
- Always check the actual implementation to verify dependencies

### Test Coverage
- **Unit Tests**: Limited coverage (see `tests/` directory)
  - Example: `tests/SanitizerTest.php` - 51 tests covering XSS prevention, HTML sanitization, URL rewriting
- **Integration Tests**: `tests/integration/` - require database setup
- **Manual Testing**: Use Docker Compose setup with local source mounted

## Common Gotchas
- **Config Changes**: Restart Docker containers after modifying `.env`
- **Plugin State**: Plugin data cached in `ttrss_plugin_storage` - may need DB clear for dev
- **ORM Caching**: Idiorm uses identity map - call `ORM::reset_db()` to clear
- **Database-Only**: PostgreSQL is the only supported database (MySQL support removed)
