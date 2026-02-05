# Sermon Formatter

A Statamic addon that automates sermon notes formatting. Upload `.docx` or `.rtf` files and the addon uses Claude AI to format them into clean Markdown, which is then converted to Bard field content.

## Installation

```bash
composer require newsong/sermon-formatter
php artisan migrate
```

## Configuration

### Environment Variables

```env
ANTHROPIC_API_KEY=sk-ant-...
SERMON_FORMATTER_MODEL=claude-sonnet-4-20250514
SERMON_FORMATTER_MAX_TOKENS=8192
SERMON_FORMATTER_QUEUE_NAME=default
SERMON_FORMATTER_RATE_LIMIT=1
```

### Publish Config

```bash
php please vendor:publish --tag=sermon-formatter-config
```

### Publish Fieldsets

```bash
php please vendor:publish --tag=sermon-formatter-fieldsets
```

## Usage

### Fieldtype

Add the `sermon_source` fieldtype to your blueprint:

```yaml
fields:
  -
    import: sermon-formatter::sermon_source
```

Or manually:

```yaml
fields:
  -
    handle: sermon_source
    field:
      type: sermon_source
      display: 'Sermon Source'
      target_field: notes
```

### Control Panel

Navigate to **Tools > Sermon Formatter** in the control panel to:

- View processing stats and recent activity
- Edit the formatting specs (system prompt)
- Browse processing logs

### Artisan Commands

```bash
# Test Claude API connectivity
php artisan sermon-formatter:test-claude

# Bulk re-process failed entries
php artisan sermon-formatter:bulk-process --status=failed

# Bulk re-process with dry run
php artisan sermon-formatter:bulk-process messages --dry-run
```

## How It Works

1. User uploads a `.docx` or `.rtf` file via the SermonSource fieldtype
2. The file is stored temporarily and a processing job is queued
3. The job parses the document text using PHPWord
4. The text is sent to Claude with formatting instructions
5. Claude returns formatted Markdown
6. The Markdown is converted to HTML, then to ProseMirror JSON (Bard format)
7. The entry's target Bard field is updated with the formatted content
8. The temp file is cleaned up

## Permissions

- **View Sermon Formatter**: Access dashboard and logs
- **Process Sermons**: Upload documents and trigger processing
- **Manage Sermon Formatter Settings**: Edit formatting specs

## Requirements

- PHP 8.4+
- Statamic 6.0+
- An Anthropic API key
