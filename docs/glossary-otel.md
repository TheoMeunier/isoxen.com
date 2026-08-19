# Glossaire — Intégration OTEL sur isoxen.com

Contexte : isoxen.com devient un backend d'observabilité (façon Laravel Nightwatch) qui
reçoit des données OTEL provenant d'autres applications Laravel.

**OTEL / OpenTelemetry**
Standard ouvert et vendor-neutral pour instrumenter des applications et transmettre des
données d'observabilité (traces, métriques, logs) dans un format commun.

**OTLP (OpenTelemetry Protocol)**
Le protocole de transport défini par OTEL pour envoyer les données depuis une application
instrumentée (le "client") vers un backend (le "collector" ou, ici, isoxen.com directement).
Existe en variante HTTP (JSON ou protobuf) et gRPC.

**Trace**
Représentation du parcours complet d'une requête (ex: une requête HTTP) à travers un
système, composée d'un ou plusieurs spans reliés entre eux.

**Span**
Unité de travail au sein d'une trace (ex: une requête SQL, un appel HTTP sortant, l'exécution
d'un job). Possède un `trace_id`, un `span_id`, éventuellement un `parent_span_id`, un nom,
une durée, un statut, et des attributs.

**Métrique**
Mesure numérique agrégée dans le temps (compteur, histogramme, jauge) — ex: latence moyenne,
nombre de requêtes par seconde, taux d'erreur.

**Log (au sens OTEL)**
Entrée de journal applicatif transmise via le protocole OTEL, potentiellement corrélée à une
trace/un span via `trace_id`/`span_id`.

**Resource attributes**
Métadonnées standard attachées à toutes les données émises par une application cliente
(ex: `service.name`, `service.instance.id`, version, environnement). Permettent d'identifier
la provenance d'une donnée sans dépendre uniquement de l'authentification.

**Project (isoxen.com)**
Modèle de données représentant une application cliente surveillée. Un projet possède une clé
secrète (API key) utilisée par le SDK OTEL de l'application cliente pour s'authentifier
auprès d'isoxen.com. Pour ce lot : un projet = un seul propriétaire (`User`), pas de partage
d'équipe.

**Endpoint d'ingestion**
Les routes HTTP exposées par isoxen.com suivant les chemins standards OTLP :
`/v1/traces`, `/v1/metrics`, `/v1/logs`.

**Tenant / multi-tenant**
Le fait qu'isoxen.com reçoive et isole les données de plusieurs applications clientes
distinctes. Ici, l'isolation se fait au niveau du `Project`.

**TimescaleDB**
Extension PostgreSQL transformant certaines tables en "hypertables" optimisées pour les
séries temporelles (partitioning automatique par temps). Envisagée initialement pour stocker
les spans/métriques/logs ingérés, mais **retirée du périmètre le 2026-08-19** (voir
amendement dans `docs/adr/0001-otel-ingestion-mvp.md`) : les tables restent des tables
Postgres classiques pour l'instant, avec un schéma (colonne `time`, index dédié) qui permet
de réintroduire TimescaleDB plus tard sans tout refaire si le volume le justifie.

**Octane / FrankenPHP**
Serveur d'application PHP utilisé par isoxen.com qui garde le process applicatif en vie entre
les requêtes (contrairement au modèle PHP-FPM classique). Implique de ne pas stocker d'état
lié à une requête dans des singletons/propriétés statiques.

**Ingestion asynchrone (via queue Redis)**
Stratégie retenue pour ce lot : l'endpoint OTLP dépose le payload reçu sur une queue Redis, et
un job dédié effectue l'écriture en base — plutôt que d'écrire en base de façon synchrone
dans le cycle de la requête HTTP entrante.

**Laravel Nightwatch**
Produit d'observabilité officiel de l'écosystème Laravel, servant de référence/inspiration
pour ce que doit devenir isoxen.com (recevoir traces/métriques/logs de plusieurs applications
Laravel).
