-- Création du type pour les rôles
-- Bloc de sécurité pour le type ENUM (évite l'erreur si déjà existant)
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
        CREATE TYPE user_role AS ENUM ('admin', 'user_1', 'user_2');
    END IF;
END $$;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password TEXT NOT NULL,
    "role" user_role NOT NULL DEFAULT 'user_1'
);

-- Table des stocks
CREATE TABLE IF NOT EXISTS stocks (
    id SERIAL PRIMARY KEY,
    nom_produit VARCHAR(255) NOT NULL,
    quantite_disponible INT DEFAULT 0,
    prix_vente DECIMAL(10,2) DEFAULT 0.00,
    seuil_alerte INT DEFAULT 5,
    actif BOOLEAN DEFAULT TRUE
);

-- Table des clients mise à jour
CREATE TABLE IF NOT EXISTS clients (
    id SERIAL PRIMARY KEY,
    nom_prenom VARCHAR(255) NOT NULL,
    sexe CHAR(1),
    age INT,
    telephone VARCHAR(50),
    ville VARCHAR(100),
    taille_cm INT,
    poids_actuel DECIMAL(5,2),
    poids_objectif DECIMAL(5,2),
    alimentation TEXT,
    activite_physique DECIMAL(3,1),
    antecedents_medicaux TEXT,
    allergies_intolerances TEXT,
    operations_chirurgicales TEXT,
    nombre_accouchements INT DEFAULT 0,
    allaitement VARCHAR(10),
    prix_cure DECIMAL(10,2) DEFAULT 30.00,
    date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cree_par VARCHAR(50),
    produit_id INT REFERENCES stocks(id) ON DELETE SET NULL,
    stock_deduit BOOLEAN DEFAULT FALSE
);

-- Table de liaison Client ↔ Produits (Relation N:N)
-- Permet d'associer plusieurs cures/produits à un même client
CREATE TABLE IF NOT EXISTS client_produits (
    id SERIAL PRIMARY KEY,
    client_id INT REFERENCES clients(id) ON DELETE CASCADE NOT NULL,
    produit_id INT REFERENCES stocks(id) ON DELETE CASCADE NOT NULL,
    UNIQUE(client_id, produit_id)
);

-- Table pour le suivi de l'évolution (Historique)
-- Permet d'enregistrer les mesures corporelles à chaque consultation
CREATE TABLE IF NOT EXISTS suivi_progression (
    id SERIAL PRIMARY KEY,
    client_id INT REFERENCES clients(id) ON DELETE CASCADE,
    -- Mesures corporelles
    poids_mesure DECIMAL(5,2),
    taille_mesure INT,
    tour_taille DECIMAL(5,1),
    tour_hanches DECIMAL(5,1),
    imc DECIMAL(4,1),
    masse_graisseuse DECIMAL(4,1),
    masse_musculaire DECIMAL(4,1),
    -- Évaluations
    tension_arterielle VARCHAR(20),
    note_activite DECIMAL(3,1),
    notes_alimentation TEXT,
    notes_suivi TEXT DEFAULT '',
    -- Métadonnées
    date_consultation DATE DEFAULT CURRENT_DATE,
    enregistre_par VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table d'historique des mouvements de stock
CREATE TABLE IF NOT EXISTS stock_history (
    id SERIAL PRIMARY KEY,
    produit_id INT REFERENCES stocks(id) ON DELETE CASCADE,
    quantite INT,
    execute_par VARCHAR(50),
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des champs personnalisés dynamiques du formulaire client
-- Permet d'ajouter / archiver des rubriques sans modifier le schéma
CREATE TABLE IF NOT EXISTS form_fields (
    id SERIAL PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    field_type VARCHAR(50) NOT NULL DEFAULT 'text', -- text | number | textarea | select | date
    options_json TEXT DEFAULT NULL,                  -- JSON pour les listes de choix
    placeholder VARCHAR(255) DEFAULT NULL,
    actif BOOLEAN DEFAULT TRUE,
    ordre INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Valeurs des champs personnalisés par client
CREATE TABLE IF NOT EXISTS client_custom_values (
    id SERIAL PRIMARY KEY,
    client_id INT REFERENCES clients(id) ON DELETE CASCADE NOT NULL,
    field_id  INT REFERENCES form_fields(id) ON DELETE CASCADE NOT NULL,
    valeur TEXT DEFAULT NULL,
    UNIQUE(client_id, field_id)                      -- une seule valeur par client/champ
);

-- Index pour les performances sur les recherches fréquentes
CREATE INDEX IF NOT EXISTS idx_clients_nom ON clients(nom_prenom);
CREATE INDEX IF NOT EXISTS idx_clients_ville ON clients(ville);
CREATE INDEX IF NOT EXISTS idx_clients_date ON clients(date_enregistrement);
CREATE INDEX IF NOT EXISTS idx_client_produits_client ON client_produits(client_id);
CREATE INDEX IF NOT EXISTS idx_client_produits_produit ON client_produits(produit_id);
CREATE INDEX IF NOT EXISTS idx_suivi_client ON suivi_progression(client_id);
CREATE INDEX IF NOT EXISTS idx_stock_history_produit ON stock_history(produit_id);
CREATE INDEX IF NOT EXISTS idx_custom_values_client ON client_custom_values(client_id);

-- Données initiales : utilisateurs par défaut (mot de passe = "athena123" pour tous)
INSERT INTO users (username, password, "role") VALUES
    ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
    ('poste_1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user_1'),
    ('poste_2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user_2')
ON CONFLICT (username) DO NOTHING;
