# ADR-0002 : Séparation logique métier (`src/`) et framework Laravel (`app/`)

- Statut : Proposé
- Date : 2026-08-22
- Contexte projet : isoxen.com (Laravel 13, PHP 8.4, Octane/FrankenPHP, Inertia+React, Postgres 18, Redis)

## Contexte

`app/` est déjà organisé par capacité métier plutôt que par couche technique (`Auth/`,
`Core/`, `Watch/Ingestion/`, `Watch/Projects/`, chacun avec ses propres
`Controllers/Models/Requests/...`) — c'est une bonne base, mais tout reste mélangé avec le
framework : un `Controller` Inertia, un `Model` Eloquent et un parseur OTLP pur vivent côte à
côte dans le même dossier, sous le même namespace `App\`.

La demande : sortir la **logique métier réutilisable et testable indépendamment de
Laravel** dans un dossier `/src` séparé. Règle explicite posée par l'équipe : **rien dans
`src/` ne doit dépendre de Laravel** (aucune classe `Illuminate\*`, aucune classe `App\*`).
`app/` reste le point d'attache du framework et peut dépendre de `src/`, jamais l'inverse.

J'ai relu les 81 fichiers de `app/` pour distinguer ce qui est réellement de la logique
métier portable de ce qui est de la mécanique Laravel. Constat : le domaine métier actuel du
projet est concentré et plutôt mince — l'essentiel du code est de l'orchestration HTTP/DB
(normal pour une app CRUD + ingestion). La vraie logique métier extractible tient dans le
décodage/mapping OTLP (`Data/Otlp/*`, `Support/*`, `Parsing/*`) et, dans une moindre mesure,
la génération d'identité d'un `Project` (slug/token).

## Décision

### Règle de dépendance

- `src/`, namespace **`Domain\`** : PHP natif uniquement. Aucun `Illuminate\*`, aucun
  `Symfony\*`, aucun `App\*`. Pas d'accès DB, pas de facades, pas de conteneur.
- `app/`, namespace `App\` **inchangé** (il ne bouge pas de dossier) : Controllers, Requests,
  Middleware, Eloquent Models, Jobs, Policies, Resources, Providers, Console — tout ce qui
  est structurellement lié à Laravel. `app/` peut appeler `src/`.
- Ajout dans `composer.json` :

  ```json
  "autoload": {
      "psr-4": {
          "App\\": "app/",
          "Domain\\": "src/",
          "Database\\Factories\\": "database/factories/",
          "Database\\Seeders\\": "database/seeders/"
      }
  }
  ```

  Comme `app/` ne bouge pas, `bootstrap/app.php` n'a **pas** besoin d'être modifié (pas de
  `useAppPath()` à gérer — vérifié dans le code source de
  `Illuminate\Foundation\Application`).

### Ce qui migre vers `src/`

| Aujourd'hui (`app/Watch/Ingestion/...`) | Demain (`src/Watch/Ingestion/...`) | Pourquoi |
|---|---|---|
| `Data/Otlp/*` (10 classes : `Span`, `Metric`, `LogRecord`, `DataPoint`, `ResourceSpans`, ...) | `Otlp/*` | Value objects purs : décodent un payload OTLP/JSON en objets typés, aucune I/O |
| `Support/OtlpAttributes.php`, `OtlpTimestamp.php`, `OtlpValue.php` | `Values/*` | Décodage de valeurs primitives du protocole OTLP, zéro dépendance déjà aujourd'hui |
| `Support/ObservabilityCategories.php` | `ObservabilityCategories.php` | Catalogue métier statique (catégories d'observabilité, mapping vers un modèle) — déjà zéro dépendance |
| `Data/LogRow.php`, `MetricRow.php`, `SpanRow.php`, `DatabaseTimestamp.php` | `Rows/*` | DTOs purs ; `toDatabaseRow()` reste un simple mapping vers un tableau associatif, aucune dépendance Laravel |
| `Parsing/OtlpLogsParser.php`, `OtlpMetricsParser.php`, `OtlpSpansParser.php` | `Parsing/*` | Logique de transformation payload → lignes, moyennant les deux retraits ci-dessous |
| *(nouveau)* — extrait de `Project::booted()` | `Watch/Projects/ProjectIdentity.php` | Génération du slug et du token d'un projet : aujourd'hui de la logique métier cachée dans un hook Eloquent, via `Str::slug`/`Str::random` |

### Ce qui reste dans `app/` et pourquoi

- **Models Eloquent** (`User`, `Project`, `OtelLog`, `OtelMetric`, `OtelSpan`,
  `OtelRecord`) : structurellement couplés au framework (`extends Model`), ce sont les
  adaptateurs de persistance, pas le domaine lui-même.
- **`Queries/*`** (12 classes) : entièrement construites sur `Eloquent`/`Query Builder`
  (agrégations SQL, `LengthAwarePaginator`) — ce sont des adaptateurs de lecture, aucune
  logique n'est réutilisable hors Laravel.
- **`Actions/*`** (`CreateProjectAction`, `UpdateProjectAction`, `DeleteProjectAction`) :
  appels directs à Eloquent sans règle propre au-delà de l'appel — rien à en extraire, pas de
  gain à les déplacer.
- **Controllers, Requests, Middleware, Resources, Policies, Providers, Console Commands,
  Jobs** : glue framework par nature (HTTP, queue, validation Laravel, `Gate`).

### Deux points à trancher avant de commencer

1. **`Illuminate\Support\Collection` dans `Data/Otlp/*` et `Parsing/*`.** C'est la seule
   dépendance Laravel dans ce qui migre. `illuminate/collections` est un package autonome
   utilisable hors Laravel, mais reste un import `Illuminate\*` — contraire à la règle telle
   que formulée. **Recommandation : remplacer par des tableaux natifs** (`array_map`,
   `array_filter`, `array_values` à la place de `->map()->filter()->values()`) pour respecter
   la règle à la lettre. Un peu plus verbeux, mais zéro ambiguïté. Alternative : accepter
   `illuminate/collections` comme exception documentée si la verbosité gêne — à valider avec
   toi.
2. **`Illuminate\Support\Facades\Date::now()` dans les trois `Parsing/*Parser.php`.** Facile à
   retirer : passer `DateTimeImmutable $now` en paramètre de `toRows()`, fourni par l'appelant
   (le `Job` correspondant dans `app/`, qui reste libre d'utiliser `Date::now()`).

Nom du namespace racine : je propose `Domain\`. J'évite `Isoxen\`, déjà pris par le package
séparé `packages/isoxen-client` (`Isoxen\Client\...`) — risque de confusion même si les
chemins PSR-4 ne collisionnent pas techniquement.

### Faire respecter la règle dans la durée

Une règle « rien ne doit dépendre de Laravel » qui n'est pas vérifiée automatiquement finit
par être violée au premier import ajouté sans y penser. Trois filets :

- **Deptrac** (`composer require --dev qossmic/deptrac`) : définir deux couches (`Domain` =
  `src/`, `Framework` = `app/` + tout `Illuminate\*`/`Symfony\*`) et interdire
  `Domain → Framework`. À lancer dans `make phpstan`/`composer ci:check`, aux côtés de Pint et
  Larastan. C'est le seul des trois filets qui casse la CI si la règle est violée.
- **`phpstan.neon`** et **`rector.php`** : ajouter `src/` à `paths`.
- **`phpunit.xml`** : ajouter `<directory>src</directory>` sous `<source><include>` pour que la
  couverture de test remonte aussi le domaine.

### Tests

Bonne surprise en relisant `tests/Pest.php` : seul `tests/Feature` est lié à
`Tests\TestCase` (donc à Laravel, `RefreshDatabase`, etc.) — `tests/Unit` tourne déjà en Pest
pur, sans bootstrap Laravel. Aucune modification nécessaire : les futurs tests de `src/`
vont dans `tests/Unit/Domain/...` en miroir de `src/`, et s'exécutent en quelques
millisecondes sans base de données. C'est aussi un bon test de la règle elle-même : si un test
unitaire d'une classe `Domain\...` a besoin d'un mock Eloquent ou d'une facade, c'est le signe
que la classe n'est pas si pure que ça.

### Étapes de migration proposées

À faire module par module, en testant entre chaque étape plutôt qu'en un seul gros commit —
une quinzaine de fichiers `app/` référencent les classes qui migrent (imports à mettre à
jour : `HandleInertiaRequests`, les 3 controllers `Ingest*Controller`, `ShowProjectController`,
`CreateProjectController`, `EditProjectController`, les 3 `Jobs`, `IngestionStatusCommand`...).

1. `composer.json` : ajouter `"Domain\\": "src/"`, puis `composer dump-autoload`.
2. Déplacer `Data/Otlp/*` → `src/Watch/Ingestion/Otlp/*` (namespace `Domain\Watch\Ingestion\Otlp`),
   mettre à jour les imports dans `Parsing/*`.
3. Déplacer `Support/{OtlpAttributes,OtlpTimestamp,OtlpValue}.php` → `src/Watch/Ingestion/Values/*`.
4. Déplacer `Support/ObservabilityCategories.php` → `src/Watch/Ingestion/ObservabilityCategories.php`,
   mettre à jour les 4 fichiers qui l'importent.
5. Déplacer `Data/{LogRow,MetricRow,SpanRow,DatabaseTimestamp}.php` → `src/Watch/Ingestion/Rows/*`.
6. Retirer `Date::now()` des trois parsers (paramètre `DateTimeImmutable $now`), les déplacer
   vers `src/Watch/Ingestion/Parsing/*`, mettre à jour les 3 `Jobs` pour leur passer l'heure.
7. Si on retient la recommandation du point 1 ci-dessus : remplacer `Collection` par des
   tableaux natifs dans les classes déplacées, adapter les `Jobs` (`$rows->chunk(500)` devient
   `array_chunk($rows, 500)`).
8. Extraire `ProjectIdentity` (`slugFor()`, `generateToken()`) dans
   `src/Watch/Projects/ProjectIdentity.php`, appelé depuis `Project::booted()`.
9. Mettre à jour `phpstan.neon`, `rector.php`, `phpunit.xml` (ajout de `src/`).
10. Ajouter Deptrac + config des couches + intégration CI.
11. `composer dump-autoload && vendor/bin/pint && vendor/bin/phpstan analyse && php artisan test --compact`.
12. Écrire des tests unitaires pour les classes migrées dans `tests/Unit/Domain/...` — au
    minimum `OtlpValue` (ids, severity), `OtlpTimestamp::toDateTime`, et les trois parsers de
    bout en bout.

### Risque à noter

Le guideline Boost du projet (`AGENTS.md`) dit explicitement *« Stick to existing directory
structure; don't create new base folders without approval »* — cette ADR **est** cette
approbation. Ça vaut le coup de le mentionner dans le message de commit/PR pour qu'un futur
agent ne pense pas que `src/` est une anomalie à corriger.

## Conséquences

- `app/` reste 100 % Laravel — plus facile à lire comme « ce qui répond à une requête HTTP,
  un job, une commande », sans avoir à se demander si telle classe a des effets de bord
  cachés du framework.
- `src/` reste volontairement petit au démarrage (une douzaine de classes) mais strictement
  pur : testable en millisecondes, sans DB, portable si un jour une partie du parsing OTLP
  devait tourner ailleurs (CLI, autre service).
- Le prochain morceau de logique métier (règles d'alerting, corrélation multi-signaux, etc.)
  a désormais un point d'atterrissage évident plutôt que de repartir dans `app/` par défaut.

## Alternatives envisagées et écartées

- **Renommer `app/` en `src/` tel quel** (garder `App\`, juste changer le chemin) : plus
  simple techniquement, mais ne répond pas à la demande — ça ne sépare rien, juste un
  changement de nom de dossier.
- **DDD complet par agrégat** (entités de domaine séparées des Models Eloquent + repositories
  pour `Project`, `User`, `OtelSpan`...) : écarté pour ce lot. Le domaine actuel n'a presque
  pas de règles métier au-delà du parsing OTLP et de l'identité de projet ; construire des
  repositories et des entités pour du CRUD quasi nu serait de la sur-ingénierie, et va à
  l'encontre du `CLAUDE.md` du projet (« éviter les nouvelles abstractions », « garder les
  responsabilités petites »). À reconsidérer si le domaine se complexifie réellement.
- **Garder `Illuminate\Support\Collection` sans trancher la question** : écarté — ça
  laisserait une zone grise sur la règle « zéro dépendance Laravel », qui finirait par être
  contournée au premier ajout pressé.

## Points ouverts / à vérifier avant implémentation

1. Nom du namespace racine (`Domain\` proposé) — à valider.
2. `Collection` Illuminate vs tableaux natifs dans `src/` — à trancher (recommandation :
   tableaux natifs).
3. Extraire `ProjectIdentity` maintenant ou le laisser pour un lot séparé (c'est un bonus, pas
   un pré-requis du déplacement du reste) — à valider.
4. Ajout de Deptrac comme nouvelle dépendance dev — à valider explicitement (le Boost rule du
   projet demande une approbation pour tout changement de dépendances ; cette ADR vaut
   validation si tu es d'accord).
