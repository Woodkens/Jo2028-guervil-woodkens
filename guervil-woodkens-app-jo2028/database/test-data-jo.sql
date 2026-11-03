-- Suppression des données existantes dans les tables
DELETE FROM PARTICIPER;
DELETE FROM EPREUVE;
DELETE FROM ATHLETE;
DELETE FROM PAYS;
DELETE FROM GENRE;
DELETE FROM LIEU;
DELETE FROM SPORT;
DELETE FROM ADMINISTRATEUR;

-- Réinitialisation des séquences d'identité pour les tables avec des colonnes ID
ALTER TABLE PAYS AUTO_INCREMENT=1;
ALTER TABLE GENRE AUTO_INCREMENT=1;
ALTER TABLE LIEU AUTO_INCREMENT=1;
ALTER TABLE SPORT AUTO_INCREMENT=1;
ALTER TABLE ATHLETE AUTO_INCREMENT=1;
ALTER TABLE EPREUVE AUTO_INCREMENT=1;
ALTER TABLE ADMINISTRATEUR AUTO_INCREMENT=1;

-- Insertion des données dans la table PAYS
INSERT INTO PAYS (id_pays, nom_pays) VALUES
    (1, 'France'),    -- Europe
    (2, 'Algérie'),    -- Afrique
    (3, 'Maroc'),      -- Afrique
    (4, 'Tunisie'),    -- Afrique
    (5, 'Brésil'),     -- Amérique du Sud
    (6, 'Australie'),  -- Océanie
    (7, 'Canada'),     -- Amérique du Nord
    (8, 'Inde'),       -- Asie
    (9, 'Chine'),      -- Asie
    (10, 'États-Unis'); -- Amérique du Nord

-- Insertion des données dans la table GENRE
INSERT INTO GENRE (id_genre, nom_genre) VALUES
    (1, 'Homme'),
    (2, 'Femme');

-- Insertion des données dans la table LIEU
INSERT INTO LIEU (id_lieu, nom_lieu, adresse_lieu, cp_lieu, ville_lieu) VALUES
    (1, 'Los Angeles Memorial Coliseum', '3911 S Figueroa St', '90037', 'Los Angeles'),
    (2, 'Crypto.com Arena (DTLA Arena)', '1111 S Figueroa St', '90015', 'Los Angeles'),
    (3, 'SoFi Stadium', '1001 Stadium Dr', '90301', 'Inglewood'),
    (4, 'Carson Velodrome', '18400 Avalon Blvd', '90746', 'Carson'),
    (5, 'Long Beach Climbing Theater', '300 E Ocean Blvd', '90802', 'Long Beach');

-- Insertion des données dans la table SPORT
INSERT INTO SPORT (id_sport, nom_sport) VALUES
    (1, '100m'),
    (2, 'Saut en hauteur'),
    (3, 'Natation'),
    (4, 'Cyclisme'),
    (5, 'Lancer'),
    (6, 'Saut en longueur'),
    (7, 'Gymnastique'),
    (8, 'VTT'),
    (9, 'Boxe'),
    (10, 'Escalade');

-- Insertion des données dans la table ATHLETE
INSERT INTO ATHLETE (id_athlete, nom_athlete, prenom_athlete, id_pays, id_genre) VALUES
    (1, 'MARTIN', 'Antoine', 1, 1),
    (2, 'LARBI', 'Ahmed', 2, 1),
    (3, 'BENACER', 'Fatima', 3, 2),
    (4, 'BEN YOUSSEF', 'Karim', 4, 1),
    (5, 'SILVA', 'Carlos', 5, 1),
    (6, 'JOHNSON', 'Emily', 6, 2),
    (7, 'GONZALES', 'Javier', 7, 1),
    (8, 'KUMAR', 'Raj', 8, 1),
    (9, 'WANG', 'Li', 9, 2),
    (10, 'SMITH', 'John', 10, 1);

-- Insertion des données dans la table EPREUVE
INSERT INTO EPREUVE (id_epreuve, date_epreuve, heure_epreuve, id_lieu, id_sport) VALUES
    (1, '2028-07-20', '14:30', 1, 1),
    (2, '2028-07-21', '10:00', 2, 2),
    (3, '2028-07-22', '15:45', 3, 3),
    (4, '2028-07-23', '09:15', 4, 4),
    (5, '2028-07-24', '14:45', 5, 5),
    (6, '2028-07-25', '11:30', 1, 6),
    (7, '2028-07-26', '16:15', 2, 7),
    (8, '2028-07-27', '10:30', 3, 8),
    (9, '2028-07-28', '15:00', 4, 9),
    (10, '2028-07-29', '09:45', 5, 10);

-- Insertion des données dans la table PARTICIPER
INSERT INTO PARTICIPER (id_athlete, id_epreuve, resultat) VALUES
    (1, 1, '10.5'),
    (2, 1, '11.2'),
    (3, 2, '1.85'),
    (4, 3, '2:05.3'),
    (5, 5, '14.3'),
    (6, 6, '7.2'),
    (7, 7, '15.5'),
    (8, 8, '1:30:45'),
    (9, 9, 'Vainqueur'),
    (10, 10, '5.8');

-- Insertion des données dans la table ADMINISTRATEUR
INSERT INTO ADMINISTRATEUR (id_admin, nom_admin, prenom_admin, login, password) VALUES
    (1, 'Admin', 'Super', 'admin', '$2y$10$WFxymbZ/gV2XfGy1We2bB.NZ9owdEU5QKUFWAicOY7qayhbe93ACm'),
    (2, 'User', 'John', 'john_doe', '$2y$10$VSdvPWt4OQnuQdT2vrP1z.5PzBJ5FuJc/bJhIFL8TB2AP99u3h8cO'),
    (3, 'User', 'Jane', 'jane_doe', '$2y$10$xP/2LE33Hy./Je/CLqLyL.8KJFWgXsHXcaln/usfr8Vv6INtCKIoO');