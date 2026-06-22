# BriBoi Repository Assessment

Date: 2026-06-22

## Executive summary

BriBoi is a small, early-stage open-source biodiversity intelligence repository with two clearly named modules:

- `bri_cibralab_vai`: Biodiversity Risk Intelligence (BRI).
- `boi_cibralab`: Biodiversity Opportunity Intelligence (BOI).

The project has a useful governance baseline at the repository root, including license, notice, contribution, security and conduct documents. The BRI module is substantially more developed than BOI: it includes PHP-based data generation, static public pages, sample/generated public data and an optional AI interpretation layer. BOI is currently a placeholder with a minimal static page, a minimal PHP script and a minimal configuration file.

The most important near-term improvements are documentation consistency, security hardening of browser rendering, repository hygiene, and minimal validation automation. No secrets were found in the repository during this review, but there are several best-practice gaps that should be addressed before wider public deployment.

## Scope and review method

This assessment reviewed the repository structure, root documentation, module documentation, PHP scripts, static web assets, JSON configuration and GitHub configuration. The review was conservative and did not change application code.

Checks performed:

- Repository file inventory using `find`.
- Targeted text review of README, contribution, security, conduct, module, PHP, HTML, JavaScript, CSS, JSON and Dependabot files.
- Secret-pattern scan using `rg` for common key, token, password and private-key indicators.
- PHP syntax checks using `php -l`.
- JSON syntax checks using Python `json.load`.
- Git status checks before committing this documentation-only report.

## 1. Project structure and organization

### Current structure

At the time of review, the repository is organized as follows:

```text
BriBoi/
├── .github/
│   └── dependabot.yml
├── boi_cibralab/
│   ├── config/
│   │   └── actions_catalog.json
│   ├── public/
│   │   └── index.html
│   ├── scripts/
│   │   └── update.php
│   └── README.md
├── bri_cibralab_vai/
│   ├── config/
│   │   ├── regions.json
│   │   └── settings.php
│   ├── public/
│   │   ├── assets/
│   │   ├── data/
│   │   ├── reports/
│   │   ├── index.html
│   │   ├── methodology.html
│   │   └── sources.html
│   ├── scripts/
│   │   └── update.php
│   ├── AGENTS.md
│   ├── LICENSE
│   ├── README.md
│   └── README_INSTAL_LACIO.txt
├── AGENTS.md
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── LICENSE
├── NOTICE
├── README.md
└── SECURITY.md
```

### Strengths

- The two modules are separated into different top-level directories, matching the intended BRI/BOI separation.
- Root governance files are present: `LICENSE`, `NOTICE`, `SECURITY.md`, `CONTRIBUTING.md` and `CODE_OF_CONDUCT.md`.
- BRI has a deployment-oriented structure suitable for simple PHP/shared-hosting environments: `config`, `scripts`, and static `public` assets.
- BRI's optional AI configuration reads secrets from environment variables rather than hard-coding them.
- BRI includes static generated data and reports, which makes the demo viewable without a database or backend server.

### Issues and risks

- The root `README.md` repository layout is outdated. It lists `BRI/`, `BOI/` and `docs/`, while the actual module directories are `bri_cibralab_vai/` and `boi_cibralab/`.
- BOI is underdeveloped compared with BRI and currently has very little documentation or functionality.
- Generated runtime outputs appear to be committed under `bri_cibralab_vai/public/data/` and `bri_cibralab_vai/public/reports/`. This may be intentional for a static demo, but it should be explicitly documented as sample/generated public data.
- There is no root `.gitignore`, even though BRI documentation says `.gitignore` excludes `.env`, logs, keys and backups.
- There is no `docs/` directory before this assessment report, despite the root README showing one in the desired layout.

## 2. Documentation quality

### Strengths

- The root README states the mission, principles, components, roadmap and disclaimer.
- The root documentation establishes a basic contributor and security process.
- BRI documentation explains installation on IONOS/shared hosting, cron execution and optional AI activation.
- BRI public pages include methodology and source pages, which is important for scientific credibility and explainability.
- The project clearly states that BRI is informational and does not replace medical, legal, environmental or official advice.

### Gaps

