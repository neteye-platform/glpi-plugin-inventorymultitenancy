# Inventory Multitenancy

[![GLPI](https://img.shields.io/badge/GLPI-%3E%3D%2010.0.6-blue.svg)](https://glpi-project.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE-MIT)
[![License: Apache 2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](./LICENSE-APACHE)

A [GLPI](https://glpi-project.org/) plugin that enforces **tenant isolation** during
native inventory import, so that assets discovered by the GLPI inventory agent can
never be created outside the boundary of the entity they belong to.

## Introduction

GLPI ships with a native inventory engine. When an asset is imported, the
`RuleImportEntity` rule engine decides in **which entity** the asset will be
created. In a multitenant deployment — where each customer (tenant) is mapped to
a GLPI entity and inventory agents authenticate within a specific entity — the
default rule engine can resolve a target entity that lies **outside** the tenant
currently performing the import. This would let one tenant's inventory data leak
into another tenant's entity.

The **Inventory Multitenancy** plugin closes this gap. It intercepts the result
of the entity import rules and guarantees that every imported asset stays inside
the **active entity** of the session performing the import (or one of its
sub-entities).

## How it works

The plugin registers a callback on a custom hook,
`post_process_import_entity_rules`, that is fired right after GLPI has evaluated
the entity import rules for an asset. On each import, the plugin applies the
following logic:

1. **Sub-entity is allowed** — if the entity resolved by the rules is a
   descendant of the current active entity, the resolution is accepted as-is.
   This keeps legitimate placement into child entities working.
2. **Safety check** — if no active entity can be determined for the current
   session (and it is not the root entity `0`), an exception is thrown to abort
   the import rather than risk placing the asset in the wrong tenant.
3. **Enforcement** — in every other case, the asset's entity is forced to the
   current active entity, overriding whatever the rules resolved.

The relevant logic lives in
[`plugin_post_process_importentity_rules_inventorymultitenancy()`](setup.php#L36).

## Requirements

- **GLPI >= 10.0.6**
- A **patched / forked GLPI core** that exposes the
  `post_process_import_entity_rules` hook.

### Required GLPI core patch

This plugin depends on a hook that is **not present in stock GLPI**. The hook is
emitted from `MainAsset.php`, right after the entity import rules are processed.
In our fork, the following line is added at line 607 of
`src/Glpi/Inventory/MainAsset/MainAsset.php`:

```php
Plugin::doHookFunction('post_process_import_entity_rules', (object) [
    "mainAssetObj"      => $this,
    "rulesTargetEntity" => $dataEntity,
]);
```

Without this patch the hook never fires and the plugin has no effect. Make sure
your GLPI deployment uses a build that includes this modification.

## Installation

1. Clone (or download and extract) this repository into the `plugins`
   directory of your GLPI installation, in a folder named `inventorymultitenancy`:

   ```sh
   cd /var/www/glpi/plugins
   git clone <repository-url> inventorymultitenancy
   ```

2. In GLPI, go to **Setup > Plugins**.
3. Locate **Inventory Multitenancy** and click **Install**, then **Enable**.

The plugin requires no additional configuration or database tables.

## Usage

Once installed and enabled — and running against a GLPI core that includes the
required patch — the plugin works transparently. Every asset imported through the
native inventory is automatically constrained to the active entity of the session
that performs the import. No user interaction is needed.

## License

This plugin is distributed under the terms of both the MIT license and the
Apache License (Version 2.0). You may use it under either license, at your option.

- [Apache License, Version 2.0](./LICENSE-APACHE)
- [MIT License](./LICENSE-MIT)

## Contributing

- Open an issue for each bug or feature so it can be discussed first.
- Work on a dedicated branch on your own fork.
- Open a pull request that will be reviewed by a maintainer.
