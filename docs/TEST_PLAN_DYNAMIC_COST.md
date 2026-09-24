# Plan de test - Prix de revient DynamicPrices

## Installation

- Activer le module.
- Vérifier la création des tables de coût DynamicPrices.
- Désactiver puis réactiver le module.
- Vérifier qu'aucun réglage n'est perdu.
- Vérifier que `modulebuilder.txt` reste présent.

## SQL

- Vérifier que chaque table contient `entity`.
- Vérifier les index attendus.
- Vérifier la clé unique `(entity, fk_product)` du coût courant.
- Vérifier la clé unique `(entity, element_type, fk_elementdet)` des snapshots.
- Vérifier qu'aucune clé étrangère SQL dure vers le core Dolibarr n'est créée.

## Calcul

- Produit physique avec PMP ou prix fournisseur moyen.
- Produit physique sans source.
- Service ignoré par défaut.
- Service inclus si option active.
- Kit avec composants possédant tous un prix de revient DynamicPrices valide : somme des coûts multipliés par les quantités.
- Kit avec un composant sans coût DynamicPrices ou portant un statut de calcul en erreur : calcul refusé et montant courant nul.
- Kit imbriqué : propagation du recalcul du composant jusqu'à tous les kits parents sans boucle.
- Kit avec `DYNAMICPRICES_COST_RECALC_KITS` désactivé : calcul marqué indisponible.
- Ancien coût présent puis recalcul en erreur : l'ancien montant n'est ni affiché ni retourné comme coût effectif.
- Prix fournisseur actif et complet inclus dans la moyenne.
- Prix fournisseur inactif, sans référence, sans quantité valide ou sans prix unitaire exclu de la moyenne.
- Composant sans coût.
- Coût nul volontaire.
- Coût `NULL` issu d'un recalcul en erreur qui doit invalider l'ancien coût courant sans toucher au prix de revient natif Dolibarr.
- Recalcul identique avec mode log `changes_only`.
- Recalcul identique avec mode log complet.

## Multicompany

- Créer un coût pour le même produit en entité A.
- Créer un coût différent pour le même produit en entité B.
- Vérifier les listes, exports et lectures API.
- Vérifier qu'une entité ne lit pas le coût d'une autre entité.
- Vérifier les produits partagés.

## Fiche produit

- Afficher coût Dolibarr, PMP, coût DynamicPrices, source, règle, statut, date.
- Recalculer depuis la fiche.
- Prévisualiser sans écriture.
- Consulter l'historique.
- Afficher un avertissement lorsqu'au moins un prix fournisseur incomplet est ignoré.
- Refuser côté navigateur et côté serveur une actualisation de prix fournisseur avec une référence vide.
- Vérifier les boutons selon les permissions.
- Vérifier qu'aucun recalcul lourd ne se lance au simple affichage.

## Recalcul de masse

- Simulation sans écriture.
- Confirmation après token valide.
- Annulation sans écriture.
- Produits filtrés par type.
- Produits filtrés par coût absent.
- Produits avec erreur de calcul.
- Export CSV du résultat.
- Traitement par lots si volume élevé.

## Ventes

- Option `DYNAMICPRICES_COST_USE_FOR_SALES` désactivée : comportement Dolibarr inchangé.
- Devis direct avec produit : coût DynamicPrices appliqué si option active.
- Commande depuis devis : coût conservé ou recalculé selon stratégie.
- Facture depuis commande : coût conservé ou recalculé selon stratégie.
- Facture directe : coût DynamicPrices appliqué.
- Ligne sans produit : aucune action.
- Coût DynamicPrices absent avec fallback `keep_dolibarr`.
- Coût absent avec fallback `block`.
- Snapshot créé pour chaque ligne traitée.

## Intégration PriceList

Contrôles automatisés de l'intégration préparée pour la version 3.0.2, exécutables depuis la racine du module :

```sh
php test/dynamicpricescostservice_test.php
php test/commercial_line_cost_test.php
php test/commercial_line_cost_test.php missing
php test/commercial_line_cost_test.php incompatible
php test/commercial_line_cost_endpoint_test.php
```

Les tests métier couvrent l'ordre conservé, l'activation sans ajout automatique de PriceList à l'ordre, les doublons, les sources inconnues, la désactivation/réactivation, l'absence de tarif ou de coût, zéro explicite, les erreurs sans repli, les choix manuels autorisés ou falsifiés, les snapshots, les entités et l'absence de recalcul des lignes existantes. Ils vérifient la transmission du produit, du tiers, de la quantité et du document au contrat PriceList ; ils ne reproduisent pas son moteur de règles.

