-- phpMyAdmin SQL Dump
-- version 3.2.0.1
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Dim 22 Novembre 2009 à 12:43
-- Version du serveur: 5.1.36
-- Version de PHP: 5.3.0

--
-- Base de données: 'tdbmunittest'
--

-- --------------------------------------------------------

--
-- Structure de la table 'roles'
--

CREATE TABLE roles (
  id int4 NOT NULL,
  name varchar(255) NOT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Structure de la table 'users'
--

CREATE TABLE users (
  id int4 NOT NULL,
  login varchar(255) NOT NULL,
  password varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Structure de la table 'users_roles'
--

CREATE TABLE users_roles (
  id int4 NOT NULL,
  user_id int4 NOT NULL,
  role_id int4 NOT NULL,
  PRIMARY KEY (id)
);

--
-- Contraintes pour les tables exportées
--

--
-- Contraintes pour la table users_roles
--
ALTER TABLE users_roles
  ADD CONSTRAINT users_roles_ibfk_2 FOREIGN KEY (role_id) REFERENCES roles (id),
  ADD CONSTRAINT users_roles_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id);
