# Script & roadmap — vidéo de présentation OHADA_CONSO+

Durée cible : **8 minutes**. Format : capture d'écran + voix off.

---

# PARTIE 1 — ROADMAP (à faire AVANT d'enregistrer)

## A. Préparer la base (5 min)

L'état de départ conditionne toute la démo. Remets-la à zéro :

```
mysql -u root -e "DROP DATABASE IF EXISTS groupfin; CREATE DATABASE groupfin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root groupfin < database/schema.sql
mysql -u root groupfin < database/seed.sql
php database/seed_financials.php
php database/seed_workflow.php
php database/seed_intercompany.php
```

## B. Pré-valider 5 filiales hors caméra (5 min) — **étape la plus importante**

Après le reset, 5 filiales sont *soumises* mais pas *validées*. Les valider à l'écran = 5 connexions
successives, ennuyeux et long. Fais-le **avant** d'enregistrer.

Pour chacun de ces 5 comptes (mot de passe `Groupfin@2026`) :
`controller.sn@` · `controller.ci@` · `controller.ml@` · `controller.fr@` · `controller.gh@` (`...@novaafrica.com`)

→ Se connecter → menu **Données financières** → période **2026-12** → bouton **Valider le paquet**.

**Ne touche PAS au Maroc** : il doit rester en brouillon, c'est lui que tu montreras en direct.

Pourquoi c'est malin : quand tu valideras le Maroc pendant la vidéo, le compteur passera à 6/6 et l'alerte
« *Toutes les filiales du périmètre sont validées* » apparaîtra sur le tableau de bord — enchaînement naturel
vers la consolidation. Sans cette préparation, tu ne peux pas lancer la conso à l'écran.

## C. Vérifier l'état de départ (1 min)

- Tableau de bord : KPIs affichés, **pas** de panneau « Situation bilancielle groupe » (normal, aucun run).
- Liasse groupe : « Aucune consolidation terminée » (normal). **Ne montre pas cet onglet avant la §7.**
- Intercompany : la paire Sénégal/France est en statut **Écart**.

## D. Préparer l'enregistrement (5 min)

- Ouvrir 2 fenêtres/onglets : une session **préparateur Maroc**, une session **contrôleur Maroc** — ça évite
  4 déconnexions/reconnexions à l'écran (fais-le en navigation privée pour la 2ᵉ session).
- Zoom navigateur à **100 %**, fenêtre en plein écran, masquer la barre de favoris.
- Fermer notifications système, Slack, mail.
- Vider le bureau si tu montres autre chose que le navigateur.
- Faire un test d'1 min : son OK, pas de saturation, débit lent et posé.

## E. Roadmap de tournage

| # | Séquence | Durée |
|---|---|---|
| 1 | Intro & problème métier | 0:45 |
| 2 | Connexion + tableau de bord | 1:00 |
| 3 | Structure de groupe | 0:30 |
| 4 | Saisie + contrôle d'équilibre | 1:00 |
| 5 | Workflow d'approbation (Maroc) | 1:15 |
| 6 | Intercompany | 0:40 |
| 7 | Moteur de consolidation | 1:00 |
| 8 | Liasse OHADA + exports | 1:00 |
| 9 | Retour dashboard + Budget vs Actual | 0:50 |
| 10 | Traçabilité + conclusion technique | 0:50 |

Conseil : enregistre **séquence par séquence**, pas d'une traite. Tu montes ensuite bout à bout.

---

# PARTIE 2 — SCRIPT

> Le texte en **«  »** est à dire. Les lignes `→` sont les actions à l'écran.

---

## 1 · Intro & problème métier — 0:45

→ *Écran de connexion affiché, immobile.*

« Bonjour. Je vous présente **OHADA_CONSO+**, une plateforme de consolidation financière et de reporting de
groupe, que j'ai développée de bout en bout.

Le problème qu'elle résout est concret. Prenez un groupe présent dans six pays — c'est le cas de mon groupe de
démonstration, NOVA AFRICA GROUP : Sénégal, Côte d'Ivoire, Mali, Maroc, Ghana et France. Chaque filiale tient
sa comptabilité, dans **sa propre devise**. À la fin de chaque mois, le groupe doit produire **un seul** compte
de résultat et **un seul** bilan, consolidés.

Ça veut dire : collecter les données de chaque filiale, les faire valider, **éliminer les opérations internes
au groupe** — sinon on compte deux fois le même euro —, tout convertir dans une devise unique, et présenter le
résultat selon la norme comptable **OHADA**, celle utilisée dans dix-sept pays d'Afrique.

C'est exactement le cycle que fait cette plateforme. Je vous le montre en entier. »

---

## 2 · Connexion + tableau de bord — 1:00

→ *Se connecter avec `admin@novaafrica.com`.*

« Je me connecte en tant qu'administrateur groupe. »

→ *Le tableau de bord s'affiche. Laisser 2 secondes.*

