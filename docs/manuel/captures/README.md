# Captures d'écran du manuel utilisateur

Déposez ici vos captures au format **PNG**, avec exactement les noms de fichier ci-dessous. Elles
s'intègrent automatiquement dans `../manuel-utilisateur.html` — aucune modification du HTML n'est
nécessaire. Tant qu'une capture est absente, un cadre hachuré indique ce qu'il faut photographier.

## Liste des captures attendues

Statut : **7 captures sur 15 déposées.** Les 8 restantes sont signalées ci-dessous.

| Fichier | Écran à capturer | Compte à utiliser | État requis | Statut |
|---|---|---|---|---|
| `01-connexion.png` | Écran de connexion complet (panneau de marque + formulaire) | — | — | ✅ |
| `02-interface.png` | Tableau de bord entier, barre supérieure et menu latéral bien visibles | `admin@` | — | ✅ |
| `03-periodes.png` | Menu **Périodes** : les 12 mois avec leurs statuts | `admin@` | — | ⬜ |
| `04-taux.png` | Menu **Taux de change**, décembre 2026 (EUR, MAD, GHS) | `admin@` | — | ⬜ |
| `05-saisie.png` | Formulaire de saisie avec le bandeau vert « Bilan équilibré » | `preparer.ma@` | Maroc en brouillon | ⬜ |
| `06-desequilibre.png` | Message d'erreur rouge après avoir faussé un montant du bilan | `preparer.ma@` | Fausser puis **ne pas** enregistrer | ⬜ |
| `07-revue.png` | Panneau « Revue du contrôleur » (boutons Valider / Rejeter + champ motif) | `controller.ma@` | Maroc soumis | ⬜ |
| `08-historique.png` | Tableau « Historique du workflow » : soumis → rejeté → soumis → validé | `controller.ma@` | Après le cycle complet | ⬜ |
| `09-intercompany.png` | Menu **Intercompany**, paire Sénégal / France en statut Écart | `consolidation@` | — | ⬜ |
| `10-consolidation.png` | Menu **Consolidation** : bloc de lancement + historique des runs | `consolidation@` | — | ✅ |
| `10b-run.png` | Journal d'exécution : les 7 étapes en statut Terminé | `consolidation@` | Après avoir lancé un run | ⬜ |
| `11-liasse.png` | **Liasse groupe** : compte de résultat, codes REF visibles | `consolidation@` | Après un run | ✅ |
| `12-bilan.png` | Bilan consolidé actif + passif, totaux BZ et DZ visibles | `consolidation@` | Après un run | ⬜ |
| `13-dashboard.png` | Tableau de bord complet (tuiles, courbe, donut, contribution) | `admin@` | Après un run | ⬜ |
| `14-budget.png` | **Budget vs Actual** : tuiles de synthèse + tableau Mois / Cumul | `admin@` | — | ✅ |
| `15-audit.png` | **Journal d'audit** avec un filtre appliqué sur une filiale | `admin@` | Après le cycle Maroc | ✅ |
| `16-hierarchie.png` | Menu **Hiérarchie** : organigramme du groupe | `admin@` | — | ✅ |

## Ordre de capture recommandé

Certaines captures dépendent de l'état de la base. En suivant cet ordre, vous n'avez à parcourir le
cycle qu'une seule fois :

1. **Avant tout** — base réinitialisée : `01`, `02`, `03`, `04`, `14`
2. **Cycle Maroc** (`preparer.ma@` puis `controller.ma@`) : `05`, `06`, `07`, `08`
3. **Après validation des 6 filiales** (`consolidation@`) : `09`
4. **Après le lancement du run** : `10`, `11`, `12`, `13`, `15`

## Conseils de prise de vue

- Zoom navigateur à **100 %**, fenêtre large (1400 px minimum) pour éviter le repli responsive.
- Capturer la **zone utile** plutôt que tout l'écran : inutile d'inclure la barre de tâches Windows.
- Sous Windows : `Win + Maj + S` pour la capture de zone.
- Format **PNG** (le JPEG dégrade la netteté du texte).
- Largeur idéale : 1200 à 1600 px. Au-delà, le fichier s'alourdit sans gain visible à l'impression.

## Générer le PDF

Ouvrir `../manuel-utilisateur.html` dans Chrome ou Edge, puis :

1. `Ctrl + P`
2. Destination : **Enregistrer au format PDF**
3. Format : **A4**, marges : **Par défaut**
4. **Décocher** « En-têtes et pieds de page »
5. **Cocher** « Graphiques d'arrière-plan » *(indispensable : sans cette option, les couleurs des
   en-têtes de tableaux et des encadrés ne sont pas imprimées)*
