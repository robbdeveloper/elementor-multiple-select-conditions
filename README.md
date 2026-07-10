# Elementor Dynamic Select

A WordPress plugin that adds a **Dynamic Select** field type to Elementor Pro forms. Options are populated client-side from a JSON ruleset based on the live values of one or more multi-select or checkbox source fields.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Elementor
- Elementor Pro (Forms module)

## Installation

1. Copy the plugin folder into `wp-content/plugins/elementor-multiple-select-conditions/` (or rename it as preferred).
2. Activate **Elementor Dynamic Select** in the WordPress admin.
3. Edit a page with an Elementor Pro Form widget.
4. Add source fields (multi-select and/or checkbox) with unique **Field IDs**.
5. Add a **Dynamic Select** field and paste your rules JSON.

## Quick start

1. Create two source fields:
   - `country` (multi-select or checkbox)
   - `category` (multi-select or checkbox)
2. Add a Dynamic Select field with Field ID `product`.
3. Paste the sample rules from [`schema/example-rules.json`](schema/example-rules.json).
4. Publish and test on the frontend.

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

## Security

- Rules JSON is embedded in a `data-eds-rules` attribute with proper escaping.
- On submit, the same rules are re-evaluated server-side to reject tampered values.
- Invalid JSON renders a safe empty select with a `data-eds-error` attribute.

## JSON Schema

See [`schema/rules.schema.json`](schema/rules.schema.json) for the full schema definition.

## File structure

```
elementor-dynamic-select.php
includes/
  class-plugin.php
  class-rules-schema.php
  class-rules-evaluator.php
  fields/
    class-dynamic-select-field.php
assets/
  js/dynamic-select.js
  js/editor-preview.js
  css/dynamic-select.css
schema/
  rules.schema.json
  example-rules.json
```

## License

GPL-2.0-or-later