Les tests de points d'entrée exécutent le véritable endpoint Ajax et les véritables triggers du module dans des processus PHP isolés avec objets, droits et base simulés. Ils contrôlent les trois documents, les refus d'accès, les entités, les entrées invalides, un palier simulé à zéro, les créations sans navigateur et les erreurs. Aucun document commercial réel n'est créé.

Le test navigateur hors ligne utilise le JavaScript modifié et les bibliothèques jQuery/Select2 d'un checkout Dolibarr, avec formulaire et réponses Ajax simulés :

```sh
node test/commercial_line_cost_browser_test.cjs /chemin/vers/dolibarr/htdocs
```

Il nécessite Playwright déjà disponible dans l'environnement (sans ajout de dépendance au module), Edge sous Windows ou Chromium Playwright ailleurs. Il vérifie les trois routes de fiches, les quantités, les réponses obsolètes, le choix Select2, la conservation d'une saisie manuelle, les erreurs et l'intégration désactivée. Il ne valide pas les formulaires complets d'une instance Dolibarr.

Résultats au 2026-09-22 : tests PHP réussis sous PHP 8.4.22, tests Ajax/triggers isolés et test navigateur hors ligne réussis. Socle déclaré : Dolibarr 20 / PHP 8.0 ; ce couple et une installation réelle Multicompany n'ont pas été exécutés. PHPStan n'est pas installé dans l'environnement utilisé. Aucune instance distante n'a été déployée ou utilisée pour valider le correctif.

### Recette sur une instance servant effectivement le correctif

- Vérifier le code déployé, noter les versions Dolibarr/PHP/PriceList/Multicompany et relever les réglages initiaux avant tout essai.
- PriceList actif et compatible : cinq rangs et libellé **Prix de revient Tarifs Dégréssifs**, Select2, option **Ignorer**, suppression des doublons à l'enregistrement. Vérifier l'absence d'ajout automatique de PriceList à l'ordre existant.
- PriceList absent ou incompatible : aucune nouvelle sélection possible. Désactiver puis réactiver avec un rang enregistré, enregistrer les autres rangs pendant la désactivation et vérifier la conservation de la position PriceList.
- Configurer deux ordres différents dans les entités A et B ; vérifier leur indépendance après rechargement et désactivation/réactivation.
- Sur devis, commande et facture, comparer aperçu, `pa_ht` et snapshot avec plusieurs ordres, plusieurs paliers, tarif absent, coût absent, coût zéro et mode PriceList utilisant le coût natif du produit.
- Vérifier les règles propres au client, aux catégories client et aux catégories document disponibles sur la version courante. Ces règles sont exécutées par PriceList, non simulées par DynamicPrices.
- Tester un produit partagé entre deux entités avec tarifs distincts et les refus sur document, tiers ou produit hors périmètre ; tester aussi un utilisateur externe et un commercial limité à ses tiers.
- Tester l'utilisateur sans accès aux coûts : aucune restitution Ajax de coût et application automatique à la création. Falsifier les champs manuels sans ce droit, puis tester un choix manuel autorisé malgré le hook PriceList.
- Tester sans JavaScript et via API/import utilisant les triggers natifs : quantité réelle et document transmis, erreur PriceList propagée à la transaction, aucun coût de remplacement enregistré.
- Vérifier **Valeur par défaut Dolibarr** avec l'option PriceList de non-écrasement activée/désactivée et avec les autres modules qui modifient le coût entrant.
- Vérifier `MAIN_MAX_DECIMALS_UNIT`, les montants à plusieurs décimales et les saisies françaises. Contrôler que `product.cost_price` et les prix de vente restent inchangés par DynamicPrices.
- Modifier une ligne existante : cette intégration ne réapplique pas les priorités. Vérifier les stratégies `never` et `preserve_origin` lors des conversions de documents.
- Contrôler le formulaire de réglage avec/sans token et restaurer les réglages temporaires ; les tests de recette ne doivent pas déclencher de notification ou de document externe sans autorisation.

Pas de migration, de nouveau cron, d'Agenda, de Notifications, de numérotation ou de génération documentaire dans ce périmètre : leurs tests spécifiques ne s'appliquent pas à cette évolution.

## Créations automatiques Workflow (3.0.3)

