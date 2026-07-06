# Migration - Prix de revient DynamicPrices

## Objectif

Initialiser les prix de revient DynamicPrices sans modifier le champ natif Dolibarr `llx_product.cost_price`.

## Assistant

L'assistant est disponible dans `admin/migrate_dynamic_cost.php`, comme onglet interne des réglages du module.

Il est réservé aux administrateurs.

## Modes

- Depuis coût Dolibarr : copie le coût natif dans la table DynamicPrices.
- Depuis calcul moteur : calcule le coût DynamicPrices avec les règles actuelles.
- Mixte : tente le calcul moteur, puis reprend le coût Dolibarr si aucun calcul n'est disponible.
- Aucun : ne crée aucune donnée.

## Sécurité

- Simulation avant confirmation.
- Confirmation protégée par token CSRF.
- Filtrage par entité courante.
- Aucune écriture dans `llx_product.cost_price`.
- Création d'un historique pour chaque coût migré.
- Rejouable grâce à la clé unique `(entity, fk_product)`.

## Points de contrôle

- Vérifier le nombre de lignes créées.
- Vérifier le nombre de lignes mises à jour.
- Vérifier le nombre de lignes ignorées.
- Contrôler un produit dans deux entités différentes.
- Vérifier que le coût natif Dolibarr reste inchangé.
