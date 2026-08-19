# ADR-0001: Ingestion OTEL (traces, métriques, logs) — MVP

- Statut : Accepté
- Date : 2026-08-19
- Contexte projet : isoxen.com (Laravel 13, PHP 8.4, Octane/FrankenPHP, Inertia+React, Postgres 18, Redis)

## Contexte

isoxen.com a pour objectif de reproduire le principe de Laravel Nightwatch : devenir la
plateforme qui **reçoit, stocke et (plus tard) affiche** les traces, métriques et logs
d'**autres applications Laravel**, envoyées via le protocole standard OpenTelemetry (OTEL).

Ce n'est pas une instrumentation d'isoxen.com par lui-même : isoxen.com est le **backend
d'observabilité**, les applications clientes sont des projets Laravel/PHP externes qui
utilisent le SDK OTEL PHP standard.

Le cas d'usage principal de ce premier lot est le **debug/observabilité technique** pour
l'équipe (usage interne / POC), pas encore une fonctionnalité produit multi-clients avec
partage d'équipe.

## Décision

### Périmètre du MVP

Ce lot couvre **uniquement l'ingestion et le stockage** :

1. Un endpoint HTTP compatible OTLP qui accepte traces, métriques et logs.
2. La persistance de ces données dans Postgres (voir amendement du 2026-08-19 : TimescaleDB
   envisagé initialement, retiré du périmètre).
3. L'association de chaque payload reçu à un projet (tenant) via une clé secrète.

Explicitement **hors périmètre** de ce lot (tickets séparés) :

- UI de visualisation des traces (pas de page Inertia/React dans ce lot).
- Politique de rétention / purge des données.
- Support du format **protobuf** en entrée (voir section Format ci-dessous) — traité en tâche 2.
- Partage d'un projet entre plusieurs utilisateurs/équipes (un projet = un seul propriétaire pour le MVP).

### Signaux couverts

Les trois signaux OTEL sont couverts dès ce lot : **traces (spans)**, **métriques**, **logs**.
Uniquement côté **backend PHP** — pas d'instrumentation frontend (React/Inertia) dans ce lot.

### Routes d'ingestion

On suit les chemins **standards OTLP/HTTP** :

- `POST /v1/traces`
- `POST /v1/metrics`
- `POST /v1/logs`

Raison : tout SDK OTEL configuré avec `OTEL_EXPORTER_OTLP_ENDPOINT` pointant vers la racine
d'isoxen.com fonctionne sans configuration exotique côté client.

### Format de payload

- **MVP : JSON uniquement** (OTLP/HTTP+JSON). Le SDK OTEL PHP standard sait exporter dans ce
  format ; pas besoin de bibliothèque de décodage protobuf côté serveur pour démarrer.
- **Tâche 2 (hors périmètre de ce lot)** : support protobuf. Point ouvert à investiguer avant
  implémentation — vérifier si les classes générées `open-telemetry/gen-otlp-protobuf`
  (prévues à l'origine pour l'export) peuvent être réutilisées pour le **décodage** en entrée,
  ou s'il faut une autre approche (ex: `google/protobuf` + définitions `.proto` OTLP officielles).
  ⚠️ Ce point n'est pas vérifié à ce stade — à confirmer avant de démarrer la tâche 2.

### Identification du tenant (application cliente)

- Nouveau modèle **`Project`** : un projet = une application cliente surveillée.
- Chaque `Project` possède une **clé secrète** (API key) générée à la création.
- Le payload OTLP entrant est authentifié via cette clé (header `Authorization`), qui
  détermine le `project_id` auquel rattacher les données.
- Complément : les attributs de ressource OTEL standards (`service.name`,
  `service.instance.id`, environnement, version...) sont conservés dans les données stockées
  pour affiner le détail à l'intérieur d'un même projet.
- **Un projet = un seul propriétaire** (`User`) pour ce lot. Pas de notion d'équipe/organisation
  partagée à ce stade.

### Stockage

- **Postgres, tables classiques** (voir amendement du 2026-08-19 en fin de document —
  TimescaleDB a été décidé initialement puis retiré avant la mise en œuvre).
- Pas de politique de rétention dans ce lot (traité dans un ticket dédié ultérieur) — on
  accepte donc une croissance non bornée du volume pendant la durée du MVP/POC.

