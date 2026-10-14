# Revue du processus de mise ÃƒÂ  jour des prix fournisseurs

Revue du 14 octobre 2026. Patch local sur `fix/supplier-price-confirmation`, base `b93992b7ffa8c8d5a088cf2e30df6ed3aac59e39`. Ce document dÃƒÂ©crit un correctif prÃƒÂ©parÃƒÂ©, sans attester de son dÃƒÂ©ploiement ni d'une nouvelle version publiÃƒÂ©e.

## DÃƒÂ©fauts reproduits ou observÃƒÂ©s dans les sources

- Les tarifs ÃƒÂ©taient ÃƒÂ©crits par `doActions`, avant la transaction d'envoi de commande. Une erreur ultÃƒÂ©rieure pouvait donc laisser un tarif modifiÃƒÂ© malgrÃƒÂ© l'ÃƒÂ©chec de la commande. Le test exÃƒÂ©cutÃƒÂ© sur la base Git reproduit deux ÃƒÂ©critures dÃƒÂ¨s la prÃƒÂ©paration, puis ÃƒÂ©choue sur l'assertion interdisant ces ÃƒÂ©critures anticipÃƒÂ©es.
- Les droits natifs de modification des tarifs produits/services, les restrictions sur les objets liÃƒÂ©s et le contexte d'entitÃƒÂ© n'ÃƒÂ©taient pas vÃƒÂ©rifiÃƒÂ©s avant l'ÃƒÂ©criture.
- La fermeture du dialogue dÃƒÂ©clenchait une URL d'envoi de commande avec omission de l'actualisation. Fermer une fenÃƒÂªtre ne doit pas envoyer une commande.
- Le dialogue Ajax transportait les valeurs par une charge JSON hexadÃƒÂ©cimale dans une URL. Les ÃƒÂ©vÃƒÂ©nements JavaScript globaux et les rÃƒÂ©ponses du formulaire pouvaient dÃƒÂ©synchroniser sÃƒÂ©lection et donnÃƒÂ©es. Le formulaire natif POST transmet maintenant les contrÃƒÂ´les ordinaires, sans cette charge ni ces ÃƒÂ©vÃƒÂ©nements.
- Des erreurs de lecture SQL ÃƒÂ©taient interprÃƒÂ©tÃƒÂ©es comme une absence de tarif. Elles interrompent dÃƒÂ©sormais la prÃƒÂ©paration ou l'enregistrement.
- La recherche de rÃƒÂ©fÃƒÂ©rence fournisseur ne filtrait pas l'entitÃƒÂ©. Elle est dÃƒÂ©sormais limitÃƒÂ©e ÃƒÂ  l'entitÃƒÂ© courante.
- Le prix pouvait changer entre l'affichage et l'enregistrement. Le prix attendu et l'identifiant sont vÃƒÂ©rifiÃƒÂ©s ÃƒÂ  la confirmation ; le tarif est ensuite verrouillÃƒÂ© et son prix, sa quantitÃƒÂ© et sa rÃƒÂ©fÃƒÂ©rence sont vÃƒÂ©rifiÃƒÂ©s avant modification.

## Parcours corrigÃƒÂ©

