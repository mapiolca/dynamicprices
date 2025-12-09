# Changelog DynamicsPrices

## 2.0.0
- Ajout du support des kits : le prix d'un kit est recalculé après ses composants pour éviter les doublons et refléter le coût cumulé.
- Correction des mises à jour intempestives des services : seuls les produits physiques sont recalculés (`fk_product_type = 0`).
- Extension de la couverture des triggers pour lancer les recalculs sur davantage d'événements Dolibarr.
- Calcul automatique des prix de revient via un dictionnaire dédié et la moyenne des prix d'achat.

## 2.0.0 (EN)
- Added kit support: a kit price is recalculated after its components to avoid duplicates and reflect the cumulative cost.
- Fixed unintended service updates: only physical products are recalculated (`fk_product_type = 0`).
- Expanded trigger coverage to launch recalculations on more Dolibarr events.
- Automatic cost-price computation via a dedicated dictionary and average purchase prices.

## 1.1.1
- Correction du filtre pour ignorer les services ; seuls les produits sont pris en compte. (29/09/2025)

## 1.1.1 (EN)
- Fixed the filter to ignore services; only products are taken into account. (29/09/2025)

## 1.1
- Prise en charge des prix de revient pour l'actualisation des prix de vente. (17/09/2025)

## 1.1 (EN)
- Added cost-price handling to refresh selling prices. (17/09/2025)

## 1.0
- Version initiale.

## 1.0 (EN)
- Initial release.