### Stratégie d'ingestion côté Octane

- **Écriture asynchrone via queue Redis** (déjà présente dans l'infra) plutôt qu'écriture
  synchrone en base dans le cycle de requête HTTP.
- Le endpoint OTLP se contente de valider l'authentification et de déposer le payload brut
  sur une queue Redis ; un job dédié consomme la queue et effectue l'insertion en base.
- Raison : évite de bloquer les workers Octane (process long-lived) sur des écritures DB
  potentiellement nombreuses/lourdes, et absorbe les pics de charge.

### Contraintes Octane/FrankenPHP

Aucune contrainte précise identifiée à ce stade par l'équipe. Point de vigilance générique à
garder en tête pendant l'implémentation : comme Octane garde le process PHP en vie entre les
requêtes, tout état lié à une trace/un span ne doit **pas** être stocké dans une propriété
statique ou un singleton — utiliser des bindings scoped (`$this->app->scoped()`) si un état
partagé au niveau requête est nécessaire pendant le traitement d'un payload OTLP entrant.

## Conséquences

- Le modèle de données (spans, points de métriques, entrées de logs, clé étrangère vers
  `projects`) est implémenté comme des tables Postgres classiques (voir amendement du
  2026-08-19).
- Le support protobuf étant repoussé, il faudra documenter clairement dans le README/AGENTS.md
  du module que seul JSON est supporté au lancement, pour éviter les intégrations clientes
  cassées si un SDK OTEL est configuré par défaut en protobuf (c'est le défaut de certains
  SDKs — à vérifier au cas par cas pour le SDK PHP).

## Alternatives envisagées et écartées

- **SaaS d'observabilité externe (Grafana Cloud, Honeycomb, etc.)** : écarté, l'objectif
  explicite est qu'isoxen.com **soit** le backend d'observabilité (façon Nightwatch), pas
  qu'il en consomme un.
- **Store spécialisé séries temporelles (ClickHouse, etc.)** : écarté pour ce lot au profit de
  Postgres, cohérent avec l'infra déjà en place (Postgres 18) et suffisant pour un POC/MVP.
- **OTLP/gRPC** : écarté au profit d'OTLP/HTTP, plus simple à servir depuis une application
  Laravel/Octane classique sans serveur gRPC dédié.
- **Écriture synchrone en base** : écarté au profit d'une queue Redis, pour ne pas risquer de
  bloquer les workers Octane sous charge.

## Points ouverts / à vérifier avant implémentation

1. Confirmer la faisabilité du décodage OTLP protobuf via `open-telemetry/gen-otlp-protobuf`
   (ou alternative) — **avant** de démarrer la tâche 2 (support protobuf).
2. Définir le schéma de données détaillé (tables, index) pour spans, métriques et logs — non
   couvert par cet ADR dans le détail (implémenté depuis dans
   `database/migrations/2026_08_19_100000_create_otel_ingestion_tables.php`).

## Amendement — 2026-08-19 : retrait de TimescaleDB

TimescaleDB a été retiré du périmètre. Les tables `otel_spans`, `otel_metrics` et `otel_logs`
restent des tables Postgres classiques (avec un `id` auto-incrémenté et un index
`(project_id, time)`), sans extension ni hypertable. `compose.yaml` reste sur l'image
`postgres:18-alpine` d'origine — plus besoin de vérifier un tag d'image TimescaleDB compatible
pg18 ni de réinitialiser `storage-db/`.

Raison : simplifier l'infra pour ce lot ; TimescaleDB apportait surtout une optimisation de
performance/partitioning à volume élevé, pas une nécessité fonctionnelle pour un POC. Le choix
du schéma (colonne `time`, index dédié) a été conservé tel quel pour ne pas fermer la porte à
une réintroduction ultérieure de TimescaleDB si le volume le justifie — il suffirait alors
d'ajouter l'extension et d'appeler `create_hypertable(...)` dans une nouvelle migration.

Conséquence pratique : plus besoin de vérifier l'existence d'un tag d'image
`timescale/timescaledb-ha:pg18` compatible avec Postgres 18 avant de lancer
`docker compose up` — l'image Postgres standard suffit.
