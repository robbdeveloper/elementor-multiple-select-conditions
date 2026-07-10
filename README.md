# Elementor Dynamic Select

A WordPress plugin that adds a **Conditional Dynamic Select** field type to Elementor Pro forms. Options update instantly on the frontend based on the live values of other form fields (multi-select, checkbox, or single-value fields), driven by a JSON ruleset you configure per field.

**Repository:** [github.com/robbdeveloper/elementor-multiple-select-conditions](https://github.com/robbdeveloper/elementor-multiple-select-conditions)

## Features

- Custom Elementor Pro form field type
- Per-field JSON rules configuration (inline or via URL)
- Reusable named option sets
- AND/OR condition groups
- Client-side instant updates
- Server-side validation against the same rules

## Requirements

- WordPress 6.0+
- PHP 7.4+
- [Elementor](https://wordpress.org/plugins/elementor/)
- [Elementor Pro](https://elementor.com/) (Forms module)

## Installation

### From a GitHub Release (recommended)

1. Open the [Releases](https://github.com/robbdeveloper/elementor-multiple-select-conditions/releases) page.
2. Download `elementor-dynamic-select.zip` from the latest release.
3. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
4. Upload the zip file and click **Install Now**, then **Activate**.
5. Ensure Elementor Pro is active.

### Manual install

1. Copy the plugin folder into `wp-content/plugins/elementor-dynamic-select/` (or any folder name you prefer).
2. Activate **Elementor Dynamic Select** in **Plugins**.
3. Ensure Elementor Pro is active.

## Quick start

1. Edit a page with an Elementor Pro **Form** widget.
2. Add source fields with unique **Field IDs** (multi-select and/or checkbox work best):
   - `country`
   - `category`
3. Add a **Conditional Dynamic Select** field with Field ID `product`.
4. In the field settings, paste your rules JSON under **Rules JSON** (see [example-rules.json](schema/example-rules.json)).
5. Publish and test on the frontend — changing source fields should rebuild the select options instantly.

## Field settings

Each Dynamic Select field supports:

| Setting | Description |
|---------|-------------|
| **Rules JSON** | Inline JSON ruleset (primary input) |
| **Rules JSON URL** | Optional fallback URL if inline JSON is empty |
| **Placeholder Text** | Shown before a valid option is chosen |
| **No Match Text** | Shown when no rule matches |

Standard Elementor form settings (label, required, Field ID, etc.) work as usual.

## JSON ruleset format

The ruleset is versioned, reusable, and designed to scale across forms.

```json
{
  "version": 1,
  "behavior": {
    "match": "first",
    "preserveSelection": true,
    "emptyText": "Select an option",
    "noMatchText": "No options available"
  },
  "sources": ["country", "category"],
  "optionSets": {
    "euElectronics": [
      { "value": "tv", "label": "Television" },
      { "value": "phone", "label": "Phone" }
    ]
  },
  "rules": [
    {
      "id": "eu-electronics",
      "when": {
        "all": [
          {
            "field": "country",
            "operator": "includesAny",
            "value": ["de", "fr"]
          },
          {
            "field": "category",
            "operator": "includes",
            "value": "electronics"
          }
        ]
      },
      "use": "euElectronics"
    }
  ],
  "default": {
    "use": null
  }
}
```

### Top-level keys

| Key | Required | Description |
|-----|----------|-------------|
| `version` | Yes | Ruleset schema version (`1`) |
| `behavior` | No | Matching and UX behavior |
| `sources` | No | Explicit source field IDs (also inferred from rules) |
| `optionSets` | No | Reusable named option lists |
| `rules` | Yes* | Ordered conditional rules |
| `default` | No | Fallback when no rule matches |

\* At least one rule or a default must be defined.

### Behavior

| Key | Values | Default | Description |
|-----|--------|---------|-------------|
| `match` | `first`, `merge` | `first` | Stop at first matching rule or merge all matches |
| `preserveSelection` | boolean | `true` | Keep current value if still valid after rebuild |
| `emptyText` | string | — | Placeholder when options exist |
| `noMatchText` | string | — | Message when no options match |

### Conditions (`when`)

Each rule uses a `when` block with one or both groups:

- `all` — every condition must match (AND)
- `any` — at least one condition must match (OR)

Each condition:

```json
{
  "field": "country",
  "operator": "includesAny",
  "value": ["de", "fr"]
}
```

### Supported operators

| Operator | Description |
|----------|-------------|
| `includes` | Source includes at least one expected value |
| `includesAll` | Source includes all expected values |
| `includesAny` / `intersects` | Source intersects expected values |
| `equals` | Source values exactly equal expected set |
| `in` | Any source value is in the expected list |
| `any` | Source field has at least one value |

### Options

Rules can reference reusable sets or define inline options:

```json
{ "use": "euElectronics" }
```

```json
{
  "options": [
    { "value": "fiction", "label": "Fiction" }
  ]
}
```

## JSON Schema

See [schema/rules.schema.json](schema/rules.schema.json) for the full schema definition and [schema/example-rules.json](schema/example-rules.json) for a working example.

## Security

- Rules JSON is embedded in a `data-eds-rules` attribute with proper escaping.
- On submit, the same rules are re-evaluated server-side to reject tampered values.
- Invalid JSON renders a safe empty select with a `data-eds-error` attribute.

## Releasing a new version

Releases are automated via GitHub Actions. The plugin header version is the single source of truth.

1. Bump the version in **two places**:
   - `Version:` in [elementor-dynamic-select.php](elementor-dynamic-select.php) (and the `EDS_VERSION` constant)
   - `Stable tag:` in [readme.txt](readme.txt)
2. Add a changelog entry under `== Changelog ==` in `readme.txt`.
3. Commit and push to `main`.

The [Release workflow](.github/workflows/release.yml) will:

- Read the version from the plugin header
- Skip if a release for that version already exists
- Build a clean `elementor-dynamic-select.zip` (using [.distignore](.distignore))
- Create a GitHub Release tagged `v<version>` with the zip attached

No manual tagging or zip building required.

## File structure

```
elementor-dynamic-select.php      # Plugin bootstrap
includes/
  class-plugin.php                # Hooks, assets, field registration
  class-rules-schema.php          # JSON parsing and validation
  class-rules-evaluator.php       # Server-side rule evaluation
  fields/
    class-dynamic-select-field.php
assets/
  js/dynamic-select.js            # Frontend option rebuilding
  js/editor-preview.js            # Elementor editor preview
  css/dynamic-select.css
schema/
  rules.schema.json               # JSON Schema for rulesets
  example-rules.json              # Example ruleset
readme.txt                        # WordPress.org-style readme
.github/workflows/release.yml     # Automated release workflow
```

## License

GPL-2.0-or-later