1. L'action native `commande` affiche une confirmation `Form::formconfirm` en POST si un prix diffÃƒÂ¨re. Les lignes non cochÃƒÂ©es restent inchangÃƒÂ©es.
2. Un choix distinct permet de mettre ÃƒÂ  jour les tarifs ou de les ignorer. Le paramÃƒÂ¨tre natif `confirm=yes` reste rÃƒÂ©servÃƒÂ© ÃƒÂ  l'envoi de commande : le core le lit avant `doActions`. Le choix Ã‚Â« Ignorer Ã‚Â» continue explicitement l'envoi sans ÃƒÂ©crire de tarif.
3. `main.inc.php` conserve son contrÃƒÂ´le CSRF. Le module refuse ÃƒÂ©galement une requÃƒÂªte sans token, une commande dÃƒÂ©jÃƒÂ  envoyÃƒÂ©e, un utilisateur externe, un acteur sans droit d'envoi ou une commande d'une autre entitÃƒÂ©. Les valeurs techniques du produit et du fournisseur sont reconstruites depuis les lignes serveur.
4. Le hook prÃƒÂ©pare les seules lignes sÃƒÂ©lectionnÃƒÂ©es dans le contexte transitoire `dynamicsprices_supplier_updates`. Il n'ÃƒÂ©crit aucun tarif.
5. `CommandeFournisseur::commande()` appelle le trigger natif `ORDER_SUPPLIER_SUBMIT` dans sa transaction. Le trigger du module consomme le contexte une seule fois et applique les tarifs. Aucune lecture de session ou de paramÃƒÂ¨tres HTTP n'est nÃƒÂ©cessaire dans ce traitement.
6. Une modification utilise `ProductFournisseur::update_buyprice()`. Le chemin de crÃƒÂ©ation existant, qui prÃƒÂ©serve un tarif de rÃƒÂ©fÃƒÂ©rence distinct, conserve les contrÃƒÂ´les de clÃƒÂ© unique, l'historique et le trigger natif `PRODUCT_BUYPRICE_CREATE`. Ses erreurs de dÃƒÂ©but/commit et ses exceptions sont maintenant contrÃƒÂ´lÃƒÂ©es.
7. Un refus de permission, une valeur invalide, un conflit ou une erreur de tarif retourne un ÃƒÂ©chec au trigger ; l'appelant natif annule l'envoi et les ÃƒÂ©critures du lot. Le commit final appartient au core. Les prix unitaires et totaux persistÃƒÂ©s passent par `price2num(..., 'MU'/'MT')`.

Le droit avancÃƒÂ© `product/product_advance/write_supplier_prices` suit le core ÃƒÂ  partir de v23 lorsque `MAIN_USE_ADVANCED_PERMS` est actif. En v20Ã¢â‚¬â€œv22, le droit natif produit/service de crÃƒÂ©ation reste utilisÃƒÂ©. Aucun droit supplÃƒÂ©mentaire ni migration SQL n'est requis. Aucun changement du descripteur ou des hooks persistÃƒÂ©s n'est nÃƒÂ©cessaire.

## VÃƒÂ©rifications et preuves

Sources natives lues localement :

| Dolibarr | Commit | VÃƒÂ©rification de sources |
|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | POST natif, trigger d'envoi transactionnel, droits produits/services |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | POST natif et trigger d'envoi ; absence du droit avancÃƒÂ© fournisseur dans la page |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | MÃƒÂªme vÃƒÂ©rification |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | POST natif, trigger d'envoi, prÃƒÂ©sence du droit avancÃƒÂ© fournisseur |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | MÃƒÂªme vÃƒÂ©rification |
| Checkout core local | `ddf7358479534e7ffe021caf99bd7e13a83811e8` | Lecture dÃƒÂ©taillÃƒÂ©e du formulaire, de la page d'envoi, de `update_buyprice` et des transactions imbriquÃƒÂ©es |
| 25 finale | Non vÃƒÂ©rifiÃƒÂ©e | Aucun tag 25.0.0 disponible dans le checkout examinÃƒÂ© |

Ces lectures ne sont pas des essais sur des instances v20Ã¢â‚¬â€œv25.

Commandes reproductibles depuis la racine du module :

```text
php test/supplier_price_submission_test.php
php test/supplier_price_submission_test.php --baseline
php test/supplier_price_submission_test.php --native-form
php test/dynamicpricescostservice_test.php
php test/commercial_line_cost_test.php
php test/commercial_line_cost_endpoint_test.php
```

`--baseline` charge les classes de `HEAD` et doit ÃƒÂ©chouer avant ce patch : deux tarifs sont ÃƒÂ©crits pendant la prÃƒÂ©paration. AprÃƒÂ¨s commit du correctif, cette option ne dÃƒÂ©signera plus l'ancienne base. `--native-form` nÃƒÂ©cessite le checkout Dolibarr voisin `../dolibarr` ; il exÃƒÂ©cute son vÃƒÂ©ritable `Form::formconfirm` et vÃƒÂ©rifie le DOM produit dans un environnement simulÃƒÂ©, sans navigateur ni session rÃƒÂ©elle.

