# [Silverstripe Content Replace]

This module replaces certain tags in WYSIWYG content with SilverStripe templates.

Currently supports:

* Replacing `file_link` shortcode with `Symbiote/ContentReplace/WYSIWYGFileLink.ss` template.

## Composer Install

```sh
composer require symbiote/silverstripe-contentreplace:dev-ss6
```

Your must add this fork's URL to a repository entry in your project:

```json
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/nswdpc/silverstripe-contentreplace.git"
        }
    ]
```

## Requirements

* See composer.json

## Documentation

* [Quick Start](docs/en/quick-start.md)

## Licence
* [License](LICENSE.md)

## Contributing
* [Contributing](CONTRIBUTING.md)
