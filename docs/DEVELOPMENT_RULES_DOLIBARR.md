# Règles de développement Dolibarr

## Périmètre

Le module doit rester une extension Dolibarr native. Les modifications concernent uniquement le module `dynamicsprices`.

## SQL

- Utiliser des fichiers `.sql` pour les tables et `.key.sql` pour les index.
- Garder une colonne `entity` sur toutes les tables métier.
- Ne jamais créer de clé étrangère SQL dure vers les tables standards Dolibarr.
- Ne jamais utiliser `ENUM`.
- Les liaisons vers Dolibarr se font par colonnes `fk_*` et contrôles applicatifs.
- Les requêtes métier filtrent par entité avec `getEntity()` ou l'entité stricte selon le contexte.
- Les migrations doivent être idempotentes.

## Pages

Chaque page doit :

- charger `main.inc.php` ;
- charger les langues nécessaires ;
- vérifier que le module est actif avec `isModEnabled('dynamicsprices')` ;
- vérifier les droits ;
- utiliser `GETPOST()` et `GETPOSTINT()` ;
- protéger les actions sensibles par token ;
- afficher les messages avec `setEventMessages()`.

## Configuration

- `config_page_url` doit rester limité à `setup.php@dynamicsprices`.
- Les autres pages d'administration sont des onglets internes.
- Les constantes métier sont conservées lors de la désactivation/réactivation.
- Les booléens doivent utiliser les switches Dolibarr.
- Les choix fermés doivent utiliser Select2 ou multiselect2 natif.

## Hooks

Les hooks servent à intégrer l'UI ou brancher un point d'entrée Dolibarr existant.

Hooks prévus pour cette feature :

- `productcard` pour le bloc coût DynamicPrices ;
- `productlist` ou contexte liste produit si disponible pour colonne optionnelle ;
- contextes commerciaux à vérifier pour devis, commandes et factures.

La logique métier doit rester dans `DynamicPricesCostService`.

## Triggers

Les triggers doivent être utilisés pour réagir à des événements core Dolibarr.

Pour les objets propres au module, les codes custom doivent rester limités à :

- `DYNAMICPRICES_PRODUCT_COST_CREATE`
- `DYNAMICPRICES_PRODUCT_COST_UPDATE`
- `DYNAMICPRICES_PRODUCT_COST_DELETE`

Les détails métier se portent dans le contexte de l'objet, pas dans des suffixes de trigger spécifiques.

## Permissions

Les permissions doivent être déclarées dans `modDynamicsPrices`.

La numérotation doit utiliser :

```php
$this->rights[$r][0] = $this->numero * 100 + $r;
```

Permissions prévues :

- `cost->read`
- `cost->write`
- `cost->massupdate`
- `cost->admin`
- `cost->history`
- `cost->override`

## Traductions

Toutes les chaînes affichées doivent exister dans :

- `langs/fr_FR/dynamicsprices.lang`
- `langs/en_US/dynamicsprices.lang`

Les valeurs françaises doivent utiliser les accents corrects.

## Sécurité

- Pas de SQL avec entrée utilisateur non échappée.
- Pas d'action sensible sans token CSRF.
- Pas de droit masqué seulement en CSS ou JavaScript.
- Pas de recalcul lourd à l'affichage.
- Pas de données inter-entités.
- Pas de log de secret, token ou contenu client sensible.

## PHPStan et typage

- Déclarer les propriétés utilisées.
- Initialiser les variables avant les branches.
- Vérifier les retours de `fetch()`, `query()` et `fetch_object()`.
- Documenter les tableaux complexes en PHPDoc.
- Ne pas ajouter de baseline ou d'ignore global.
