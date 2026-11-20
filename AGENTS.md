<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

---

# Panduan Permanen Proyek (`laundry-kos`)

> Aturan di bawah ini melengkapi `<laravel-boost-guidelines>` di atas dan wajib dipatuhi pada seluruh fase pembangunan aplikasi.

## 1. Dokumen Acuan
- **Acuan utama:** `docs/RANCANGAN-LAUNDRY-WEB.md` dan `docs/KEPUTUSAN.md`.
- Apabila terdapat perbedaan antara keduanya, **`docs/KEPUTUSAN.md` yang berlaku** (multi-properti untuk 1 owner, 4 peran, istilah **properti**, Tailwind CSS v4 `@theme`, PHPUnit + MySQL, pembagian Fase 1a/1b).
- **Hemat konteks:** baca `docs/RANCANGAN-LAUNDRY-WEB.md` hanya pada bagian yang relevan (`grep -n '^## \|^### ' docs/RANCANGAN-LAUNDRY-WEB.md`).

## 2. Stack Terkunci & Lingkungan Dev
- **Backend:** PHP 8.3+, Laravel 13.43+, MySQL 8 (`db_laundry_app`).
- **Frontend:** Blade + Tailwind CSS **v4** (`@theme` di `resources/css/app.css`, `@tailwindcss/vite`) + Alpine.js + `@fontsource/roboto`. **DILARANG** memakai Livewire, Inertia, `tailwind.config.js` (v3), atau paket baru tanpa persetujuan user.
- **Node.js Laragon:** `node` / `npm` tidak berada di default `$env:PATH`. Selalu awali perintah Node/Vite dengan:
  `$env:PATH = "C:\laragon\bin\nodejs\node-v20.19.1-win-x64;" + $env:PATH;`
- **Mobile-first:** tulis class CSS dasar untuk layar HP (target sentuh ≥ 44 px, navigasi bottom bar), lalu perluas ke desktop dengan prefix `md:` / `lg:`.
- **Konfirmasi & kebersihan View Page Source:** setiap aksi sensitif (logout, hapus, ubah status aktif, pindah kamar/keluar, perubahan pengaturan, dan update data kritis) wajib memakai atribut `data-confirm-title`, `data-confirm-message`, `data-confirm-label`, dan `data-confirm-variant` yang ditangani terpusat oleh `<x-dialog-konfirmasi />` dan `resources/js/app.js`. DILARANG menulis `<script>` atau `<style>` inline custom di Blade.

## 3. Arsitektur & Aturan Domain Wajib
- **Controller tipis:** validasi di `FormRequest`, hak akses di `Policy`, logika bisnis di `app/Services/`.
- **Lingkup properti:** seluruh data operasional (`kamar`, `penghuni`, `laundry`, `pengaturan`, `audit_log`) wajib terikat ke `properti_id`. Karyawan (`admin`/`penjaga`) terikat ke tepat satu `users.properti_id`; maksimal 1 `admin` aktif per properti (dijaga `users.admin_properti` `storedAs` + unique index).
- **Kuota & perhitungan aman:**
  - Kuota selalu dihitung per **`penghuni_id`** per **bulan kalender** (zona waktu `Asia/Jakarta`), **TIDAK PERNAH** difilter per `kamar_id`.
  - Gunakan perhitungan desimal aman (satuan `0,01 kg` integer atau `bcmath` + agregasi SQL `DECIMAL`), **jangan pakai float**.
  - `toleransi_kg` bersifat relatif (`kelebihan >= toleransi_kg`, bukan angka hardcoded). Catatan yang melewati kuota tetap ditandai merah meskipun di bawah toleransi.
- **Tipe data:** uang = integer rupiah (`int unsigned`); tanggal sewa/laundry = `DATE` (tanpa jam); zona waktu = `Asia/Jakarta`; perubahan penting tercatat di `audit_log`; aksi multi-tabel dibungkus `DB::transaction()` + `lockForUpdate()`.

## 4. Pengujian & Keamanan Data
- **Database tes wajib:** PHPUnit menjalankan tes pada **`db_laundry_app_test`** (dikonfigurasi di `phpunit.xml`). **JANGAN PERNAH** menjalankan `RefreshDatabase` atau test suite terhadap `db_laundry_app`.
- **Data pribadi:** berkas di `storage/app/seed/` berisi data pribadi nyata dan tidak boleh di-commit atau dicetak utuh di log.

## 5. Aturan Penggunaan Agent Skills
1. **`graphify`:** Gunakan untuk analisis arsitektur, relasi modul, dan dampak perubahan lintas file setelah struktur domain/service mulai terbentuk (rencana awal dibangun setelah Fase 2, lalu `--update` di tiap fase berikutnya). Jangan membangun graph untuk pencarian teks sederhana; jaga `graphify-out/` tetap di-ignore Git.
2. **`prompt-master`:** Gunakan **hanya** apabila user secara eksplisit meminta pembuatan, perbaikan, atau adaptasi prompt untuk AI tool lain. Jangan digunakan untuk coding, debugging, atau dokumentasi biasa.
3. **`modern-web-guidance`:** Gunakan sebagai acuan pola HTML/CSS/JS modern, dialog native (`<dialog>`), aksesibilitas, mobile-first, popover/toast, dan print CSS (`@page`, `print-color-adjust`). (Gunakan Node Laragon pada `.agents/skills/modern-web-guidance/`).

## 6. Alur Kerja Sesi
Kerjakan **satu fase per sesi**: Rencana tanpa kode → tunggu persetujuan user → implementasi dalam commit kecil → verifikasi dengan `php artisan test` + `npm run build` dan laporkan hasil aktual.
