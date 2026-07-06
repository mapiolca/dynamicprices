# Changelog DynamicsPrices

## 2.2.0
- Ajout d'un prix de revient DynamicPrices par produit et par entité Multicompany, stocké dans des tables dédiées et historisé sans écriture automatique dans `llx_product.cost_price`. / Added a DynamicPrices cost price per product and Multicompany entity, stored in dedicated tables and logged without automatically writing to `llx_product.cost_price`.
- Ajout du service central `DynamicPricesCostService` pour calculer, lire, sauvegarder, historiser et appliquer les coûts aux lignes commerciales. / Added the central `DynamicPricesCostService` to calculate, read, save, log and apply costs to commercial lines.
- Ajout du bloc coût dans l'onglet prix d'achat du produit, de l'historique produit, du recalcul de masse avec prévisualisation/export CSV et de l'assistant de migration non destructive. / Added the cost block in the product supplier prices tab, product history, mass recalculation with preview/CSV export and non-destructive migration assistant.
- Ajout de l'application optionnelle des coûts DynamicPrices sur les lignes de devis, commandes et factures avec snapshots. / Added optional application of DynamicPrices costs to proposal, order and invoice lines with snapshots.
- Ajout de l'API REST et de l'export natif Dolibarr pour les coûts DynamicPrices. / Added REST API and native Dolibarr export for DynamicPrices costs.
- Ajout d'un onglet interne de compatibilité indiquant les versions détectées et la disponibilité des fonctionnalités. / Added an internal compatibility tab showing detected versions and feature availability.
- Ajout des traductions espagnoles, allemandes et italiennes pour la nouvelle fonctionnalité de coût. / Added Spanish, German and Italian translations for the new cost feature.
- Correction du recalcul du prix de revient DynamicPrices pour appliquer strictement la moyenne des prix d'achat unitaires fournisseurs multipliée par le coefficient de la catégorie commerciale, et pour relire l'entité courante dans l'onglet prix d'achat. / Fixed DynamicPrices cost recalculation to strictly apply average supplier unit purchase prices multiplied by the commercial category coefficient, and to reload the current entity in the supplier prices tab.
- Correction du recalcul cron et manuel pour conserver le code de catégorie commerciale, recalculer le prix de revient DynamicPrices avec le bon coefficient et relancer les prix de vente après un recalcul manuel. / Fixed cron and manual recalculation to keep the commercial category code, recalculate the DynamicPrices cost with the right coefficient and refresh selling prices after a manual recalculation.
- Correction de l'application du prix de revient DynamicPrices sur les lignes de devis, commandes et factures en utilisant la colonne native `buy_price_ht` avec fallback compatible. / Fixed DynamicPrices cost application on proposal, order and invoice lines by using the native `buy_price_ht` column with a compatible fallback.
- Ajout du prix de revient DynamicPrices comme option sélectionnée par défaut dans le sélecteur natif de prix de revient des lignes commerciales. / Added the DynamicPrices cost price as the default selected option in the native commercial line cost selector.
- Ajout d'un ordre configurable des sources de prix de revient pour les lignes commerciales, avec conservation du choix manuel des utilisateurs autorisés et réglage plus explicite. / Added a configurable cost source order for commercial lines, with preservation of authorized users' manual choices and clearer settings.
- Limitation de l'application automatique du prix de revient à la création des lignes commerciales, y compris côté JavaScript lorsque Dolibarr réutilise le formulaire `addproduct` pour éditer une ligne, afin de préserver le coût déjà sélectionné lors de l'édition. / Limited automatic cost price application to commercial line creation, including in JavaScript when Dolibarr reuses the `addproduct` form to edit a line, to preserve the already selected cost during line editing.
- Correction des métadonnées d'aide des dictionnaires DynamicPrices pour respecter la structure attendue par l'administration native Dolibarr et éviter les warnings PHP sur les clés de colonnes. / Fixed DynamicPrices dictionary help metadata to match the structure expected by native Dolibarr administration and avoid PHP warnings on column keys.
- Correction de l'activation Multicompany pour ne pas recréer la tâche planifiée DynamicPrices lorsqu'une tâche équivalente existe déjà dans une autre entité. / Fixed Multicompany activation to avoid recreating the DynamicPrices scheduled job when an equivalent job already exists in another entity.
- Présentation du bloc prix de revient de l'onglet prix d'achat sous forme de tableau horizontal à en-têtes et ligne de données. / Displayed the supplier prices tab cost block as a horizontal table with headers and one data row.
- Lecture du dernier calcul disponible lorsqu'il existe plusieurs lignes historiques de coût pour un même produit et une même entité. / Read the latest available calculation when several historical cost rows exist for the same product and entity.
- Correction de la conservation des constantes du module lors de la désactivation/réactivation. / Fixed preservation of module constants during disable/reactivate cycles.

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
