# DynamicsPrices

Module Dolibarr pour la mise à jour dynamique des prix de vente à partir des coûts d'achat et des coefficients configurables.

## Aperçu

DynamicsPrices automatise le recalcul des prix des produits en s'appuyant sur les prix d'achat moyens, les coefficients de marge et les relations entre produits (composants, kits). Les déclencheurs du module s'occupent d'appliquer les nouveaux prix de vente au bon moment, tout en respectant les spécificités des produits et services Dolibarr.

## Fonctionnalités clés

- Mise à jour automatique des prix de vente en fonction du prix d'achat moyen et d'un dictionnaire de coefficients dédié.
- Recalcul des kits après leurs composants pour éviter les doublons de prix de vente et refléter le coût cumulé des sous-produits et services.
- Filtrage des services : seuls les produits physiques (`fk_product_type = 0`) sont recalculés pour éviter les mises à jour intempestives.
- Plus grand nombre de triggers pour couvrir les actions courantes (création, modification, réception d'achat, etc.).
- Calcul automatique des prix de revient à partir des nouveaux dictionnaires et de la moyenne des prix d'achat.

## Compatibilité

- Dolibarr ≥ 19.0 (minimum recommandé).
- Module externe installable dans `htdocs/custom/dynamicsprices`.

## Installation

### Depuis une archive ZIP

1. Télécharger l'archive `module_dynamicsprices-x.y.z.zip`.
2. Déployer l'archive via le menu **Accueil > Configuration > Modules > Déployer un module externe**.
3. Activer le module **DynamicsPrices** dans **Configuration > Modules/Applications**.

### Depuis un dépôt Git

```bash
cd htdocs/custom
git clone git@github.com:gitlogin/dynamicsprices.git dynamicsprices
```

Puis activer le module dans Dolibarr comme décrit ci-dessus.

## Mise à jour

1. Sauvegarder la base de données et le répertoire du module.
2. Installer la nouvelle version (ZIP ou Git) dans `htdocs/custom/dynamicsprices`.
3. Lancer les scripts de migration proposés par Dolibarr si nécessaire.

## Configuration

- **Dictionnaire des coefficients** : définir les coefficients de marge dans **Dictionnaires > Coefficients DynamicsPrices**.
- **Triggers** : les déclencheurs DynamicsPrices mettent à jour les prix lors des actions standards (création de produit, réception fournisseur, modification de prix, etc.).
- **Kits** : le prix de vente d'un kit est recalculé uniquement après mise à jour des prix de ses composants pour éviter toute duplication.

## Utilisation

- Créer ou mettre à jour un produit avec un prix d'achat renseigné.
- Les triggers calculent automatiquement le prix de revient et le prix de vente suivant le coefficient applicable.
- Les services et produits non physiques (`fk_product_type != 0`) sont ignorés par les mises à jour automatiques.

## Permissions et sécurité

- Les actions de mise à jour sont soumises aux permissions Dolibarr standard sur les produits et dictionnaires.
- Les écrans du module masquent automatiquement les actions non autorisées.

## Traductions

Les fichiers de langue sont disponibles dans `langs/`. Complétez ou ajustez les traductions en `en_US` et `fr_FR` pour tout nouveau libellé.

## Support

- Documentation et support Dolibarr : [https://wiki.dolibarr.org](https://wiki.dolibarr.org)
- Autres modules externes : [Dolistore.com](https://www.dolistore.com)

## Licence

- Code : GPLv3 ou ultérieure (voir `COPYING`).
- Documentation : GFDL (voir la licence correspondante).
