# AGENTS.md - DynamicPrices

Ce dépôt contient le module externe Dolibarr `dynamicsprices`, installé en production sous `htdocs/custom/dynamicsprices`.

## Documentation locale

- Audit technique : `docs/technical-audit-dynamic-cost.md`
- Architecture du coût DynamicPrices : `docs/ARCHITECTURE_DYNAMIC_COST.md`
- Règles de développement Dolibarr : `docs/DEVELOPMENT_RULES_DOLIBARR.md`
- Plan de test : `docs/TEST_PLAN_DYNAMIC_COST.md`

## Objectif fonctionnel

La fonctionnalité en cours doit permettre à chaque entité d'une instance Dolibarr Multicompany d'exploiter un prix de revient DynamicPrices propre, distinct du prix de revient natif Dolibarr.

## Règles impératives

- Ne jamais modifier le core Dolibarr.
- Ne jamais écrire automatiquement dans `llx_product.cost_price`.
- Stocker le coût DynamicPrices dans les tables du module.
- Garder `llx_product.cost_price` comme source lisible ou écriture legacy explicite, désactivée par défaut.
- Ne pas créer de clé étrangère SQL dure vers les tables standards Dolibarr.
- Ne pas utiliser d'`ENUM` SQL.
- Toutes les tables métier du module doivent contenir `entity`.
- Toutes les requêtes métier doivent filtrer par entité.
- Toutes les chaînes affichées doivent passer par les fichiers de langue `fr_FR` et `en_US`.
- Utiliser l'indentation par tabulations en PHP.
- Contrôler les droits côté serveur.
- Utiliser les helpers Dolibarr : `GETPOST()`, `GETPOSTINT()`, `price2num()`, `isModEnabled()`, `$user->hasRight()`.
- Protéger les actions sensibles par token CSRF.
- Terminer chaque tâche par une revue des fichiers modifiés.

## Commandes utiles

Ce dépôt ne fournit pas encore de configuration de test autonome. Si PHPStan est disponible dans l'instance Dolibarr cible :

```bash
vendor/bin/phpstan analyse htdocs/custom/dynamicsprices
```

Pour un contrôle syntaxique local des fichiers PHP modifiés :

```bash
php -l path/to/file.php
```

## Ordre de travail recommandé

1. Audit et documentation.
2. Tables SQL du coût DynamicPrices.
3. Service central `DynamicPricesCostService`.
4. Remplacement des écritures automatiques vers `cost_price`.
5. UI fiche produit et historique.
6. Recalcul de masse.
7. Application aux lignes commerciales.
8. API, exports, migration, documentation finale.
