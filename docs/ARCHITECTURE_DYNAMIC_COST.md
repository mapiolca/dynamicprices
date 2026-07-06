# Architecture - Prix de revient DynamicPrices

## Objectif

DynamicPrices doit gérer un prix de revient propre au module, historisé par produit et par entité, sans écraser le champ natif Dolibarr `product.cost_price`.

Le besoin métier principal est Multicompany : un même produit partagé peut avoir un coût commercial différent selon l'entité qui l'exploite.

## Décision d'architecture

Le prix de revient DynamicPrices est une donnée métier du module :

- coût courant : `llx_dynamicprices_product_cost` ;
- historique : `llx_dynamicprices_product_cost_log` ;
- snapshot des lignes commerciales : `llx_dynamicprices_line_cost_snapshot`.

Le champ natif `llx_product.cost_price` reste disponible comme référence Dolibarr standard. Il ne doit être modifié que par une option legacy explicite, désactivée par défaut.

## Tables prévues

### `llx_dynamicprices_product_cost`

Une ligne courante par couple `(entity, fk_product)`.
Créée par `sql/llx_dynamicprices_product_cost.sql`, avec index dans `sql/llx_dynamicprices_product_cost.key.sql`.

Rôle :

- stocker le dernier coût calculé ;
- stocker la source principale ;
- stocker le coefficient et la règle appliqués ;
- stocker le statut et le message de calcul.

### `llx_dynamicprices_product_cost_log`

Historique des recalculs.
Créée par `sql/llx_dynamicprices_product_cost_log.sql`, avec index dans `sql/llx_dynamicprices_product_cost_log.key.sql`.

Rôle :

- tracer ancienne et nouvelle valeur ;
- conserver les snapshots utiles : coût Dolibarr, PMP, source ;
- identifier le contexte : manuel, masse, trigger, cron, API, migration.

### `llx_dynamicprices_line_cost_snapshot`

Trace du coût DynamicPrices appliqué aux lignes commerciales.
Créée par `sql/llx_dynamicprices_line_cost_snapshot.sql`, avec index dans `sql/llx_dynamicprices_line_cost_snapshot.key.sql`.

Rôle :

- auditer les coûts injectés dans les lignes de devis, commandes et factures ;
- conserver le coût natif de ligne avant/après ;
- retrouver la règle et la source utilisées.

## Service central

La classe `DynamicPricesCostService` centralise :

- lecture du coût courant ;
- calcul ;
- sauvegarde ;
- historique ;
- fallback ;
- application aux lignes commerciales ;
- création des snapshots.

Aucune logique de coût DynamicPrices ne doit être dispersée dans les hooks, triggers ou pages.

Implémentation initiale : `class/dynamicpricescostservice.class.php`.

## Flux de calcul

1. Le service charge le produit.
2. Il vérifie l'entité cible.
3. Il calcule la moyenne des prix d'achat unitaires fournisseurs accessibles dans l'entité courante.
4. Il applique le coefficient de prix de revient de la catégorie commerciale du produit.
5. Il normalise le montant avec les helpers Dolibarr.
6. Il sauvegarde dans `llx_dynamicprices_product_cost`.
7. Il écrit un log selon `DYNAMICPRICES_COST_LOG_MODE`.
8. Il laisse `llx_product.cost_price` inchangé sauf option legacy.

La source du calcul est donc toujours `supplier_average`. Les fallbacks configurables ne servent qu'à l'exploitation d'un coût absent, pas au recalcul du prix de revient DynamicPrices.

## Flux d'application aux lignes commerciales

L'application aux ventes est optionnelle.

Si `DYNAMICPRICES_COST_USE_FOR_SALES` est actif :

1. Le hook ou trigger détecte une création de ligne ; les modifications de lignes existantes conservent le prix de revient déjà défini.
2. Il applique la stratégie configurée : `on_create_only`, `manual_button`, `preserve_origin`.
3. Si un utilisateur autorisé par le droit natif `margins/creer` a choisi manuellement un prix de revient, le service conserve ce choix.
4. Sinon, à la création uniquement, le service résout la première source disponible selon `DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY` : coût DynamicPrices, valeur par défaut Dolibarr, PMP, puis coût Dolibarr par défaut.
5. Il applique le fallback uniquement si aucune source prioritaire n'est disponible.
6. Il renseigne le coût de ligne (`pa_ht`) lorsque le contexte Dolibarr le permet.
7. Il crée un snapshot dans `llx_dynamicprices_line_cost_snapshot`.

Le calcul ne doit jamais être fait pendant la génération PDF.

## Migration depuis l'ancien comportement

La migration est non destructive :

- simulation préalable obligatoire ;
- aucune écriture dans `llx_product.cost_price` ;
- initialisation depuis coût Dolibarr, calcul moteur ou mode mixte ;
- création d'un historique ;
- traitement par entité ;
- rejouable sans doublon.

Assistant livré : `admin/migrate_dynamic_cost.php`.

## API et export

L'API est portée par `class/api_dynamicprices_cost.class.php`.

Elle couvre :

- lecture du coût courant ;
- lecture de l'historique ;
- recalcul unitaire ;
- recalcul de masse ;
- override manuel ;
- suppression d'override manuel.

L'export natif est déclaré dans `core/modules/modDynamicsPrices.class.php` avec le code `dynamicsprices_dynamic_cost`. Il joint `dynamicprices_product_cost` et `product`, puis filtre l'entité courante.

## Compatibilité

L'onglet `admin/compatibility.php` affiche :

- la version Dolibarr détectée ;
- la version PHP détectée ;
- les versions minimales supportées ;
- la disponibilité des fonctionnalités de coût DynamicPrices.

La V1 de cette fonctionnalité repose sur le socle Dolibarr v20 et PHP 8.0, sans dépendance à une API core plus récente.

## Limites V1

La V1 ne couvre pas :

- coût par entrepôt ;
- coût par lot ou numéro de série ;
- simulation multi-scénarios ;
- remplacement global de toutes les lectures core de `Product::$cost_price`.