- Documentation language is inconsistent: root documentation is in English, while much of BRI documentation and public content is in Catalan. This may be appropriate for the target audience, but the repository should declare its language strategy.
- `boi_cibralab/README.md` contains only a title. It does not describe purpose, status, setup, data model, roadmap or limitations.
- The BRI README mentions `.env.example`, but no `.env.example` file is present.
- The BRI README mentions `.gitignore`, but no root `.gitignore` file is present.
- The root README's structure diagram does not reflect the current repository.
- There is no architecture document explaining how BRI and BOI should remain separated, where shared abstractions may live, and what an integration would require.
- There is no operational runbook documenting cron setup, generated files, rollback, monitoring, failure modes or expected hosting permissions.
- There is no explicit data governance document covering open-data source selection, provenance, refresh cadence, licensing, retention and generated report publication.

## 3. Security issues and risks

### Positive findings

- No hard-coded real API keys, tokens, passwords or private keys were found by the secret-pattern scan.
- BRI reads the optional AI API key from `BRI_AI_API_KEY` and keeps AI disabled unless `BRI_AI_ENABLED=1`.
- The PHP AI call has a timeout, which reduces the risk of hanging cron jobs.
- Generated HTML report content is escaped with `htmlspecialchars` in the PHP report generation path.
- External links in BRI signal cards use `rel="noopener"` when opening a new tab.
- BRI includes clear disclaimers that it does not issue official alerts or medical advice.

### Risks to prioritize

1. **Client-side HTML injection risk**

   `bri_cibralab_vai/public/assets/app.js` renders values from JSON data directly with template literals and `innerHTML`. If an upstream RSS item, generated JSON file or future data source contains malicious HTML, it could be rendered in the browser. This is the highest-priority security hardening item because RSS and other external data are treated as display content.

   Recommended mitigation:

   - Escape all dynamic text before inserting it into HTML.
   - Prefer DOM APIs (`textContent`, `createElement`) for untrusted content.
   - Validate and sanitize external URLs before rendering links.

2. **Remote link validation**

   RSS links are rendered into anchor `href` attributes without an allowlist or URL scheme check. Add validation to only allow `http:` and `https:` URLs.

3. **Public generated data policy**

   The update process writes public JSON and HTML outputs into `public/data` and `public/reports`. That can be safe for a static public site, but the repository should document that these outputs must never include secrets, private data, raw logs or sensitive records.

4. **Missing `.gitignore`**

   The repository should add a `.gitignore` to prevent accidental commits of `.env`, logs, backups, local dependency folders and generated private artifacts. This is especially important because the documentation instructs users not to commit secrets.

5. **External JavaScript/CSS dependencies loaded from CDN**

   BRI loads Leaflet assets from `unpkg.com`. This is simple and appropriate for early prototypes, but production deployments should consider Subresource Integrity (SRI), version pinning, vendoring assets, or a documented CDN trust model.

6. **Network fetch robustness**

   BRI fetches external RSS and API data in a cron script. It already has timeouts and fallbacks, but a future hardening pass should document failure behavior and add structured logging with log rotation guidance.

7. **AI output trust boundary**

   Optional AI output is stored as JSON and displayed in the browser. Treat AI output as untrusted user-facing text and escape it consistently.

## 4. Missing files or repository best practices

Recommended additions, in conservative priority order:

1. `.gitignore` covering `.env`, logs, backups, local caches and dependency folders.
2. `.env.example` for BRI with placeholder environment variables only.
3. `docs/architecture.md` explaining the BRI/BOI boundary and deployment model.
4. `docs/data-governance.md` describing data provenance, source licensing, refresh cadence and public-output policy.
5. `docs/security-hardening.md` or expanded `SECURITY.md` with operational risks, secret management and responsible disclosure expectations.
6. `docs/operations.md` for cron setup, generated files, rollback and failure handling.
7. Minimal CI workflow for syntax checks:
   - PHP lint for `*.php`.
   - JSON validation for `*.json`.
   - Optional link check or static HTML validation later.
8. BOI README expansion.
9. Issue and pull request templates under `.github/`.
10. A changelog or release notes process.
11. License clarification for generated/sample data and external data source outputs.

## 5. Opportunities for improvement

### Documentation and governance

- Update the root README to match actual paths and module names.
- Expand BOI documentation before adding functionality.
- Document whether committed `public/data` files are demo snapshots, canonical generated outputs or examples only.
- Add a short maintainer checklist for releases and deployments.

### Security and reliability

- Escape or DOM-render all dynamic frontend content.
- Add URL validation for RSS-derived links.
- Add a `.gitignore` and `.env.example`.
- Add CI for PHP and JSON syntax.
- Add a lightweight Content Security Policy recommendation for deployments.

