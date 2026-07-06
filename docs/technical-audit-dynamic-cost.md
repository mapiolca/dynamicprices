# Audit technique - Prix de revient DynamicPrices

## Objectif

Ajouter un prix de revient DynamicPrices distinct du prix de revient natif Dolibarr, historisé par entité et exploitable ensuite dans les lignes commerciales, sans écriture automatique dans `llx_product.cost_price`.

Le dépôt inspecté est un dépôt de module seul. Il ne contient pas le core Dolibarr complet ; les signatures exactes des classes de devis, commandes, factures et lignes commerciales devront donc être vérifiées dans l'instance Dolibarr cible avant l'intégration ventes.

## État actuel

Le module est structuré comme un module externe Dolibarr installé sous `htdocs/custom/dynamicsprices`.

Fichiers principaux relevés :

- `core/modules/modDynamicsPrices.class.php` : descripteur du module, dictionnaires, cron, chargement SQL, extrafield produit.
- `lib/dynamicsprices.lib.php` : moteur actuel de calcul des prix de vente et du prix de revient natif.
- `core/triggers/interface_99_modDynamicsPrices_DynamicsPricesTriggers.class.php` : recalcul automatique sur événements produits/prix fournisseurs.
- `class/actions_dynamicsprices.class.php` : hooks sur la fiche commande fournisseur pour proposer la mise à jour des prix d'achat fournisseur.
- `class/cron_dynamicsprices.class.php` : cron journalier de mise à jour des prix.
- `admin/setup.php` : réglage des constantes existantes et rendu des dictionnaires.
- `sql/*.sql` : dictionnaires `c_coefprice`, `c_margin_on_cost`, `c_commercial_category`.
- `langs/fr_FR/dynamicsprices.lang` et `langs/en_US/dynamicsprices.lang` : traductions principales.

Le module contient déjà `modulebuilder.txt`, `README.md` et `ChangeLog.md` à la racine du module.

## Points de calcul existants

Le calcul actuel est porté par `lib/dynamicsprices.lib.php` :

- `dynamicsprices_get_average_supplier_price()` calcule une moyenne des prix fournisseurs.
- `dynamicsprices_get_margin_on_cost_percent()` lit le taux de marge sur prix de revient.
- `dynamicsprices_get_price_rules()` lit les coefficients de prix de vente.
- `dynamicsprices_update_prices_from_base()` écrit les prix de vente dans `product_price`.
- `dynamicsprices_update_kit_cost_price()` calcule un coût de kit depuis ses composants.
- `dynamicsprices_update_kit_prices_from_components()` calcule les prix de vente de kits depuis les prix composants.
- `update_customer_prices_from_suppliers()` orchestre le recalcul depuis les prix fournisseurs.
- `update_customer_prices_from_cost_price()` orchestre le recalcul depuis le coût natif.

## Écritures actuelles dans `cost_price`

Le point d'écriture direct est centralisé ici :

- `lib/dynamicsprices.lib.php` : `dynamicsprices_save_cost_price($db, $productId, $costPrice)`
  - exécute `UPDATE ... product SET cost_price = ... WHERE rowid = ...`.

Cette fonction est appelée par :

- `dynamicsprices_update_kit_cost_price()` ;
- `update_customer_prices_from_suppliers()` ;
- `update_customer_prices_from_cost_price()`.

Ces écritures sont automatiques et devront être remplacées par l'enregistrement dans `dynamicprices_product_cost`, avec option legacy explicite pour écrire aussi dans `product.cost_price`.

## Lectures de `cost_price`

Lectures identifiées :

- `update_customer_prices_from_cost_price()` lit `p.cost_price` pour calculer les prix de vente lorsque `LMDB_COST_PRICE_ONLY` est actif.
- Les futures migrations pourront lire `cost_price` comme source d'initialisation, sans l'écrire.

## Hooks existants

Le descripteur déclare actuellement le contexte hook :

- `ordersuppliercard`

La classe `ActionsDynamicsPrices` implémente :

- `doActions()` pour traiter la confirmation custom `dynamicsprices_confirm_commande`.
- `formConfirm()` pour afficher une modale avant l'envoi de commande fournisseur.

Ces hooks concernent uniquement les prix d'achat fournisseur. Aucun hook produit, liste produit, devis, commande client ou facture client n'est encore déclaré pour le coût DynamicPrices.

## Triggers existants

Le descripteur active les triggers avec `module_parts['triggers'] = 1`.

`InterfaceDynamicsPricesTriggers::runTrigger()` réagit aux actions core suivantes lorsque `LMDB_SUPPLIER_BUYPRICE_ALTERED` est active :

