<div align="center">
    <img src="https://lengaengine.com/images/lenga-logo-xl.png" width="140" alt="Lenga Engine Logo" />
</div>

# Lenga Engine Sample Games

This repository contains editable Lenga projects that show how engine features fit together in real games. Open a project in the Lenga Editor, inspect its scenes and components, and use the PHP scripts as practical starting points for your own work.

These are source projects rather than prebuilt game downloads. They evolve independently from the engine repository and may target features on Lenga's current development branch.

## Projects

| Project | Mode | What it demonstrates |
| --- | --- | --- |
| [Blasters](./Blasters) | 2D | A compact space-shooter project with input, animation, projectiles, and gameplay behaviours. |
| [HelloWorld](./HelloWorld) | 2D and 3D | A feature sandbox for camera behaviours, 2D joints, 3D models, and simple scripted objects. |
| [Platformer](./Platformer) | 2D | A larger platformer project with tile-based levels, animation controllers, combat, menus, audio mixing, and gameplay state. |
| [Pong](./Pong) | 2D | Physics-driven paddle gameplay, configurable input, runtime spawning, UI, and scene transitions. |
| [RollerWorld](./RollerWorld) | 3D | A roll-a-ball game using 3D physics, follow cameras, lighting, materials, shaders, audio, UI, and collectibles. |
| [Super Blastoid](./Super%20Blastoid) | 3D | A minimal 3D project for experimenting with scene setup and native components. |

## Requirements

- A Lenga Editor and SDK checkout compatible with the projects in this repository
- PHP 8.5 or newer
- Composer 2

Each project currently resolves the PHP scripting API from `../../php-engine`. For a source checkout, keep this repository at `<lenga>/samples` and the PHP API repository at `<lenga>/php-engine`:

```text
lenga/
|-- php-engine/
`-- samples/
    |-- Blasters/
    |-- Platformer/
    `-- ...
```

## Open A Sample

1. Choose the project you want to explore.
2. Run `composer install` inside that project's directory to install the PHP scripting API and generate its autoloader.
3. Start the Lenga Editor and open the project directory from the Project Hub.
4. Open the project's configured entry scene and press Play.

For example:

```bash
cd samples/RollerWorld
composer install
```

Composer dependencies, exported builds, runtime saves, IDE settings, and local editor layout files are intentionally not committed. Lenga's `ProjectSettings/.asset-imports.json` files are committed because they are portable asset dependency metadata used by editor and export workflows.

## Project Structure

Every sample follows the same basic Lenga project layout:

```text
ProjectName/
|-- Assets/             # Scenes, scripts, prefabs, media, and authored assets
|-- ProjectSettings/    # Lenga project, import, physics, and editor settings
|-- bootstrap.php       # PHP runtime bootstrap
|-- composer.json       # PHP dependencies and PSR-4 namespace mapping
`-- composer.lock       # Reproducible dependency versions
```

The most useful learning path is to start with a scene in `Assets/Scenes`, select its GameObjects in the Hierarchy, and then trace attached behaviours into `Assets/Scripts`.

## Contributing

Bug fixes and focused examples are welcome. Before submitting a change, read [CONTRIBUTING.md](./CONTRIBUTING.md) and verify that the affected project opens, its PHP files lint successfully, and its entry scene runs in the editor.

## Licensing

Lenga-authored source code and project configuration in this repository are available under the [MIT License](./LICENSE). Media, fonts, models, and other third-party assets may have separate terms; see [THIRD_PARTY_NOTICES.md](./THIRD_PARTY_NOTICES.md) and any license files stored beside those assets before reusing them in another project.