La suite vÃƒÂ©rifie prÃƒÂ©paration sans ÃƒÂ©criture, succÃƒÂ¨s, ÃƒÂ©chec de la deuxiÃƒÂ¨me ligne avec restauration de la premiÃƒÂ¨re, ÃƒÂ©chec ultÃƒÂ©rieur de commande, absence de droits, mauvaise entitÃƒÂ©, rejeu aprÃƒÂ¨s envoi, token manquant, donnÃƒÂ©es structurÃƒÂ©es invalides, identifiants falsifiÃƒÂ©s, erreur SQL, conflit de prix, remise invalide, ignorance explicite et dÃƒÂ©sÃƒÂ©lection totale. Le test transactionnel utilise des doubles ERP et SQL ; il ne valide pas une base MySQL rÃƒÂ©elle ni le middleware CSRF.

## Limites et recette avant production

- Aucun dÃƒÂ©ploiement ni essai distant effectuÃƒÂ©. La commande 1233 et INT300 n'ont pas ÃƒÂ©tÃƒÂ© modifiÃƒÂ©s. Le journal initial ne permet toujours pas d'attester leur tarif final.
- Faire la recette sur une instance de test : produit et service, droits simples/avancÃƒÂ©s, administrateur sans droit, deux entitÃƒÂ©s et produit partagÃƒÂ©, crÃƒÂ©ation d'un nouveau tarif et conservation du tarif de rÃƒÂ©fÃƒÂ©rence, conflit de clÃƒÂ© unique, ÃƒÂ©chec d'historique/trigger et commit final sur l'abstraction SQL rÃƒÂ©elle.
- Tester POST avec/sans token et token invalide, choix Ã‚Â« Ignorer Ã‚Â», aucune ligne sÃƒÂ©lectionnÃƒÂ©e, fonctionnement sans JavaScript, tÃƒÂ©lÃƒÂ©phone, tablette et bureau. La confirmation est dÃƒÂ©sormais intÃƒÂ©grÃƒÂ©e ÃƒÂ  la page ; aucune fermeture de dialogue ne lance une commande.
- VÃƒÂ©rifier les notifications et dÃƒÂ©clencheurs manuels propres ÃƒÂ  l'instance. La transaction SQL ne peut pas annuler un email ou un appel externe dÃƒÂ©jÃƒÂ  ÃƒÂ©mis par un autre trigger.
- Le verrou contrÃƒÂ´le le prix, la quantitÃƒÂ© et la rÃƒÂ©fÃƒÂ©rence au moment d'ÃƒÂ©crire ; il ne constitue pas un contrÃƒÂ´le de version complet de tous les attributs d'un tarif. La prÃƒÂ©vention du rejeu dÃƒÂ©pend du statut de la commande relu par la page ; un double envoi strictement simultanÃƒÂ© doit ÃƒÂªtre testÃƒÂ© sur la base rÃƒÂ©elle.
- Les traces INFO `supplier update staged` indiquent une ÃƒÂ©criture dans la transaction, pas un succÃƒÂ¨s final. Pour confirmer le rÃƒÂ©sultat, corrÃƒÂ©ler le `COMMIT`/`ROLLBACK` natif au niveau DEBUG et contrÃƒÂ´ler le tarif et son historique en base.
- PHPStan n'est pas installÃƒÂ© dans le module ni dans le checkout core voisin. L'analyse statique reste ÃƒÂ  exÃƒÂ©cuter sur l'outillage de l'instance cible. Aucune configuration ni baseline n'a ÃƒÂ©tÃƒÂ© affaiblie.

Les calculs de coÃƒÂ»t DynamicPrices, la tarification commerciale, les PDF et les objets propres au module restent hors de cette correction. Les suites existantes vÃƒÂ©rifient leur non-rÃƒÂ©gression disponible.
