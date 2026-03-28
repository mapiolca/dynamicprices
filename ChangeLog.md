# Changelog DynamicsPrices

## 2.1.0
- Refonte de la gestion des dictionnaires avec l'ajout du dictionnaire "Catégories commerciales" pour remplacer l'usage du dictionnaire "Nature de produit" dans les calculs métier. / Refactored dictionary management by adding the "Commercial categories" dictionary to replace the "Product nature" dictionary in business calculations.
- Ajout de l'extrafield "Catégorie commerciale" sur les produits et services afin de piloter les règles tarifaires depuis une donnée dédiée. / Added the "Commercial category" extrafield on products and services to drive pricing rules from a dedicated field.
- Mise en place de la migration des données depuis l'ancien dictionnaire vers le nouveau et routage de tous les calculs de DynamicPrices vers ce nouveau référentiel. / Implemented data migration from the legacy dictionary to the new one and rerouted all DynamicPrices computations to this new reference.
- Ajout d'une confirmation à l'envoi de commande fournisseur pour proposer la mise à jour des prix d'achat via une modale affichant les écarts et les choix utilisateur (valider/ignorer). / Added a confirmation step on supplier-order submission to propose purchase-price updates through a modal showing differences and user choices (apply/ignore).

## 2.0.1
- Correction du déclenchement des recalculs de prix lors des événements de prix d'achat/vente quand l'identifiant produit n'est pas directement porté par l'objet trigger. / Fixed price recalculation trigger execution on buy/sell price events when the product identifier is not directly available on the trigger object.

## 2.0.0
- Ajout du support des kits : le prix d'un kit est recalculé après ses composants pour éviter les doublons et refléter le coût cumulé. / Added kit support: a kit price is recalculated after its components to avoid duplicates and reflect the cumulative cost.
- Correction des mises à jour intempestives des services : seuls les produits physiques sont recalculés (`fk_product_type = 0`). / Fixed unintended service updates: only physical products are recalculated (`fk_product_type = 0`).
- Extension de la couverture des triggers pour lancer les recalculs sur davantage d'événements Dolibarr. / Expanded trigger coverage to launch recalculations on more Dolibarr events.
- Calcul automatique des prix de revient via un dictionnaire dédié et la moyenne des prix d'achat. / Automatic cost-price computation via a dedicated dictionary and average purchase prices.

## 1.1.1
- Correction du filtre pour ignorer les services ; seuls les produits sont pris en compte. (29/09/2025) / Fixed the filter to ignore services; only products are taken into account. (29/09/2025)

## 1.1
- Prise en charge des prix de revient pour l'actualisation des prix de vente. (17/09/2025) / Added cost-price handling to refresh selling prices. (17/09/2025)

## 1.0
- Version initiale. / Initial release.
