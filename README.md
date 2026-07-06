# DynamicsPrices

## Présentation

DynamicsPrices est un module externe Dolibarr qui calcule et exploite des prix dynamiques à partir des coûts, prix fournisseurs et coefficients métier.

Depuis la version 2.2.0, le module dispose d'un prix de revient propre par produit et par entité Multicompany. Ce prix est stocké dans les tables du module, historisé, exposé en API/export et peut être utilisé pour alimenter les coûts de lignes commerciales sans écraser le champ natif Dolibarr `llx_product.cost_price`.

## Fonctionnalités

- Calcul des prix de revient DynamicPrices par couple `entity + product`.
- Historique des recalculs et des valeurs appliquées.
- Sources configurables : PMP, moyenne fournisseur, meilleur prix fournisseur, fournisseur principal, coût natif Dolibarr, valeur manuelle.
- Fallback configurable lorsque le coût DynamicPrices est absent.
- Recalcul unitaire depuis la fiche produit.
- Prévisualisation sans écriture.
- Recalcul de masse avec simulation, confirmation et export CSV.
- Migration non destructive depuis l'ancien comportement.
- Option d'application aux lignes de devis, commandes et factures via `pa_ht`, désactivée par défaut.
- Snapshots des coûts appliqués aux lignes commerciales.
- API REST pour lecture, historique, recalcul et override manuel.
- Export Dolibarr natif des coûts DynamicPrices.
- Traductions `fr_FR`, `en_US`, `es_ES`, `de_DE` et `it_IT`.

## Compatibilité

- Dolibarr v20 ou supérieur.
- PHP 8.0 ou supérieur.
- MySQL/MariaDB via l'abstraction Dolibarr.
- Module externe à installer dans `htdocs/custom/dynamicsprices`.

## Installation

### Depuis une archive ZIP

1. Télécharger l'archive du module.
2. Déployer l'archive via **Accueil > Configuration > Modules > Déployer un module externe**.
3. Activer le module **DynamicsPrices** dans **Configuration > Modules/Applications**.

### Depuis un dépôt Git

```bash
cd htdocs/custom
git clone git@github.com:gitlogin/dynamicsprices.git dynamicsprices
```

Puis activer le module depuis l'administration Dolibarr.

## Configuration

Les réglages se trouvent dans `admin/setup.php`, seul point d'entrée déclaré dans le descripteur du module.

Les onglets internes disponibles sont :

- réglages ;
- compatibilité ;
- migration des coûts ;
- à propos.

Réglages principaux du prix de revient DynamicPrices :

- `DYNAMICPRICES_COST_ENABLE` : active la lecture et le calcul du coût DynamicPrices.
- `DYNAMICPRICES_COST_USE_FOR_SALES` : autorise l'application aux lignes commerciales. Désactivé par défaut.
- `DYNAMICPRICES_COST_LINE_STRATEGY` : stratégie d'application aux lignes.
- `DYNAMICPRICES_COST_FALLBACK` : comportement si aucun coût DynamicPrices n'est disponible.
- `DYNAMICPRICES_COST_SOURCE_PRIORITY` : ordre des sources de calcul.
- `DYNAMICPRICES_COST_INCLUDE_SERVICES` : inclut les services dans le calcul.
- `DYNAMICPRICES_COST_LOG_MODE` : historisation des changements uniquement ou de tous les recalculs.
- `DYNAMICPRICES_COST_ALLOW_MANUAL_OVERRIDE` : autorise l'override manuel via API.
- `DYNAMICPRICES_COST_ALLOW_NATIVE_WRITE` : option legacy pour écrire aussi dans `llx_product.cost_price`. Elle est désactivée par défaut.

## Migration

L'assistant `admin/migrate_dynamic_cost.php` initialise les coûts DynamicPrices sans modifier le coût natif Dolibarr.

Modes disponibles :

- depuis le coût Dolibarr ;
- depuis le calcul moteur DynamicPrices ;
- mixte ;
- aucun, pour simulation ou contrôle.

La migration est filtrée par entité courante, protégée par token CSRF et rejouable grâce à la clé unique `(entity, fk_product)`.

## Utilisation

Sur la fiche produit, le module affiche le coût DynamicPrices courant, la source, la règle, le dernier calcul et l'accès à l'historique lorsque l'utilisateur dispose des droits.

Le recalcul de masse est disponible via `cost_mass_update.php`. Il permet de filtrer les produits, prévisualiser les résultats, exporter la simulation en CSV, puis confirmer l'écriture.

Les coûts appliqués aux lignes commerciales sont activables explicitement. Tant que `DYNAMICPRICES_COST_USE_FOR_SALES` reste désactivé, les devis, commandes et factures conservent le comportement Dolibarr standard.

## API et export

Le module expose une API REST dédiée aux coûts DynamicPrices :

- `GET /costs/{product_id}` ;
- `GET /costs/{product_id}/history` ;
- `POST /costs/{product_id}/recalculate` ;
- `POST /costs/recalculate` ;
- `POST /costs/{product_id}/manual` ;
- `DELETE /costs/{product_id}/manual`.

Un export Dolibarr natif `dynamicsprices_dynamic_cost` liste les coûts DynamicPrices produits de l'entité courante.

## Documentation technique

- `docs/technical-audit-dynamic-cost.md` : audit initial.
- `docs/ARCHITECTURE_DYNAMIC_COST.md` : architecture du prix de revient DynamicPrices.
- `docs/MIGRATION_DYNAMIC_COST.md` : procédure de migration.
- `docs/TEST_PLAN_DYNAMIC_COST.md` : plan de test fonctionnel et technique.
- `docs/DEVELOPMENT_RULES_DOLIBARR.md` : règles locales de développement Dolibarr.

## Licence

Code sous GPLv3 ou ultérieure.
