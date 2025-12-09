# Changelog DynamicsPrices

## 2.0.0
- Ajout du support des kits : le prix d'un kit est recalculé après ses composants pour éviter les doublons et refléter le coût cumulé.
- Correction des mises à jour intempestives des services : seuls les produits physiques sont recalculés (`fk_product_type = 0`).
- Extension de la couverture des triggers pour lancer les recalculs sur davantage d'événements Dolibarr.
- Calcul automatique des prix de revient via un dictionnaire dédié et la moyenne des prix d'achat.

## 1.1.1
- Correction du filtre pour ignorer les services ; seuls les produits sont pris en compte. (29/09/2025)

## 1.1
- Prise en charge des prix de revient pour l'actualisation des prix de vente. (17/09/2025)

## 1.0
- Version initiale.