- `SUPPLIER_PRODUCT_BUYPRICE_CREATE`
- `SUPPLIER_PRODUCT_BUYPRICE_MODIFY`
- `SUPPLIER_PRODUCT_BUYPRICE_DELETE`
- `PRODUCT_MODIFY`
- `PRODUCT_CREATE`
- `PRODUCT_CLONE`
- `PRODUCT_PRICE_CREATE`
- `PRODUCT_PRICE_MODIFY`
- `PRODUCT_PRICE_DELETE`
- `PRODUCT_BUYPRICE_CREATE`
- `PRODUCT_BUYPRICE_MODIFY`
- `PRODUCT_BUYPRICE_DELETE`
- `PRODUCT_SUBPRODUCT_ADD`
- `PRODUCT_SUBPRODUCT_UPDATE`
- `PRODUCT_SUBPRODUCT_DELETE`

Le trigger résout un produit, appelle le moteur de recalcul, puis recalcule aussi les kits parents.

## SQL existant

Tables module existantes :

- `llx_c_coefprice`
- `llx_c_margin_on_cost`
- `llx_c_commercial_category`

Constats :

- Les dictionnaires ont une colonne `entity` sauf anciens chemins de migration dans `dolibarr_allversions.sql`.
- Les fichiers SQL actuels ne contiennent pas encore de table de coût courant, historique ou snapshot de ligne.
- Aucun fichier `.key.sql` dédié aux nouvelles tables n'existe encore.

## Descripteur module

Points structurants :

- `numero = 450002`
- `rights_class = 'dynamicsprices'`
- `config_page_url = array('setup.php@dynamicsprices')`
- `depends = array('modProduct', 'modFournisseur', 'modSociete')`
- `phpmin = array(7, 1)` et `need_dolibarr_version = array(19, -3)` : à relever vers PHP 8.0 / Dolibarr 20 pour la nouvelle trajectoire.
- `cronjobs` déclare un cron journalier `Cron_DynamicsPrices::updatePrices`.
- Aucune permission active n'est déclarée ; le bloc est encore celui du squelette ModuleBuilder commenté.
- `init()` appelle `_load_tables('/dynamicsprices/sql/')`, donc les nouveaux fichiers SQL seront chargés à l'activation.
- `init()` appelle actuellement `remove($options)` avant `_init()`, ce qui mérite une revue car les consignes demandent de conserver les réglages à la désactivation/réactivation.

## Classes Dolibarr utilisées ou à vérifier

Classes déjà référencées par le module :

- `Product` via `DOL_DOCUMENT_ROOT.'/product/class/product.class.php'`.
- `ProductFournisseur` via `DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php'`.
- `CommandeFournisseur` via `DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php'`.
- `Societe` via `DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php'`.
- `ExtraFields`, `Form`, `FormSetup` et les helpers d'administration core.

Classes à inspecter dans le core Dolibarr cible avant les lots ventes :

- `Propal` et lignes `PropaleLigne` / table `propaldet`.
- `Commande` et lignes `OrderLine` / table `commandedet`.
- `Facture` et lignes `FactureLigne` / table `facturedet`.
- Hooks et triggers disponibles autour de `addline()`, `updateline()`, `LINEPROPAL_INSERT`, `LINEORDER_INSERT`, `LINEBILL_INSERT` et variantes `MODIFY`.
- Champs exacts de ligne disponibles selon version : `pa_ht`, `fk_fournprice`, `fk_product`, `fk_parent_line`, `special_code`, `rang`.

## Architecture proposée

Ajouter une couche coût DynamicPrices séparée :

1. SQL
   - `llx_dynamicprices_product_cost`
   - `llx_dynamicprices_product_cost_log`
   - `llx_dynamicprices_line_cost_snapshot`

2. Service central
   - `class/dynamicpricescostservice.class.php`
   - lecture, calcul, sauvegarde, historique, fallback et application aux lignes commerciales.

3. Moteur existant
   - conserver les fonctions actuelles pour les prix de vente ;
   - remplacer uniquement l'écriture automatique dans `product.cost_price` par une sauvegarde du coût DynamicPrices ;
   - garder `product.cost_price` comme source lisible et comme écriture legacy optionnelle désactivée par défaut.

4. Interface
   - hook `pricesuppliercard` pour afficher le bloc coût DynamicPrices dans l'onglet prix d'achat ;
   - page d'historique paginée ;
   - page de recalcul de masse avec simulation puis confirmation.

5. Ventes
   - intégrer plus tard via hooks/triggers de lignes commerciales après vérification du core cible ;
   - figer le coût dans `pa_ht` uniquement si `DYNAMICPRICES_COST_USE_FOR_SALES` est actif ;
   - enregistrer un snapshot par ligne traitée.

