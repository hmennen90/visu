# PHP Game Engine — Claude Code Arbeitsplan

> Dieses Dokument ist der primäre Kontext für Claude Code.
> Lies es vollständig bevor du Code schreibst oder Dateien anderst.

---

## Projektziel

Wir bauen eine **PHP-native Game Engine** mit folgender Prioritaet:

1. **Erstes Spiel:** Code Tycoon (2D Wirtschaftssimulation) — Proof of Concept
2. **Zweites Spiel:** Netrunner: Uprising (3D Cyberpunk RPG) — nach Engine-Reife
3. **Langfristig:** Open-Source-Engine fuer AI-gestuetztes Game Authoring

---

## Architektur-Ueberblick

```
Authoring Layer         -> LLM (du) + Vue SPA Editor + Manuell
Datenformat             -> JSON (Szenen, UI-Layouts, Config, Saves)
Game-Specific Layer     -> JSON-Scene-System, UI-Interpreter,
                           AudioManager, Save/Load, Mod-System
--------------------------------------------------------------
VISU Engine (dieser Fork) -> ECS, Application Bootstrap, Game Loop, Input,
  src/                       Render-Pipeline, Signal-System, Camera,
  Namespace: VISU\           FlyUI (Immediate-Mode GUI), Graphics,
                             Shader Management, Font Rendering
--------------------------------------------------------------
C-Extensions (Backends) -> php-glfw (NanoVG 2D, OpenGL 4.1 3D)
Hardware                -> GPU
```

**Grundprinzip:** Dieses Projekt ist ein **Fork von VISU** (github.com/phpgl/visu).
Wir arbeiten direkt im VISU-Quellcode und erweitern ihn um unser JSON-Scene-System,
UI-Interpreter und alle spielspezifischen Systeme. VISU ist keine externe Dependency,
sondern unser eigenes Repository.

---

## Projektstruktur (IST-Zustand)

```
visu/                        # Repository-Root (Fork von phpgl/visu)
  composer.json              # Package: phpgl/visu, Namespace: VISU\
  visu.ctn                   # Framework DI-Container Konfiguration
  bootstrap.php              # Application Bootstrap (Container-Setup)
  bootstrap_inline.php       # Alternative Bootstrap
  phpunit.xml                # PHPUnit Konfiguration
  phpstan.neon               # PHPStan Level 8
  phpbench.json              # Benchmark Konfiguration

  src/                       # Gesamter Engine-Quellcode (Namespace: VISU\)
    Animation/               # Transition-Animationen
    Command/                 # CLI-Kommando-System (League\CLImate)
    Component/               # Vorgefertigte ECS-Components (Light, Animation, LowPoly)
    ECS/                     # Entity Component System (EntityRegistry, SystemInterface)
    Exception/               # Basis-Exceptions
    FlyUI/                   # Immediate-Mode GUI (Buttons, Cards, Labels, Select, etc.)
    Geo/                     # Geometrie-Hilfsklassen (AABB, Ray, Frustum, Transform)
    Graphics/                # OpenGL-Rendering (Shader, Framebuffer, Texture, Camera, Font)
    Instrument/              # Profiling (CPU/GPU Timer, Clock)
    Maker/                   # Code-Generatoren (Klassen, Commands)
    OS/                      # Betriebssystem-Abstraktion (Window, Input, FileSystem, Logger)
    Quickstart/              # Schnelleinstieg-Hilfsklassen
    Runtime/                 # GameLoop, DebugConsole
    Signal/                  # Event/Signal-Dispatching System
    Signals/                 # Vordefinierte Signale (Bootstrap, Input, ECS, Runtime)
    System/                  # ECS-Systeme (Camera, Animation, AABB-Tree, Dev-Tools)
    D3D.php                  # 3D Debug-Helper
    Quickstart.php           # Quickstart Entry

  tests/                     # PHPUnit Tests (spiegelt src/-Struktur)
  resources/                 # Framework-Ressourcen (Shader, Fonts, Models)
  examples/                  # Beispiel-Anwendungen
  docs/                      # MkDocs Dokumentation
  bin/visu                   # CLI Entry Point
```

