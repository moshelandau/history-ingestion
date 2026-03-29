# History Ingestion Pipeline

Auto-generate a developer portfolio website from your git history.

## Quick Start

Run the full pipeline with one command:

```bash
php bin/pipeline
```

This executes four stages in sequence:

1. **Ingest** - Collect commit history from local git repos (or GitHub API)
2. **Generate** - Transform history into a site specification (`site-spec.json`)
3. **Render** - Render the spec into a static HTML/CSS site
4. **Deploy** - Deploy the rendered site to GitHub Pages

### Prerequisites

- PHP 8.4+
- `composer install`
- `config/projects.php` configured with your projects
- `GITHUB_TOKEN` env var (for `--github` mode and deployment)
- `config/deploy.php` (for deployment, copy from `config/deploy.php.example`)

### Options

```
php bin/pipeline [options]

--output-dir=<path>   Output directory                    [default: output]
--github              Use GitHub API ingestor (requires GITHUB_TOKEN)
--max-commits=<n>     Max commits per repo                [default: 500]
--skip-deploy         Stop after render (skip deployment)
--skip=<stages>       Comma-separated stages to skip (ingest,generate,render,deploy)
--help, -h            Show this help
```

### Examples

```bash
# Full pipeline (local git only)
php bin/pipeline

# Full pipeline with GitHub API fallback
php bin/pipeline --github

# Generate and render only (skip ingest and deploy)
php bin/pipeline --skip=ingest,deploy

# Everything except deployment
php bin/pipeline --skip-deploy

# Custom output directory
php bin/pipeline --output-dir=/tmp/my-site
```

## Running Individual Stages

Each stage can also be run independently:

```bash
php bin/ingest [--output=<path>] [--github] [--max-commits=<n>]
php bin/generate [--input=<path>] [--output=<path>]
php bin/render [--input=<path>] [--output=<path>]
php bin/deploy --site=<path> [--output=<path>]
```

## Dashboard

Preview and edit the generated site spec:

```bash
php bin/dashboard
```

## Docker

Run the pipeline without installing PHP or Composer locally:

```bash
# Build the image
docker compose build

# Run the full pipeline with GitHub API
docker compose run --rm pipeline bin/pipeline --github

# Run with local git only
docker compose run --rm pipeline bin/pipeline

# Skip deployment
docker compose run --rm pipeline bin/pipeline --skip-deploy
```

Set `GITHUB_TOKEN` in a `.env` file or export it before running.

## Testing

```bash
composer test
```

## Linting

```bash
composer lint
```
