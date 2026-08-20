# Ce qu'on peut reprendre du client Laravel Nightwatch

Notes prises en lisant le code de [`laravel/nightwatch`](https://github.com/laravel/nightwatch)
(branche `1.x`), sous licence **MIT** — donc lisible, modifiable et réutilisable librement,
à condition de conserver la notice de copyright si on copie du code.

## La réserve importante

Nightwatch **n'utilise pas OTLP**. Son client sérialise ses propres enregistrements dans un
format maison :

```php
[
    'v' => 1,
    't' => 'query',                 // le type d'enregistrement
    'timestamp' => ...,
    'trace_id' => ...,
    'execution_id' => ...,
    'execution_stage' => ...,
    'sql' => ..., 'file' => ..., 'line' => ..., 'duration' => ...,
]
```

Conséquence : **la couche transport/sérialisation n'est pas réutilisable telle quelle** pour
nous, puisqu'on a fait le choix d'OTLP (ADR-0001). En revanche, tout ce qui est *en amont* —
quels événements Laravel écouter, quelles données extraire de chacun — est directement
transposable. C'est là qu'est la vraie valeur.

## L'architecture, en trois couches

Le code sépare proprement trois responsabilités, et c'est un découpage qu'on peut copier :

1. **Hooks** (`src/Hooks/`) — de simples listeners branchés sur les événements Laravel.
   Ils ne font que capter et déléguer.
2. **Sensors** (`src/Sensors/`) — la logique métier : transformer un événement Laravel en
   enregistrement structuré. Un sensor par type.
3. **Records** (`src/Records/`) — des DTO immuables décrivant la forme de chaque type de
   donnée (`Query`, `Request`, `Exception`, ...).

Chez nous, les Sensors produiraient des **spans OTEL** avec l'attribut `isoxen.type` au lieu
d'enregistrements maison. Le reste du découpage tient.

## La table de correspondance événements → catégories

C'est le morceau le plus directement exploitable. Voici où Nightwatch s'accroche, mis en
regard des catégories de notre sidebar :

| Notre catégorie | Événement(s) / hook Laravel écouté(s) |
|---|---|
| Queries | `Illuminate\Database\Events\QueryExecuted` |
| Exceptions | résolution de `ExceptionHandler` (`callAfterResolving`) |
| Jobs (mise en file) | `JobQueueing`, `JobQueued` |
| Jobs (exécution) | listener sur le cycle de vie du worker |
| Notifications | `NotificationSending`, `NotificationSent` |
| Mail | `MessageSending`, `MessageSent` |
| Outgoing Requests | résolution de la factory `Http` + middleware Guzzle |
| Cache | `RetrievingKey`, `CacheHit`, `CacheMissed`, `WritingKey`, `KeyWritten`, `KeyForgotten`, ... |
| Requests | middleware global + `RouteMatched`, `PreparingResponse`, `RequestHandled`, `Terminating` |
| Commands | `ArtisanStarting`, `CommandStarting`, `CommandFinished` |
| Scheduled Tasks | `ScheduledTaskStarting`, `ScheduledTaskFinished` |
| Users | `Logout` + résolution de l'utilisateur authentifié |

Ça répond directement à la question laissée ouverte sur les catégories qu'on affichait
grisées : **elles sont toutes atteignables**, chacune a son point d'accroche documenté dans
Laravel. Il n'y a pas de magie.

## Deux détails de conception à retenir

**L'envoi ne se fait pas en HTTP synchrone.** Nightwatch écrit dans un buffer en mémoire
(`RecordsBuffer`) et transmet par **socket TCP** vers un agent local (`Ingest`,
`SocketStreamFactory`), qui relaie ensuite vers le service. Objectif évident : ne jamais
ralentir la requête de l'application surveillée. Notre choix d'exporter en OTLP/HTTP via le
SDK OTEL est plus simple, mais le SDK a lui aussi un `BatchSpanProcessor` — il faudra
s'assurer qu'on l'utilise plutôt qu'un export synchrone par span.

**L'agrégation repose sur un hash de regroupement.** Chaque enregistrement porte un champ
`_group` : un hash de la requête SQL *normalisée* (les valeurs `in (...)` et les `values ...`
d'insert sont remplacées par des placeholders). C'est ce qui permet de regrouper
« la même requête » exécutée avec des paramètres différents. Si on veut un jour agréger
proprement côté isoxen, c'est l'astuce à reprendre.

## Ce que je propose d'en faire

Reprendre la structure Hooks/Sensors/Records et la table de correspondance ci-dessus, mais
produire des spans OTEL standards enrichis de `isoxen.type` — plutôt que de réimplémenter
leur protocole. On garde ainsi la compatibilité OTLP décidée dans l'ADR-0001 tout en obtenant
la même richesse de catégorisation.

À vérifier avant de s'engager : la bibliothèque officielle
`open-telemetry/opentelemetry-auto-laravel` couvre peut-être déjà une partie de ces hooks.
Je n'ai pas pu confirmer précisément son périmètre — sa page GitHub ne le documentait pas.
Si elle couvre déjà requests/queries/jobs, on aurait surtout à ajouter la couche
`isoxen.type` par-dessus plutôt que tout réécrire.