### Scientific credibility

- Expand methodology with known limitations, confidence interpretation, data-source caveats and validation plans.
- Document how scores should be calibrated and reviewed by domain experts.
- Track data provenance per signal and generated report.
- Add a distinction between signals, hypotheses, confidence, risk scores and official alerts.

### Maintainability

- Split the large BRI update script into smaller functions or files once functionality grows.
- Add testable pure functions for risk scoring and classification.
- Add small fixture-based tests for score calculations and JSON output shape.
- Establish naming conventions for modules and generated artifacts.

## 6. Potential technical debt

- **Single large BRI update script:** `bri_cibralab_vai/scripts/update.php` combines data fetching, scoring, AI calling, JSON writing and HTML report generation. This is understandable for a prototype, but it will become harder to test and maintain.
- **Direct `innerHTML` rendering:** Frontend rendering is concise but mixes data and HTML without escaping helpers.
- **Tracked generated data:** Committed generated outputs can create noisy diffs and stale snapshots unless explicitly managed.
- **Minimal BOI implementation:** BOI's placeholder state creates an imbalance between the two modules and leaves the opportunity-intelligence concept underspecified.
- **Dependabot configuration mismatch:** Dependabot is configured for `pip` ecosystems in both modules, but no Python dependency manifest was found. If PHP remains the main runtime, Dependabot configuration should be adjusted to actual dependency managers.
- **No automated validation:** Manual syntax checks pass, but there is no CI guardrail to prevent invalid PHP or JSON from being merged.
- **Documentation drift:** Several files refer to missing or differently named paths, indicating that documentation updates are not yet part of the development workflow.

## 7. Recommended roadmap for the next 30 days

### Week 1: Repository hygiene and documentation alignment

- Add `.gitignore` and `bri_cibralab_vai/.env.example` with safe placeholders.
- Update root README layout to reflect actual directories.
- Expand BOI README with status, purpose, planned scope and limitations.
- Document generated public data and report files.
- Add issue and pull request templates.

### Week 2: Security hardening

- Replace unsafe frontend `innerHTML` patterns or add strict escaping helpers before rendering untrusted JSON/RSS/AI content.
- Validate external links before displaying them.
- Add a deployment note for Content Security Policy and CDN trust/SRI options.
- Review all generated public outputs to ensure no private data, logs or secrets can be published.

### Week 3: Validation and testing

- Add a minimal GitHub Actions workflow for PHP syntax and JSON validation.
- Add small fixture-based tests for BRI scoring logic after extracting pure scoring functions.
- Document manual validation steps for shared-hosting deployments.
- Confirm Dependabot matches actual package ecosystems, or remove unused ecosystems until manifests exist.

### Week 4: Scientific and operational maturity

- Create an architecture document describing BRI, BOI and future integration boundaries.
- Create a data governance document for sources, provenance, licensing and refresh cadence.
- Expand BRI methodology with calibration assumptions, uncertainty, confidence and expert-review needs.
- Define a BOI v0.1 data model and minimal opportunity-scoring method.
- Prepare a small public release checklist covering syntax checks, generated files, documentation, security review and disclaimer review.

## Suggested priority backlog

| Priority | Item | Impact | Risk |
| --- | --- | --- | --- |
| P0 | Escape dynamic frontend content | Reduces XSS risk | Low to medium |
| P0 | Add `.gitignore` and `.env.example` | Prevents accidental secret commits | Low |
| P1 | Add PHP/JSON CI checks | Prevents syntax regressions | Low |
| P1 | Align README paths and BOI docs | Improves contributor onboarding | Low |
| P1 | Document generated public data policy | Reduces accidental data exposure | Low |
| P2 | Split BRI update script into testable units | Improves maintainability | Medium |
| P2 | Add data governance and architecture docs | Improves scientific credibility | Low |
| P2 | Calibrate scoring methodology with experts | Improves quality and trust | Medium |

## Conclusion

BriBoi has a solid early foundation for a lightweight, explainable biodiversity intelligence project. The root governance files and BRI's simple PHP/static architecture are appropriate for conservative shared-hosting deployment. The next phase should focus on small, low-risk improvements: align documentation, add repository hygiene files, harden frontend rendering, add minimal validation automation and clarify data governance. These steps will improve safety, maintainability and credibility without introducing heavy dependencies or disrupting the current deployment model.