---

## Kern-Entscheidungen (nicht diskutieren, direkt umsetzen)

| Thema | Entscheidung |
|---|---|
| Rendering 2D | php-glfw + NanoVG |
| Rendering 3D | php-glfw + OpenGL 4.1 |
| Editor UI | Vue SPA (Web-basiert, localhost) |
| In-Game UI | JSON -> Render Interpreter (kein HTML) |
| Szenen-Format | JSON (Entity-Hierarchien, Transforms, Components) |
| Game Loop | PHP CLI, Fixed Timestep + Variable Render (src/Runtime/GameLoop.php) |
| Sourcecode-Schutz | Opcache-Bytecode in PHAR (Stufe 2) |
| Distribution | Launcher-Binary + statisches PHP-Binary + game.phar |
| Authoring | Natuerliche Sprache -> Claude Code -> PHP/JSON -> Live Preview |
| Projekt-Typ | Fork von VISU — direktes Arbeiten im VISU-Quellcode |

---

## Was VISU bereits mitbringt (nicht neu bauen!)

| Modul | Pfad | Funktion |
|---|---|---|
| ECS | `src/ECS/` | EntityRegistry mit Freelist-Pooling, SystemInterface |
| Game Loop | `src/Runtime/GameLoop.php` | Fixed Timestep, GameLoopDelegate |
| Input | `src/OS/Input.php` | InputActionMap, InputContextMap, Key/MouseButton |
| Window | `src/OS/Window.php` | GLFW Window Management, Event Callbacks |
| Signal/Events | `src/Signal/` | Dispatcher, SignalQueue, Handler-Registration |
| Graphics | `src/Graphics/` | ShaderProgram (mit #include/#define), Framebuffer, Texture, Camera |
| Render Pipeline | `src/Graphics/Rendering/` | RenderPipeline, RenderPass, GBuffer, SSAO |
| FlyUI | `src/FlyUI/` | Immediate-Mode GUI (Button, Card, Label, Select, Checkbox, etc.) |
| Font Rendering | `src/Graphics/Font/` | Bitmap Font Rendering |
| Geo/Math | `src/Geo/` | AABB, Ray, Frustum, Transform, Plane |
| 3D Debug | `src/D3D.php` | Debug-Visualisierungen (BoundingBox, Ray) |
| Profiling | `src/Instrument/` | CPU/GPU Profiler, Clock |
| Animation | `src/Animation/` | Transition-Animationen |
| Debug Console | `src/Runtime/DebugConsole.php` | In-Game Konsole |
| CLI | `src/Command/` | Command Registry, CLI Loader via League\CLImate |
| DI Container | `visu.ctn` + `bootstrap.php` | ClanCats Container mit .ctn Dateien |

---

## Technischer Stack

```
PHP 8.1+ (CLI-SAPI, JIT empfohlen)
php-glfw — OpenGL 4.1 + NanoVG 2D (C-Extension)
ClanCats Container — Dependency Injection (.ctn Konfigurationsdateien)
League\CLImate — CLI-Ausgabe
Composer — Autoloading (PSR-4: VISU\ -> src/)
```

### Befehle

```bash
# Dependencies installieren
composer install

# Tests ausfuehren
./vendor/bin/phpunit
./vendor/bin/phpunit --filter TestName
./vendor/bin/phpunit tests/path/to/TestFile.php

# Statische Analyse (Level 8)
./vendor/bin/phpstan analyse

# Benchmarks
./vendor/bin/phpbench run
```

### Bootstrap & DI

Die Application benoetigt folgende Pfad-Konstanten vor dem Bootstrap:
- `VISU_PATH_ROOT` — Projekt-Wurzel
- `VISU_PATH_CACHE` — Cache-Verzeichnis
- `VISU_PATH_STORE` — Persistenter Speicher
- `VISU_PATH_RESOURCES` — Anwendungs-Ressourcen
- `VISU_PATH_APPCONFIG` — Pfad zu anwendungsspezifischen .ctn Dateien

`visu.ctn` definiert Framework-Services und importiert `app.ctn` aus der Anwendung.

### PHPStan Hinweise

Level 8 mit spezifischen `ignoreErrors` fuer php-glfw Math-Typen
(Property-Access und Operator-Overloading werden von PHPStan nicht vollstaendig erkannt).
Animation-Verzeichnis ist ausgeschlossen.

---

## Phasenplan — Code Tycoon (2D)

> Alle Phasen beziehen sich auf Code Tycoon als erstes Spiel.
> Der 3D-Engine-Plan (Netrunner) liegt separat in `docs/3D_ENGINE_PLAN.md`.

### Phase 1 — Engine-Kern & Scene-System (Wochen 1-4)
**Ziel:** JSON-basiertes Scene-System auf VISUs ECS, Entities aus JSON rendern.

```
Engine-Infrastruktur:
[x] ComponentRegistry (src/ECS/ComponentRegistry.php)
[x] AssetManager (src/Asset/AssetManager.php)
[x] SceneManager (src/Scene/SceneManager.php)
    - Aktive Szene, Szenen-Stack, Persistente Entities

Scene-System:
[x] SceneLoader (src/Scene/SceneLoader.php)
[x] SceneSaver (src/Scene/SceneSaver.php)
[x] Prefab-System (src/Scene/PrefabManager.php)

2D-Rendering:
[x] SpriteRenderer Component (src/Component/SpriteRenderer.php)
[x] SpriteBatchPass (src/Graphics/Rendering/Pass/SpriteBatchPass.php)
[x] Sorting Layers (src/Graphics/SortingLayer.php)
[x] Tilemap Component + TilemapPass (src/Component/Tilemap.php, src/Graphics/Rendering/Pass/TilemapPass.php)
[x] Auto-Tiling (Bitmask-basiert, src/Component/Tilemap.php: autoTile, autoTileMap, resolveAutoTile, bakeAutoTiles)
[x] SpriteAnimator Component + System (src/Component/SpriteAnimator.php, src/System/SpriteAnimatorSystem.php)
[x] Camera2DSystem (src/System/Camera2DSystem.php)
[x] NameComponent (src/Component/NameComponent.php)

Signale/Events:
[x] EntitySpawnedSignal, EntityDestroyedSignal (src/Signals/ECS/)
[x] SceneLoadedSignal, SceneUnloadedSignal (src/Signals/Scene/)
[x] CollisionSignal, TriggerSignal (src/Signals/ECS/)

[x] MEILENSTEIN: office_level1.json mit 55+ Entities, Prefabs, Entity-Hierarchie,
    Round-Trip (Load->Save->Load) verifiziert, 98 Tests bestanden.
```

### Phase 2 — Interaktion, Collision & Audio (Wochen 5-8)
**Ziel:** Spielwelt reagiert auf Input, UI aus JSON, Sound funktioniert.

```
2D Collision:
[ ] BoxCollider2D, CircleCollider2D Components
[ ] CollisionSystem (Broad Phase: Spatial Grid, Narrow Phase: AABB/Circle Tests)
[ ] Trigger vs. Solid Collider (isTrigger Flag)
[ ] CollisionSignal / TriggerSignal (Entity A, Entity B, Kontaktpunkt)
[ ] Raycast2D (Punkt-in-Entity, Linie-durch-Welt)

In-Game UI:
[ ] UI-JSON-Schema (panel, button, label, progressbar, list, grid, image, tooltip)
[ ] UIInterpreter (UI-JSON -> NanoVG Draw Calls via FlyUI-Patterns)
[ ] UI Data Binding (Expressions: "{economy.money}", "{player.health}")
    - DataBindingResolver: ECS-Component-Properties per Pfad lesen
    - Automatische UI-Updates wenn sich gebundene Werte aendern
[ ] UI Event Handling (button.event -> Signal Dispatch, z.B. "ui.new_project")
[ ] UI Transitions (FadeIn/Out, SlideIn, Scale — ueber Animation-System)
[ ] UI Screens Stack (push/pop fuer Menu -> Submenu -> Zurueck)

Audio:
[ ] AudioManager erweitern (existiert bereits in src/Audio/)
    - Sound-Kanaele: SFX, Music, UI, Ambient (getrennte Lautstaerke)
    - Music: Looping, Crossfade zwischen Tracks
    - SFX: Fire-and-Forget, max gleichzeitige Instanzen pro Clip
    - Preloading & Caching ueber AssetManager
[ ] AudioClip-Formate: WAV (existiert), OGG hinzufuegen

Camera 2D:
[ ] Camera2DSystem (Orthographic, Follow-Target, Smooth Damping)
[ ] Camera Bounds (Welt-Grenzen, kein Scrollen ueber Rand)
[ ] Camera Zoom (Scroll-Wheel, Pinch — Min/Max Limits)
[ ] Camera Shake (fuer Events/Feedback)

[ ] MEILENSTEIN: Klickbare UI aus JSON, Entities kollidieren,
    Hintergrundmusik + SFX, Kamera folgt Spieler.
```

### Phase 3 — Code Tycoon Kern-Mechaniken (Wochen 9-14)
**Ziel:** Wirtschaftssimulation laeuft, Grundspiel ist spielbar.

```
Zeitsystem:
[ ] TimeSystem (Spielzeit-Simulation, Pause/1x/2x/3x Geschwindigkeit)
[ ] GameClock Component (aktuelle Spielzeit, Tag/Monat/Jahr)
[ ] TimeControlUI (Pause, Speed-Buttons, Datum-Anzeige)
[ ] Tick-basierte System-Updates (EconomySystem etc. reagieren auf Spielzeit)

Wirtschaft:
[ ] EconomySystem + EconomyComponent (Geld, Einnahmen, Ausgaben, Bilanz)
[ ] EmployeeSystem + EmployeeComponent
    - Einstellung/Kuendigung, Gehalt, Skill-Level, Moral, Produktivitaet
    - Arbeitsplatz-Zuweisung (Entity-Referenz)
[ ] ProjectSystem + ProjectComponent
    - Projekttypen (Website, App, Game, Enterprise), Anforderungen
    - Fortschritt (Tasks, zugewiesene Mitarbeiter, Deadline)
    - Qualitaet (abhaengig von Skill-Match + Moral)
    - Einnahmen bei Abschluss, Strafen bei Verzoegerung
[ ] ContractSystem (Auftraege annehmen/ablehnen, Deadlines, Reputation)

Forschung & Progression:
[ ] ResearchSystem + TechTreeComponent
    - Technologie-Baum (JSON-definiert): Sprachen, Frameworks, Tools
    - Forschungspunkte durch Mitarbeiter generiert
    - Unlock-Effekte (neue Projekttypen, Effizienz-Boni, UI-Features)
[ ] UpgradeSystem (Buero-Upgrades: Groesse, Moebel, Server, Kueche)
    - Effekte auf Moral, Kapazitaet, Prestige

Buero & Welt:
[ ] OfficeSystem + OfficeComponent
    - Raumplanung (Grid-basiert, Moebel platzieren)
    - Arbeitsplaetze, Meetingraeume, Serverraum, Pausenraum
    - Kapazitaetslimits
[ ] Mitarbeiter-Bewegung (einfaches Pathfinding auf Grid, A* oder BFS)
[ ] Visuelles Feedback (Mitarbeiter sitzen am Platz, laufen zur Kueche, etc.)

[ ] MEILENSTEIN: Firma gruenden, Mitarbeiter einstellen, Projekte abschliessen,
    Geld verdienen, Technologien erforschen. 15+ Minuten Gameplay-Loop.
```

### Phase 4 — Content, Save/Load & Polish (Wochen 15-22)
**Ziel:** Code Tycoon ist spielbar, 30+ Minuten Content, Distribution-ready.

```
Save/Load:
[ ] SaveManager (Game State -> JSON, JSON -> Game State)
    - Alle ECS-Entities + Components serialisieren
    - Save-Slots (3-5 Slots + Autosave)
    - Autosave (alle N Spielminuten, konfigurierbar)
    - Save-Kompatibilitaet (Versionsnummer, Migration bei Schema-Aenderung)
[ ] MainMenu-Szene (Neues Spiel, Laden, Einstellungen, Beenden)

Events & Story:
[ ] RandomEventSystem (zufaellige Ereignisse basierend auf Spielzeit/Zustand)
    - Events: Mitarbeiter kuendigt, Bug-Krise, Investor-Angebot, Hackathon
    - Event-Definitionen als JSON (Typ, Bedingungen, Optionen, Konsequenzen)
[ ] NotificationSystem (Toast-Nachrichten, Event-Popups, Meilenstein-Feiern)
[ ] TutorialSystem (erste 10 Minuten gefuehrt, Schritt-fuer-Schritt Anweisungen)

Content:
[ ] 10+ Projekttypen mit unterschiedlichen Anforderungen
[ ] 20+ Technologien im Tech-Tree
[ ] 5+ Buero-Upgrade-Stufen
[ ] 15+ Random Events
[ ] 3+ Schwierigkeitsgrade (Startkapital, Event-Haeufigkeit, Markt-Dynamik)

UI-Screens (alle als JSON):
[ ] HUD: Geld, Datum, Speed-Controls, Benachrichtigungen
[ ] Mitarbeiter-Panel: Liste, Details, Einstellung
[ ] Projekt-Panel: Aktive Projekte, Verfuegbare Auftraege
[ ] Tech-Tree: Visueller Baum, Forschungs-Fortschritt
[ ] Buero-Editor: Moebel platzieren, Raeume einrichten
[ ] Bilanz: Einnahmen/Ausgaben Graph, Firmen-Statistiken
[ ] Einstellungen: Audio, Grafik, Gameplay

Audio-Content:
[ ] Hintergrundmusik (2-3 Tracks, Crossfade)
[ ] UI-Sounds (Click, Hover, Notification, Error, Success)
[ ] Ambient (Buero-Atmosphaere, Tastatur-Klackern)

Polish:
[ ] Tooltips (Hover ueber UI-Elemente zeigt Details)
[ ] Keyboard Shortcuts (Space=Pause, 1/2/3=Speed, Esc=Menu)
[ ] Accessibility (Schriftgroesse, Farbschema, Tastatur-Navigation)
[ ] Performance (60 FPS bei 200+ Entities, Profiling mit src/Instrument/)

Distribution:
[ ] Build-Script (make build -> game.phar + Launcher)
[ ] Statisches PHP-Binary (php-build fuer macOS/Linux/Windows)
[ ] Asset-Packaging (Sprites, Audio, JSON in assets/)
[ ] Mod-Loader (JSON-Overrides aus mods/ Verzeichnis laden)

[ ] MEILENSTEIN: Vollstaendig spielbares Code Tycoon mit Save/Load,
    30+ Minuten Content, Distribution-ready als Standalone-App.
```

### Phase 5 — Vue SPA Editor (optional, parallel)
**Ziel:** Browser-Editor fuer Level-Design und UI-Tuning.

```
[ ] Editor-Server erweitern (WorldEditorRouter existiert bereits)
    REST-Endpoints: GET/PUT /api/scene/{id}, PATCH /api/entity/{id}
    WebSocket: Live-Preview Updates
[ ] Vue SPA: Scene Hierarchy, Property Inspector, Asset Browser
[ ] Vue SPA: UI Layout Editor (WYSIWYG fuer UI-JSON)
[ ] Vue SPA: Tech-Tree Editor (visueller Knoten-Editor)
[ ] Vue SPA: Event-Editor (Random Events konfigurieren)
[ ] MEILENSTEIN: Entities selektierbar, Transform editierbar, als JSON gespeichert.
```

> **3D-Engine & Netrunner:** Siehe `docs/3D_ENGINE_PLAN.md`

---

## Wie du (Claude Code) arbeiten sollst

### Code schreiben
- Namespace ist immer `VISU\` — neue Klassen gehoeren in `src/`
- Bestehende VISU-Patterns und Konventionen einhalten
- PHP 8.1+ Features nutzen (Enums, Readonly, Named Arguments, Fibers)
- Tests in `tests/` mit gespiegelter Verzeichnisstruktur

### Szenen generieren
Szenen sind JSON. Generiere Entity-Hierarchien direkt als `.json`-Datei:

```json
{
  "entities": [{
    "name": "Player",
    "transform": { "position": [0, 1, 0], "rotation": [0, 0, 0], "scale": [1, 1, 1] },
    "components": [
      { "type": "SpriteRenderer", "sprite": "assets/sprites/player.png" },
      { "type": "CharacterController", "speed": 5.0 }
    ],
    "children": []
  }]
}
```

### Game Logic generieren
Components und Systems sind PHP-Klassen im `VISU\`-Namespace.
- Kommunikation ueber VISUs Signal-System (`VISU\Signal\Dispatcher`)
- ECS nutzen: `EntityRegistry`, `SystemInterface`
- Lifecycle-Methoden implementieren

### UI generieren
In-Game UI ist JSON, kein HTML:

```json
{
  "type": "panel",
  "layout": "column",
  "padding": 10,
  "children": [
    { "type": "label", "text": "Geld: {economy.money}", "fontSize": 16 },
    { "type": "progressbar", "value": "{player.oxygen}", "color": "#0088ff" },
    { "type": "button", "label": "Neues Projekt", "event": "ui.new_project" }
  ]
}
```

---

## Regeln & Anti-Patterns

| Nicht tun | Stattdessen |
|---|---|
| HTML/CSS fuer In-Game UI | JSON -> UIInterpreter |
| Direktzugriff Component->Component | Signal/EventBus nutzen |
| Proprietaeres Binaerformat fuer Szenen | Immer JSON |
| FPM/Web-Server fuer Game Loop | PHP CLI + GameLoop |
| Tight Coupling zwischen Spiel und Engine | ComponentRegistry + Interfaces |
| Monolithische Systems | Kleine, fokussierte Components + Systems |
| VISU-Kernmodule duplizieren | Bestehende Module erweitern/nutzen |

---

## Distribution (make build)

```
codetycoon/
  codetycoon          <- Launcher-Binary (startet runtime/php game.phar)
  runtime/php         <- Statisches PHP 8.3 Binary (~15-25 MB)
  game.phar           <- Engine + Game Logic, Opcache-Bytecode-geschuetzt
  assets/             <- Offen (Sprites, Sounds, UI-JSONs, Szenen)
  saves/              <- User Data
  mods/               <- Offen fuer Modder
```

---

## Authoring-Paradigma

```
Traditionell:  Designer -> Editor UI -> Szene -> Compile -> Test
Neu:           Natuerliche Sprache -> Claude Code -> PHP/JSON -> Live Preview
```

Der Editor ist ein **Visualisierungs- und Feintuning-Tool**.
Du (Claude Code) bist das **primaere Authoring-Interface**.

Jeder generierte Schritt ist:
- Ein Git-Commit
- Reviewbar (kein Black-Box-Editor-State)
- Testbar mit PHPUnit
- Versionierbar ohne proprietaere Merge-Konflikte
