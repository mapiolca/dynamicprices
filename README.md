# DynamicsPrices

## Présentation (FR)

Module Dolibarr pour la mise à jour dynamique des prix de vente à partir des coûts d'achat et des coefficients configurables.

### Aperçu

DynamicsPrices automatise le recalcul des prix des produits en s'appuyant sur les prix d'achat moyens, les coefficients de marge et les relations entre produits (composants, kits). Les déclencheurs du module s'occupent d'appliquer les nouveaux prix de vente au bon moment, tout en respectant les spécificités des produits et services Dolibarr.

### Fonctionnalités clés

- Mise à jour automatique des prix de vente en fonction du prix d'achat moyen et d'un dictionnaire de coefficients dédié.
- Recalcul des kits après leurs composants pour éviter les doublons de prix de vente et refléter le coût cumulé des sous-produits et services.
- Filtrage des services : seuls les produits physiques (`fk_product_type = 0`) sont recalculés pour éviter les mises à jour intempestives.
- Plus grand nombre de triggers pour couvrir les actions courantes (création, modification, réception d'achat, etc.).
- Calcul automatique des prix de revient à partir des nouveaux dictionnaires et de la moyenne des prix d'achat.

### Compatibilité

- Dolibarr ≥ 19.0 (minimum recommandé).
- Module externe installable dans `htdocs/custom/dynamicsprices`.

### Installation

#### Depuis une archive ZIP

1. Télécharger l'archive `module_dynamicsprices-x.y.z.zip`.
2. Déployer l'archive via le menu **Accueil > Configuration > Modules > Déployer un module externe**.
3. Activer le module **DynamicsPrices** dans **Configuration > Modules/Applications**.

#### Depuis un dépôt Git

```bash
cd htdocs/custom
git clone git@github.com:gitlogin/dynamicsprices.git dynamicsprices
```

Puis activer le module dans Dolibarr comme décrit ci-dessus.

### Mise à jour

1. Sauvegarder la base de données et le répertoire du module.
2. Installer la nouvelle version (ZIP ou Git) dans `htdocs/custom/dynamicsprices`.
3. Lancer les scripts de migration proposés par Dolibarr si nécessaire.

### Configuration

- **Dictionnaire des coefficients** : définir les coefficients de marge dans **Dictionnaires > Coefficients DynamicsPrices**.
- **Triggers** : les déclencheurs DynamicsPrices mettent à jour les prix lors des actions standards (création de produit, réception fournisseur, modification de prix, etc.).
- **Kits** : le prix de vente d'un kit est recalculé uniquement après mise à jour des prix de ses composants pour éviter toute duplication.

### Utilisation

- Créer ou mettre à jour un produit avec un prix d'achat renseigné.
- Les triggers calculent automatiquement le prix de revient et le prix de vente suivant le coefficient applicable.
- Les services et produits non physiques (`fk_product_type != 0`) sont ignorés par les mises à jour automatiques.

### Permissions et sécurité

- Les actions de mise à jour sont soumises aux permissions Dolibarr standard sur les produits et dictionnaires.
- Les écrans du module masquent automatiquement les actions non autorisées.

### Traductions

Les fichiers de langue sont disponibles dans `langs/`. Complétez ou ajustez les traductions en `en_US` et `fr_FR` pour tout nouveau libellé.

### Support

- Documentation et support Dolibarr : [https://wiki.dolibarr.org](https://wiki.dolibarr.org)
- Autres modules externes : [Dolistore.com](https://www.dolistore.com)

### Licence

- Code : GPLv3 ou ultérieure (voir `COPYING`).
- Documentation : GFDL (voir la licence correspondante).

---

## Overview (EN)

Dolibarr module for dynamically updating selling prices based on purchase costs and configurable margins.

### Summary

DynamicsPrices automates price recalculations using average purchase prices, margin coefficients, and relationships between products (components, kits). Module triggers apply new selling prices at the right time while respecting Dolibarr product and service specifics.

### Key features

- Automatic selling-price updates driven by average purchase price and a dedicated coefficient dictionary.
- Kits recalculated after their components to avoid duplicate selling prices and to reflect cumulative component and service costs.
- Service filtering: only physical products (`fk_product_type = 0`) are recalculated to avoid unintended updates.
- Expanded trigger coverage for common actions (creation, modification, purchase receipt, etc.).
- Automatic cost-price computation from dedicated dictionaries and purchase-price averages.

### Compatibility

- Dolibarr ≥ 19.0 (recommended minimum).
- External module installable in `htdocs/custom/dynamicsprices`.

### Installation

#### From a ZIP archive

1. Download the `module_dynamicsprices-x.y.z.zip` archive.
2. Deploy it via **Home > Setup > Modules > Deploy an external module**.
3. Enable the **DynamicsPrices** module in **Setup > Modules/Applications**.

#### From a Git repository

```bash
cd htdocs/custom
git clone git@github.com:gitlogin/dynamicsprices.git dynamicsprices
```

Then enable the module in Dolibarr as described above.

### Upgrade

1. Back up the database and the module directory.
2. Install the new version (ZIP or Git) in `htdocs/custom/dynamicsprices`.
3. Run any migration scripts proposed by Dolibarr if needed.

### Configuration

- **Coefficient dictionary**: define margin coefficients in **Dictionaries > DynamicsPrices Coefficients**.
- **Triggers**: DynamicsPrices triggers update prices during standard actions (product creation, supplier receipt, price edits, etc.).
- **Kits**: a kit's selling price is recalculated only after updating its component prices to prevent duplication.

### Usage

- Create or update a product with a purchase price filled in.
- Triggers automatically compute cost price and selling price using the applicable coefficient.
- Services and non-physical products (`fk_product_type != 0`) are ignored by automated updates.

### Permissions and security

- Update actions follow Dolibarr standard permissions for products and dictionaries.
- Module screens automatically hide actions that the user is not allowed to perform.

### Translations

Language files live under `langs/`. Complete or adjust translations in `en_US` and `fr_FR` for any new labels.

### Support

- Dolibarr documentation and support: [https://wiki.dolibarr.org](https://wiki.dolibarr.org)
- Other external modules: [Dolistore.com](https://www.dolistore.com)

### License

- Code: GPLv3 or later (see `COPYING`).
- Documentation: GFDL (see the corresponding license).
