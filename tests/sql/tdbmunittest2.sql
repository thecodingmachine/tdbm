
-- 
-- Structure de la table `departements`
-- 

CREATE TABLE IF NOT EXISTS `departements` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`id_region` int(11) NOT NULL,
`numero` varchar(3) NOT NULL,
`nom` varchar(50) NOT NULL,
`nom_web` varchar(50) NOT NULL,
PRIMARY KEY (`id`),
KEY `id_region` (`id_region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ; 


-- 
-- Contenu de la table `departements`
-- 

INSERT INTO `departements` (`id`, `id_region`, `numero`, `nom`, `nom_web`) VALUES
(1, 22, '01', 'Ain', 'Ain'),
(2, 20, '02', 'Aisne', 'Aisne'),
(3, 3, '03', 'Allier', 'Allier'),
(4, 18, '04', 'Alpes de Haute Provence', 'Alpes de Haute Provence'),
(5, 18, '05', 'Hautes Alpes', 'Hautes Alpes'),
(6, 18, '06', 'Alpes Maritimes', 'Alpes Maritimes'),
(7, 22, '07', 'Ardèche', 'Ard&egrave;che'),
(8, 8, '08', 'Ardennes', 'Ardennes'),
(9, 16, '09', 'Ariège', 'Ari&egrave;ge'),
(10, 8, '10', 'Aube', 'Aube'),
(11, 13, '11', 'Aude', 'Aude'),
(12, 16, '12', 'Aveyron', 'Aveyron'),
(13, 18, '13', 'Bouches du Rhône', 'Bouches du Rh&ocirc;ne'),
(14, 4, '14', 'Calvados', 'Calvados'),
(15, 3, '15', 'Cantal', 'Cantal'),
(16, 21, '16', 'Charente', 'Charente'),
(17, 21, '17', 'Charente Maritime', 'Charente Maritime'),
(18, 7, '18', 'Cher', 'Cher'),
(19, 14, '19', 'Corrèze', 'Corr&egrave;ze'),
(20, 9, '20', 'Corse', 'Corse'),
(21, 5, '21', 'Côte d''or', 'C&ocirc;te d''or'),
(22, 6, '22', 'Côtes d''Armor', 'C&ocirc;tes d''Armor'),
(23, 14, '23', 'Creuse', 'Creuse'),
(24, 2, '24', 'Dordogne', 'Dordogne'),
(25, 10, '25', 'Doubs', 'Doubs'),
(26, 22, '26', 'Drôme', 'Dr&ocirc;me'),
(27, 11, '27', 'Eure', 'Eure'),
(28, 7, '28', 'Eure et Loir', 'Eure et Loir'),
(29, 6, '29', 'Finistère', 'Finist&egrave;re'),
(30, 13, '30', 'Gard', 'Gard'),
(31, 16, '31', 'Haute Garonne', 'Haute Garonne'),
(32, 16, '32', 'Gers', 'Gers'),
(33, 2, '33', 'Gironde', 'Gironde'),
(34, 13, '34', 'Hérault', 'H&eacute;rault'),
(35, 6, '35', 'Ille et Vilaine', 'Ille et Vilaine'),
(36, 7, '36', 'Indre', 'Indre'),
(37, 7, '37', 'Indre et Loire', 'Indre et Loire'),
(38, 22, '38', 'Isère', 'Is&egrave;re'),
(39, 10, '39', 'Jura', 'Jura'),
(40, 2, '40', 'Landes', 'Landes'),
(41, 7, '41', 'Loir et Cher', 'Loir et Cher'),
(42, 22, '42', 'Loire', 'Loire'),
(43, 3, '43', 'Haute Loire', 'Haute Loire'),
(44, 19, '44', 'Loire Atlantique', 'Loire Atlantique'),
(45, 7, '45', 'Loiret', 'Loiret'),
(46, 16, '46', 'Lot', 'Lot'),
(47, 2, '47', 'Lot et Garonne', 'Lot et Garonne'),
(48, 13, '48', 'Lozère', 'Loz&egrave;re'),
(49, 19, '49', 'Maine et Loire', 'Maine et Loire'),
(50, 4, '50', 'Manche', 'Manche'),
(51, 8, '51', 'Marne', 'Marne'),
(52, 8, '52', 'Haute Marne', 'Haute Marne'),
(53, 19, '53', 'Mayenne', 'Mayenne'),
(54, 15, '54', 'Meurthe et Moselle', 'Meurthe et Moselle'),
(55, 15, '55', 'Meuse', 'Meuse'),
(56, 6, '56', 'Morbihan', 'Morbihan'),
(57, 15, '57', 'Moselle', 'Moselle'),
(58, 5, '58', 'Nièvre', 'Ni&egrave;vre'),
(59, 17, '59', 'Nord', 'Nord'),
(60, 20, '60', 'Oise', 'Oise'),
(61, 4, '61', 'Orne', 'Orne'),
(62, 17, '62', 'Pas de Calais', 'Pas de Calais'),
(63, 3, '63', 'Puy de dôme', 'Puy de d&ocirc;me'),
(64, 2, '64', 'Pyrénées-Atlantiques', 'Pyr&eacute;n&eacute;es-Atlantiques'),
(65, 16, '65', 'Hautes Pyrénées', 'Hautes Pyr&eacute;n&eacute;es'),
(66, 13, '66', 'Pyrénées Orientales', 'Pyr&eacute;n&eacute;es Orientales'),
(67, 1, '67', 'Bas Rhin', 'Bas Rhin'),
(68, 1, '68', 'Haut Rhin', 'Haut Rhin'),
(69, 22, '69', 'Rhône', 'Rh&ocirc;ne'),
(70, 10, '70', 'Haute Saône', 'Haute Sa&ocirc;ne'),
(71, 5, '71', 'Saône et Loire', 'Sa&ocirc;ne et Loire'),
(72, 19, '72', 'Sarthe', 'Sarthe'),
(73, 22, '73', 'Savoie', 'Savoie'),
(74, 22, '74', 'Haute Savoie', 'Haute Savoie'),
(75, 12, '75', 'Paris', 'Paris'),
(76, 11, '76', 'Seine Maritime', 'Seine Maritime'),
(77, 12, '77', 'Seine et Marne', 'Seine et Marne'),
(78, 12, '78', 'Yvelines', 'Yvelines'),
(79, 21, '79', 'Deux-Sèvres', 'Deux-S&egrave;vres'),
(80, 20, '80', 'Somme', 'Somme'),
(81, 16, '81', 'Tarn', 'Tarn'),
(82, 16, '82', 'Tarn et Garonne', 'Tarn et Garonne'),
(83, 18, '83', 'Var', 'Var'),
(84, 18, '84', 'Vaucluse', 'Vaucluse'),
(85, 19, '85', 'Vendée', 'Vend&eacute;e'),
(86, 21, '86', 'Vienne', 'Vienne'),
(87, 14, '87', 'Haute-Vienne', 'Haute-Vienne'),
(88, 15, '88', 'Vosges', 'Vosges'),
(89, 5, '89', 'Yonne', 'Yonne'),
(90, 10, '90', 'Territoire de Belfort', 'Territoire de Belfort'),
(91, 12, '91', 'Essonne', 'Essonne'),
(92, 12, '92', 'Hauts de Seine', 'Hauts de Seine'),
(93, 12, '93', 'Seine Saint Denis', 'Seine Saint Denis'),
(94, 12, '94', 'Val de Marne', 'Val de Marne'),
(95, 12, '95', 'Val d''Oise', 'Val d''Oise'); 


-- 
-- Structure de la table `entites`
-- 

CREATE TABLE IF NOT EXISTS `entites` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`id_type_entite` int(11) NOT NULL,
`id_type_form_entite` int(11) DEFAULT NULL,
`id_departement` int(11) NOT NULL,
`id_activite` int(11) NOT NULL,
`id_bareme` int(11) DEFAULT NULL,
`id_parent_entite` int(11) DEFAULT NULL,
`id_college` int(11) DEFAULT NULL,
`id_regroupement_seg` int(11) DEFAULT NULL,
`actif` tinyint(1) NOT NULL DEFAULT '1',
`reference_interne` varchar(10) NOT NULL,
`representant` varchar(255) DEFAULT NULL,
`signataire` varchar(250) NOT NULL,
`qualite_representant` varchar(150) DEFAULT NULL,
`code_hlm` varchar(10) DEFAULT NULL,
`code_groupe` varchar(3) DEFAULT NULL,
`denomination` varchar(70) DEFAULT NULL,
`appellation` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
`forme_entite` varchar(100) DEFAULT NULL,
`raison_sociale` varchar(40) DEFAULT NULL,
`adresse1` varchar(70) DEFAULT NULL,
`adresse2` varchar(70) DEFAULT NULL,
`code_postal` varchar(5) DEFAULT NULL,
`ville` varchar(50) DEFAULT NULL,
`telephone` varchar(14) DEFAULT NULL,
`fax` varchar(14) DEFAULT NULL,
`email` varchar(50) DEFAULT NULL,
`site_web` varchar(60) DEFAULT NULL,
`capital` double DEFAULT NULL,
`rcs` varchar(100) NOT NULL DEFAULT '',
`droit_tirage` double DEFAULT NULL,
`avancement_rapide` tinyint(1) DEFAULT NULL,
`date_debut` date DEFAULT NULL,
`date_fin` date DEFAULT NULL,
`mode_reglement` varchar(3) NOT NULL DEFAULT '',
`acompte` varchar(3) DEFAULT 'N',
`retenue` varchar(3) DEFAULT 'N',
`retenue_duree` int(2) DEFAULT NULL,
`sous_traitant` int(1) DEFAULT '1',
`college` varchar(1) DEFAULT NULL,
`module_import_dossier` tinyint(1) DEFAULT NULL,
`module_documents_en_ligne` tinyint(1) DEFAULT NULL,
`module_bilan_ft` tinyint(1) DEFAULT NULL,
`icone` varchar(300) DEFAULT NULL,
`note` text,
`mandatory` tinyint(1) NOT NULL DEFAULT '0',
`fond_de_garantie` double DEFAULT NULL,
`parts_souscrites` double DEFAULT NULL,
`encours_mobilise` double NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
KEY `id_type_entite` (`id_type_entite`),
KEY `id_type_form_entite` (`id_type_form_entite`),
KEY `id_departement` (`id_departement`),
KEY `id_activite` (`id_activite`),
KEY `id_bareme` (`id_bareme`),
KEY `id_parent_entite` (`id_parent_entite`),
KEY `reference_interne` (`reference_interne`),
KEY `id_college` (`id_college`),
KEY `id_regroupement_seg` (`id_regroupement_seg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=896 ;




CREATE TABLE IF NOT EXISTS `utilisateur_entite` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`id_entite` int(11) NOT NULL,
`id_utilisateur` int(11) NOT NULL,
`id_type_poste` int(11) DEFAULT NULL,
`email` varchar(300) DEFAULT NULL,
`telephone` varchar(30) DEFAULT NULL,
`date_employe` datetime DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `id_type_poste` (`id_type_poste`),
KEY `id_entite` (`id_entite`),
KEY `id_utilisateur` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=19 ; 




-- 
-- Contraintes pour la table `entites`
-- 
ALTER TABLE `entites`
ADD CONSTRAINT `entites_ibfk_5` FOREIGN KEY (`id_departement`) REFERENCES `departements` (`id`);

-- 
-- Contraintes pour la table `utilisateur_entite`
-- 
ALTER TABLE `utilisateur_entite`
ADD CONSTRAINT `utilisateur_entite_ibfk_4` FOREIGN KEY (`id_entite`) REFERENCES `entites` (`id`);
