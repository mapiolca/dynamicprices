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
- Kit avec composants.
- Composant sans coût.
- Coût nul volontaire.
- Coût `NULL` qui ne doit pas écraser un coût existant hors purge volontaire.
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