Le correctif distingue le recalcul facultatif de DynamicPrices de l'autorisation du parcours natif. L'absence de droit `creer` sur le document cible fait ignorer le recalcul, sans erreur ajoutée, recherche de tarif, écriture ni snapshot. Un utilisateur autorisé conserve les contrôles d'entité et d'accès métier existants et les erreurs de calcul restent bloquantes. L'endpoint Ajax conserve son contrôle de lecture du document et ses droits de consultation des coûts.

### Vérification automatisée du correctif

`php test/commercial_line_cost_endpoint_test.php` exécute 53 scénarios isolés. La matrice ajoutée couvre devis, commande et facture, utilisateur standard et administrateur, coût positif, zéro et valeur nulle. Elle vérifie le retour neutre, l'absence de requête SQL et d'appel PriceList, l'absence d'écriture et d'erreur ajoutée et le contenu limité du diagnostic DEBUG. Les refus Ajax restent vérifiés pour les trois documents et les deux profils.

Le nouveau test a reproduit le refus `DynamicPricesCostAccessDenied` avant modification du trigger. Après correction, les 53 scénarios passent sous PHP 8.4.22, ainsi que `dynamicpricescostservice_test.php` et `commercial_line_cost_test.php` (modes normal, `missing` et `incompatible`). Le contrôle `php -l` réussit sur les fichiers PHP modifiés.

Ces résultats du 2026-09-23 proviennent de doubles de test (avec `DOL_VERSION` simulé à 20.0.0), pas d'une instance Dolibarr : ils ne prouvent ni la signature réelle ni le commit de la commande Workflow. PHPStan est indisponible ; le checkout Dolibarr local ne contient pas de configuration `htdocs/conf/conf.php`. Aucun déploiement, envoi de notification ni test navigateur distant n'a été effectué. Le couple Dolibarr 20 / PHP 8.0 et Multicompany réel restent à valider.

### Recette restant à exécuter sur Dolibarr 23.0.2

1. Utiliser une instance de test servant le correctif 3.0.3 ; noter PHP, Multicompany et les réglages Workflow, Agenda et Notifications. Reproduire les droits et l'entité du commercial sans lui accorder `commande.creer`.
2. Préparer deux devis, avec et sans batterie, avec prix de revient connus. Signer chaque devis : contrôler le statut signé, une seule commande liée, les lignes complètes et le coût issu du traitement natif sans recalcul DynamicPrices ni nouveau snapshot du module. Pour un coût nul ou zéro, comparer avec la valeur effectivement fournie par Dolibarr, qui peut appliquer ses propres règles avant le trigger.
3. Rejouer le parcours natif après succès : vérifier l'absence de seconde commande. Tester aussi un administrateur dont `hasRight('commande', 'creer')` est faux, puis un utilisateur autorisé avec recalcul normal. Vérifier qu'une création manuelle reste refusée au commercial.
4. Avec un utilisateur autorisé au recalcul, vérifier le refus des documents/tiers hors périmètre et d'une entité non accessible. Provoquer une erreur de calcul en environnement de test : vérifier la propagation de l'erreur et le rollback natif.
5. Vérifier les refus Ajax sans droit de lecture ou de consultation des coûts. Relever séparément les éventuelles traces `fk_user_modif`, qui relèvent du core 23.0.2 et ne sont pas corrigées par ce patch.
6. Comparer puis restaurer les réglages temporaires sans écraser de changements concurrents ; inventorier les données de test et effets externes éventuels. Ne pas interpréter un rollback comme l'annulation d'un email déjà envoyé.

## Migration

- Simulation depuis coût Dolibarr.
- Simulation depuis calcul moteur.
- Mode mixte.
- Confirmation.
- Rejeu de la migration sans doublon.
- Vérification que `llx_product.cost_price` reste inchangé.
- Logs créés.

## Permissions

- Administrateur.
- Utilisateur sans droit.
- Utilisateur lecture seule.
- Utilisateur recalcul unitaire.
- Utilisateur recalcul masse.
- Utilisateur historique.
- Utilisateur override manuel.

## API et exports

- Lecture coût courant.
- Lecture historique.
- Recalcul unitaire.
- Recalcul masse.
- Override manuel.
- Suppression override manuel.
- Export des coûts DynamicPrices.
- Vérification des droits et de l'entité.

## Sécurité

- Formulaires sans token refusés.
- Actions GET sensibles sans token refusées.
- Entrées invalides rejetées.
- SQL échappé.
- Aucun warning PHP évident.

## Analyse statique

- Exécuter PHPStan si disponible.
- À défaut, exécuter `php -l` sur les fichiers PHP modifiés.
- Documenter les commandes exécutées et les limites.
