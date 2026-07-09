# ChangeLog DynamicsPrices

## 3.0

### Prix fournisseurs
- Correction de l'actualisation des prix d'achat depuis une commande fournisseur afin de conserver les quantités minimum et conditionnements édités dans la modale.
- Création d'une nouvelle ligne de prix fournisseur lorsque la quantité minimum ou le conditionnement saisi impose une ligne distincte, avec refus contrôlé lorsque la clé unique native Dolibarr empêche un doublon.
- Encodage sécurisé du payload de la modale pour rester compatible avec la protection SQL/script injection de Dolibarr.

### Prix de revient DynamicPrices
- Ajout d'un prix de revient DynamicPrices par produit et par entité Multicompany, stocké dans les tables du module et historisé sans écriture automatique dans `llx_product.cost_price`.
- Centralisation du calcul, de la lecture, de la sauvegarde, de l'historisation et de l'application aux lignes commerciales dans `DynamicPricesCostService`.
- Calcul métier aligné sur la règle fonctionnelle : moyenne des prix d'achat unitaires fournisseurs multipliée par le coefficient de prix de revient de la catégorie commerciale.
- Ajout du bloc prix de revient dans l'onglet prix d'achat du produit, avec affichage en tableau horizontal, historique produit, prévisualisation de recalcul et recalcul de masse.
- Ajout d'une migration non destructive pour initialiser les coûts DynamicPrices à partir des données existantes.
- Ajout de l'API REST et de l'export natif Dolibarr pour les coûts DynamicPrices.

### Lignes commerciales
- Ajout de l'application optionnelle du prix de revient DynamicPrices sur les lignes de devis, commandes et factures, avec snapshots.
- Correction de l'écriture du prix d'achat natif des lignes commerciales en utilisant la colonne physique `buy_price_ht`, avec fallback compatible lorsque `pa_ht` existe réellement.
- Ajout du prix de revient DynamicPrices dans le sélecteur natif de prix de revient des lignes commerciales.
- Ajout d'un ordre configurable des sources de prix de revient pour la création des lignes commerciales.
- Préservation du choix manuel des utilisateurs autorisés.
- Limitation de l'application automatique à la création des lignes : l'édition conserve le prix de revient déjà porté par la ligne, même si le prix DynamicPrices a changé entre temps.

### Multicompany
- Conservation d'un prix de revient DynamicPrices distinct par entité.
- Application du partage Multicompany aux lignes des dictionnaires DynamicPrices, sans créer de déclaration de dictionnaire différente par entité.
- Alignement du dictionnaire des catégories commerciales sur le partage Multicompany des produits, afin que les catégories restent cohérentes avec les produits partagés.
- Ajout de l'entité source `DYNAMICPRICES_SHARED_SELL_PRICE_SOURCE_ENTITY` pour sécuriser le recalcul des prix de vente partagés lorsque les prix d'achat fournisseurs ne sont pas partagés sur le même périmètre.
- Correction de l'activation Multicompany pour ne pas recréer la tâche planifiée DynamicPrices lorsqu'une tâche équivalente existe déjà dans une autre entité.
- Conservation des constantes et réglages du module lors des cycles désactivation/réactivation.

### Dictionnaires et administration
- Refonte des dictionnaires DynamicPrices avec des clés internes stables, des noms de tables non préfixés et des métadonnées compatibles avec l'administration native Dolibarr.
- Correction des warnings PHP liés aux métadonnées de colonnes des dictionnaires.
- Ajout d'un repli propre vers l'administration native des dictionnaires lorsque les anciens fichiers core d'inclusion `actions_dictionnaire.inc.php` et `dict.tpl.php` ne sont pas disponibles.
- Clarification des réglages d'ordre automatique des prix de revient des lignes commerciales.
- Ajout d'un onglet interne de compatibilité indiquant les versions détectées et la disponibilité des fonctionnalités.
- Ajout des traductions espagnoles, allemandes et italiennes sur les nouvelles fonctionnalités.

### Recalculs et tâches planifiées
- Correction du recalcul cron et manuel du prix de revient DynamicPrices avec conservation du code de catégorie commerciale et application du bon coefficient.
- Relance cohérente du recalcul des prix de vente après recalcul manuel lorsque la configuration l'autorise.
- Lecture du dernier calcul disponible lorsqu'il existe plusieurs lignes historiques de coût pour un même produit et une même entité.
- Recalcul des kits à partir des composants lorsque l'option correspondante est active.

## 2.2.0
- Limitation des recalculs trigger aux événements de prix d'achat avec garde-fou anti-duplication par requête pour éviter les doubles recomputations sur un même produit/kit. / Restricted trigger recalculations to buy-price events with per-request deduplication to avoid duplicate recomputations on the same product/kit.
- Ajout d'une chaîne de fallback pour le calcul du coût des kits : prix fournisseur moyen, puis prix de revient, puis PMP, avec arrêt en erreur si aucune valeur n'est disponible. / Added a fallback chain for kit-cost computation: average supplier price, then cost price, then PMP, with hard stop on error when no value is available.
- Enrichissement des notifications de fallback/erreur avec références composant + kit et liens cliquables vers les fiches produit concernées. / Enriched fallback/error notifications with component + kit references and clickable links to the related product cards.
- Ajout des traductions multilingues associées (en_US, fr_FR, de_DE, es_ES, it_IT) pour les nouveaux messages warning/error. / Added associated multilingual translations (en_US, fr_FR, de_DE, es_ES, it_IT) for the new warning/error messages.

## 2.1.0
- Ajout du dictionnaire "Catégories commerciales" pour remplacer l'usage du dictionnaire "Nature de produit" dans les calculs métier.
- Ajout de l'extrafield "Catégorie commerciale" sur les produits et services afin de piloter les règles tarifaires depuis une donnée dédiée.
- Mise en place de la migration des données depuis l'ancien dictionnaire vers le nouveau référentiel.
- Routage des calculs DynamicPrices vers le dictionnaire des catégories commerciales.
- Ajout d'une confirmation à l'envoi de commande fournisseur pour proposer la mise à jour des prix d'achat via une modale affichant les écarts et les choix utilisateur.

## 2.0.1
- Correction du déclenchement des recalculs de prix lors des événements de prix d'achat ou de vente quand l'identifiant produit n'est pas directement porté par l'objet trigger.

## 2.0.0
- Ajout du support des kits : le prix d'un kit est recalculé après ses composants pour éviter les doublons et refléter le coût cumulé.
- Correction des mises à jour intempestives des services : seuls les produits physiques sont recalculés lorsque la configuration exclut les services.
- Extension de la couverture des triggers pour lancer les recalculs sur davantage d'événements Dolibarr.
- Calcul automatique des prix de revient via un dictionnaire dédié et la moyenne des prix d'achat.

## 1.1.1
- Correction du filtre pour ignorer les services lorsque seuls les produits doivent être pris en compte.

## 1.1
- Prise en charge des prix de revient pour l'actualisation des prix de vente.

## 1.0
- Version initiale.