## Liste de fichiers à créer

- `AGENTS.md`
- `docs/ARCHITECTURE_DYNAMIC_COST.md`
- `docs/DEVELOPMENT_RULES_DOLIBARR.md`
- `docs/TEST_PLAN_DYNAMIC_COST.md`
- `docs/MIGRATION_DYNAMIC_COST.md`
- `sql/llx_dynamicprices_product_cost.sql`
- `sql/llx_dynamicprices_product_cost.key.sql`
- `sql/llx_dynamicprices_product_cost_log.sql`
- `sql/llx_dynamicprices_product_cost_log.key.sql`
- `sql/llx_dynamicprices_line_cost_snapshot.sql`
- `sql/llx_dynamicprices_line_cost_snapshot.key.sql`
- `class/dynamicpricescostservice.class.php`
- `class/api_dynamicprices_cost.class.php` si l'API est activée dans ce module
- `product_cost_history.php`
- `cost_mass_update.php`
- `admin/compatibility.php`
- `admin/migrate_dynamic_cost.php`

## Liste de fichiers à modifier

- `core/modules/modDynamicsPrices.class.php`
- `lib/dynamicsprices.lib.php`
- `class/actions_dynamicsprices.class.php`
- `core/triggers/interface_99_modDynamicsPrices_DynamicsPricesTriggers.class.php`
- `class/cron_dynamicsprices.class.php`
- `admin/setup.php`
- `langs/fr_FR/dynamicsprices.lang`
- `langs/en_US/dynamicsprices.lang`
- `langs/es_ES/dynamicsprices.lang`
- `langs/de_DE/dynamicsprices.lang`
- `langs/it_IT/dynamicsprices.lang`
- `README.md`
- `ChangeLog.md`

## Stratégie de migration

La migration doit être non destructive :

1. Lire `product.cost_price` et le calcul DynamicPrices actuel.
2. Proposer une simulation par produit et par entité.
3. Créer ou mettre à jour `dynamicprices_product_cost`.
4. Créer une ligne de log.
5. Ne jamais modifier `product.cost_price`.
6. Conserver la possibilité de rejouer la migration sans doublon grâce à la clé unique `(entity, fk_product)`.

## Stratégie de test

Tests prioritaires :

- Activation / désactivation / réactivation du module.
- Création des nouvelles tables et index.
- Recalcul d'un produit physique avec prix fournisseur moyen.
- Produit sans source : coût DynamicPrices `NULL` ou fallback configuré.
- Service ignoré par défaut.
- Kit recalculé après composants.
- Historique créé au recalcul.
- Aucune écriture dans `product.cost_price` lorsque `DYNAMICPRICES_COST_ALLOW_NATIVE_WRITE = 0`.
- Affichage dans l'onglet prix d'achat avec coût Dolibarr et coût DynamicPrices.
- Recalcul de masse en simulation puis confirmation.
- Deux entités avec coûts différents pour le même produit partagé.
- PHPStan si l'outillage est disponible.

## Risques techniques

- Le moteur actuel est fonctionnel mais peu encapsulé ; le service central devra le réutiliser progressivement sans casser les prix de vente.
- `init()` appelle `remove()` avant `_init()`, ce qui peut supprimer des éléments de configuration selon le comportement core. À vérifier et corriger prudemment.
- Les permissions ne sont pas encore déclarées ; les pages existantes s'appuient surtout sur l'administration ou des constantes.
- Les hooks produit et lignes commerciales dépendent des versions Dolibarr ciblées ; il faudra valider les contextes disponibles sur Dolibarr v20+.
- Les fonctions kit ne filtrent pas explicitement `product_association` par entité ; cela peut être acceptable si la table core n'a pas `entity`, mais doit être confirmé.
- Certains logs `LOG_WARNING` semblent être des traces temporaires dans `ActionsDynamicsPrices`.
- Le cron appelle les fonctions de recalcul avec un ordre de paramètres incohérent dans le code actuel (`$conf` et `$langs` inversés par rapport aux signatures). À corriger avant tests cron.

## Questions à résoudre avant codage profond

- Quelle version exacte de Dolibarr du parc servira de référence de vérification en plus du socle v20 ?
- Les services doivent-ils rester ignorés par défaut uniquement pour le coût DynamicPrices ou aussi pour les prix de vente existants ?
- Pour les lignes commerciales issues d'un document source, la stratégie par défaut doit-elle être `preserve_origin` plutôt que `on_create_only` ?
- L'export Dolibarr doit-il être livré en V1 même si l'API est repoussée ?
- Le mode legacy d'écriture dans `product.cost_price` doit-il être réservé aux administrateurs seulement ou visible dans un bloc avancé de configuration ?