« Voici le tableau de bord de direction. En haut, les indicateurs clés du mois : chiffre d'affaires, EBITDA,
résultat net, et les marges — chacun avec **son écart par rapport au budget**, en vert si c'est favorable, en
rouge sinon.

En dessous, l'évolution sur douze mois, la répartition par filiale, et le classement complet. »

→ *Cliquer sur le bouton **EBITDA** du donut → il se redessine.*

« Ce graphique est dynamique : je bascule entre chiffre d'affaires et EBITDA, **sans recharger la page**. »

→ *Changer le filtre **Filiale** sur NOVA-CI, puis revenir sur « Toutes ».*

« Même chose pour les filtres — période, pays, filiale : tout se recalcule instantanément. »

→ *Pointer le panneau **Alertes**.*

« Et ici, les alertes : la plateforme me dit qu'il reste des paquets non validés. On va s'en occuper. »

---

## 3 · Structure de groupe — 0:30

→ *Menu **Hiérarchie**.*

« Voici la structure du groupe. NOVA Holding en tête, les six filiales en dessous.

Chaque filiale porte **sa méthode de consolidation** : intégration globale quand le groupe la contrôle, mise en
équivalence quand il n'a qu'une participation — c'est le cas du Ghana. Ce n'est pas décoratif : le moteur de
consolidation traite ces deux cas différemment. »

→ *Cliquer le bouton rond sous NOVA Holding : les filiales se replient. Re-cliquer.*

---

## 4 · Saisie + contrôle d'équilibre — 1:00

→ *Basculer sur la session **`preparer.ma@novaafrica.com`** → Données financières → décembre 2026.*

« Je passe côté filiale. Voici le préparateur du Maroc — c'est lui qui saisit les comptes.

Compte de résultat, bilan, flux de trésorerie. Et surtout : la plateforme **vérifie l'équilibre du bilan en
temps réel**. »

→ *Modifier la **Trésorerie** (ex. ajouter un zéro) → **Enregistrer**.*

« Je fausse volontairement la trésorerie… et l'enregistrement est **bloqué**. Le message me donne l'écart exact
en francs CFA. Un bilan déséquilibré ne peut structurellement pas entrer dans le système. »

→ *Remettre la valeur d'origine → Enregistrer → « Bilan équilibré » réapparaît.*

« Je corrige, l'équilibre est rétabli. Je peux soumettre. »

→ *Cliquer **Soumettre pour validation**.*

---

## 5 · Workflow d'approbation — 1:15

« Le paquet est soumis, et le formulaire est maintenant **en lecture seule** : le préparateur ne peut plus le
modifier. »

→ *Basculer sur la session **`controller.ma@novaafrica.com`** → même écran.*

« Côté contrôleur, deux boutons apparaissent : valider ou rejeter. Je rejette, avec un motif obligatoire. »

→ *Saisir « Écart de marge vs budget à justifier » → **Rejeter**.*

→ *Revenir sur la session préparateur, recharger.*

« Le préparateur voit immédiatement le motif du rejet, et son formulaire redevient modifiable. Il corrige, il
resoumet. »

→ *Cliquer **Soumettre pour validation**.*

→ *Session contrôleur → **Valider le paquet**.*

« Cette fois, le contrôleur valide. »

→ *Faire défiler jusqu'à l'**historique du workflow** en bas de page.*

« Et tout est tracé : soumis, rejeté, resoumis, validé — avec qui, quand, et le motif. Sur un sujet financier,
cette traçabilité n'est pas optionnelle. »

---

## 6 · Intercompany — 0:40

→ *Revenir sur la session **admin** → menu **Intercompany**.*

« Étape suivante : les opérations internes au groupe.

Le Sénégal déclare une créance de 100 millions sur la France. La France déclare la dette correspondante. La
plateforme **rapproche automatiquement les deux déclarations**, les convertit, et compare. »

→ *Pointer la ligne en statut **Écart**.*

« Ici, elle détecte un **écart** : les deux montants ne correspondent pas. Les deux contrôleurs et le
responsable consolidation sont notifiés automatiquement.

Point important : la plateforme **n'élimine que les paires rapprochées**. Un écart non résolu n'est jamais
masqué — il reste visible et sera signalé dans la consolidation. »

---

## 7 · Moteur de consolidation — 1:00

→ *Menu **Tableau de bord** — l'alerte « prêt à consolider » est maintenant présente.*

« Maintenant que les six filiales sont validées, l'alerte a changé : la consolidation peut être lancée. »

→ *Menu **Consolidation** → sélectionner décembre 2026 → **Lancer la consolidation**.*

« Je la lance. »

→ *Le journal d'exécution s'affiche. Le parcourir lentement.*

« Et voilà le cœur du projet. Le moteur exécute **sept étapes**, et **journalise chacune d'elles** :

