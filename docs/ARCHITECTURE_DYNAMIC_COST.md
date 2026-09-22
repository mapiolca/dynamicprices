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
3. Pour un produit ou service simple, il calcule la moyenne des prix d'achat unitaires fournisseurs actifs et complets accessibles dans l'entité courante. Une ligne sans référence fournisseur, sans quantité valide ou sans prix unitaire est exclue.
4. Il applique le coefficient de prix de revient de la catégorie commerciale du produit ou service simple.
5. Pour un kit, il additionne le prix de revient DynamicPrices valide de chaque composant multiplié par sa quantité de composition. Aucun fallback vers le coût natif, le PMP ou un prix fournisseur du composant n'est appliqué au niveau du kit.
6. Il normalise le montant avec les helpers Dolibarr.
7. Il sauvegarde dans `llx_dynamicprices_product_cost`. Un calcul en échec enregistre un montant nul et ne conserve pas silencieusement l'ancienne valeur.
8. Il écrit un log selon `DYNAMICPRICES_COST_LOG_MODE`.
9. Il laisse `llx_product.cost_price` inchangé sauf option legacy.

La source est `supplier_average` pour un produit ou service simple et `kit_components` pour un kit. Les fallbacks configurables ne servent qu'à l'exploitation d'un coût absent sur une ligne commerciale, pas au recalcul du prix de revient DynamicPrices.

## Flux d'application aux lignes commerciales

L'application aux ventes est optionnelle.

Si `DYNAMICPRICES_COST_USE_FOR_SALES` est actif :

1. Le hook ou trigger détecte une création de ligne ; les modifications de lignes existantes conservent le prix de revient déjà défini.
2. Il applique la stratégie configurée : `on_create_only`, `manual_button`, `preserve_origin`.
3. Si un utilisateur autorisé par le droit natif `margins/creer` a choisi manuellement un prix de revient, le service conserve ce choix.
4. Sinon, à la création uniquement, le service résout la première source disponible selon `DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY`. L'ordre initial reste : coût DynamicPrices, valeur par défaut Dolibarr, PMP, puis coût Dolibarr. La source optionnelle `pricelist` peut être ajoutée au rang choisi par l'administrateur.
5. Il applique le fallback uniquement si aucune source prioritaire n'est disponible.
6. Il renseigne le coût de ligne (`pa_ht`) lorsque le contexte Dolibarr le permet.
7. Il crée un snapshot dans `llx_dynamicprices_line_cost_snapshot`.

Le calcul ne doit jamais être fait pendant la génération PDF.

### Intégration optionnelle PriceList

Intégration préparée pour la version 3.0.2, sans migration SQL. `DynamicPricesCostService::getCommercialLineCostSourceOptions()` centralise les sources et leurs traductions ; `getPriceListAvailability()` contrôle l'activation de PriceList et les signatures publiques requises. Les réglages, l'Ajax et les triggers réutilisent cette disponibilité. L'ordre stocké peut contenir une source temporairement indisponible ; l'ordre exécuté l'ignore sans réécrire la constante.

`getPriceListCost()` charge la classe externe avec `dol_include_once('/pricelist/class/pricelist.class.php')`, puis appelle directement `PriceList::get_price($productId, $thirdparty, $quantity, $document)` et `getEffectiveCostPriceForRow($row)`. PriceList reste la source de vérité pour les paliers, le client, les catégories, les entités et le mode utilisant le coût natif du produit. DynamicPrices ne recopie pas ces règles et ne rend pas disponibles des catégories absentes de la version Dolibarr courante.

Le retour `0` de `get_price()` signifie « aucun tarif » ; un retour de coût `null` signifie « aucun coût ». Un coût égal à `0` est disponible. Une erreur de résolution empêche le passage silencieux à une source de remplacement. Les sources situées après une source déjà disponible ne sont pas interrogées par la résolution serveur.

L'endpoint `ajax/commercial_line_cost.php` conserve ses champs existants et accepte `document_type` (`propal`, `commande`, `facture`), `document_id`, `qty`, `sale_price` et `line_current_cost`. Il ajoute `enabled`, `pricelist` (disponibilité, montant, libellé, erreur) et `resolution` (montant, source, erreur). Les permissions fonctionnelles sont vérifiées par `hasRight()` ; les objets et leurs entités sont vérifiés avec les contrôles natifs d'accès. Les valeurs PriceList nécessitent le droit `margins/creer`. Les appels historiques sans contexte de document restent limités au coût DynamicPrices.

Le JavaScript ignore une réponse dont le produit, la quantité ou le document a changé. Les champs `dynamicsprices_cost_source_mode`, `dynamicsprices_cost_source` et `dynamicsprices_manual_cost` transportent un choix manuel indépendamment des champs que PriceList modifie dans `doActions`. Ils ne donnent aucun droit : le service vérifie directement `margins/creer`, valide le montant et résout à nouveau une sélection explicite de PriceList. Sans ce droit, la priorité automatique s'applique.

Les triggers natifs `LINEPROPAL_INSERT`, `LINEORDER_INSERT` et `LINEBILL_INSERT` chargent la ligne persistée, sa quantité réelle, son parent et son tiers. Ils appliquent le coût après les hooks de création de ligne, dans la transaction native. L'écriture est limitée à la ligne, au parent et à l'entité autorisés. Un échec de coût ou de snapshot remonte au traitement appelant. Le snapshot porte `source_type = pricelist` lorsque cette source est retenue. Aucun nouveau trigger métier n'est créé.

**Valeur par défaut Dolibarr** désigne toujours le coût reçu par le trigger après les traitements natifs et les autres modules. Pour l'aperçu, l'effet connu du hook PriceList sur ce coût entrant est pris en compte ; un autre module modifiant le coût uniquement à la soumission peut encore modifier cette valeur. Le serveur reste décisionnaire.

Contrats examinés le 2026-09-22 : sources PriceList 2.2.0, commit `33769f4d00ecac94c0f3ab79df9ab5500eca41b9` (`class/pricelist.class.php`, méthodes ci-dessus ; `class/actions_pricelist.class.php`, traitement `addline`). Contrôle natif `checkUserAccessToObject()` et sélecteurs examinés dans Dolibarr 20.0.0 et dans le checkout local `0d20b226f5e13b848bb58528398967f52862688c` (24.0.1-1812-g0d20b226f5e). Il s'agit de lecture de sources, pas d'essais sur ces instances. Le dépôt PriceList voisin évoluant indépendamment, cette preuve reste attachée au commit indiqué.

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
