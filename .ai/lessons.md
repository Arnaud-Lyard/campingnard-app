# Lessons

## Doctrine migrations

- **Ne jamais réécrire une migration déjà exécutée pour la corriger.** Doctrine ne rejoue pas une version
  déjà présente dans `doctrine_migration_versions` : modifier son `up()` n'a aucun effet sur la base.
  → Toujours créer une **nouvelle** migration (timestamp ultérieur) qui applique le diff manquant
  (`ALTER TABLE ... ADD COLUMN ...`).
  Contexte : la migration stub `Version20260622021100` (table `reset_password_request` avec `id` seul)
  avait déjà été migrée ; l'avoir réécrite n'a rien changé → erreur runtime
  `column "expires_at" does not exist`. Corrigé via `Version20260622120000`.
- Avant de réécrire un fichier de migration, vérifier s'il a déjà été exécuté
  (`php bin/console doctrine:migrations:list`). S'il l'est, faire une nouvelle migration.