1. vérification du périmètre — toutes les filiales sont-elles validées ;
2. **conversion des devises** — taux moyen pour le compte de résultat, taux de clôture pour le bilan ;
3. **élimination des opérations intercompany** ;
4. élimination des dividendes internes ;
5. **mise en équivalence** pour le Ghana ;
6. ajustements manuels ;
7. calcul des **intérêts minoritaires** — la part qui revient aux actionnaires externes.

Rien n'est une boîte noire : chaque étape dit ce qu'elle a fait. Vous voyez ici qu'elle signale explicitement
l'écart intercompany non résolu. »

→ *Descendre au bilan consolidé.*

« Et le bilan consolidé est équilibré **au centime près**. »

---

## 8 · Liasse OHADA + exports — 1:00

→ *Menu **Liasse groupe**.*

« Voici le livrable final : les états financiers consolidés, au format **normalisé OHADA/SYCEBNL**.

Ce sont les codes de référence officiels : XB pour le chiffre d'affaires, XC la valeur ajoutée, XD l'excédent
brut d'exploitation, XI le résultat net. Un expert-comptable de la zone OHADA lit ce document sans aucune
explication — c'est exactement la trame qu'il attend. »

→ *Faire défiler jusqu'au bilan actif/passif.*

« Bilan actif à gauche, passif à droite. Les deux totaux généraux sont identiques. »

→ *Cliquer **Exporter la liasse (CSV)** → montrer le fichier téléchargé (ouvrir dans Excel si possible).*

« Exportable en CSV, directement exploitable dans Excel — avec les accents et les séparateurs corrects. »

→ *Revenir, cliquer **Exporter (PDF)** → la boîte d'impression s'ouvre → montrer l'aperçu.*

« Et en PDF, avec une mise en page dédiée : plus de menus, un en-tête de document propre. »

→ *Annuler l'impression.*

---

## 9 · Retour dashboard + Budget vs Actual — 0:50

→ *Menu **Tableau de bord**.*

« Je reviens au tableau de bord — et un nouveau panneau est apparu : la **situation bilancielle du groupe**,
avec le ratio d'endettement. Il n'était pas là tout à l'heure, parce qu'il n'existait pas encore de
consolidation officielle. La plateforme **n'invente jamais un chiffre** : s'il n'y a pas de donnée, il n'y a
pas de panneau. »

→ *Menu **Budget vs Actual**.*

« Dernier écran d'analyse : le suivi budgétaire, compte par compte. À gauche le mois, à droite le cumul depuis
janvier. La barre indique l'**ampleur** de l'écart, la couleur son sens. »

→ *Cliquer **Mois seul**, puis **Cumul seul**.*

« Et je peux réduire l'affichage à ce qui m'intéresse, instantanément. »

---

## 10 · Traçabilité + conclusion technique — 0:50

→ *Menu **Journal d'audit**, filtrer sur une filiale.*

« Enfin, la traçabilité : **chaque action** effectuée dans la plateforme est enregistrée — qui, quand, quelle
valeur avant, quelle valeur après. Filtrable par utilisateur, filiale ou période. »

→ *Écran fixe, ou retour au tableau de bord.*

« Pour terminer, deux mots sur la technique.

L'application est écrite en **PHP et MySQL**, avec une architecture MVC que j'ai construite moi-même, et
**zéro dépendance externe** : pas de framework, pas de bibliothèque à installer. Les graphiques que vous avez
vus sont du SVG généré à la main, les filtres dynamiques du JavaScript natif.

Côté sécurité : contrôle d'accès à deux niveaux — par rôle **et** par filiale, un préparateur ne peut
strictement pas accéder aux données d'une autre filiale ; protection CSRF sur tous les formulaires ; et le
journal d'audit que vous venez de voir.

Le tout couvert par une suite de tests automatisés sur la logique de calcul financier.

Merci de votre attention. »

---

# PARTIE 3 — CHECKLIST FINALE

Avant de lancer l'enregistrement :

- [ ] Base réinitialisée (§A)
- [ ] **5 filiales validées hors caméra, Maroc laissé en brouillon** (§B)
- [ ] Aucun run de consolidation existant
- [ ] Deux sessions ouvertes (préparateur Maroc / contrôleur Maroc)
- [ ] Zoom 100 %, plein écran, favoris masqués
- [ ] Notifications système coupées
- [ ] Micro testé

À dire absolument, même si tu improvises :

- [ ] Le mot **« consolidation »** expliqué simplement (éliminer les opérations internes)
- [ ] **Multi-devises** et la règle taux moyen / taux de clôture
- [ ] **OHADA** = norme comptable de 17 pays africains
- [ ] Le contrôle d'équilibre du bilan **bloquant**
- [ ] Les **7 étapes tracées** du moteur
- [ ] **Zéro dépendance** + RBAC + audit

À ne PAS faire :

- [ ] Ne pas ouvrir **Liasse groupe** avant d'avoir lancé la consolidation (écran vide)
- [ ] Ne pas valider le Maroc avant l'enregistrement
- [ ] Ne pas lire le script mot à mot — garde le ton naturel
