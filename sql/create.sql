CREATE TABLE clients (
     id MEDIUMINT NOT NULL AUTO_INCREMENT,
     shop CHAR(50) UNIQUE NOT NULL,
	api_key CHAR(50) NOT NULL,
	shopify_access_key CHAR(50) NOT NULL,
     PRIMARY KEY (id)
);

CREATE USER 'dbsync'@'localhost' IDENTIFIED  WITH mysql_native_password BY 'q86M#5Hm';
GRANT INSERT ON shopify.clients TO 'dbsync'@'localhost' WITH GRANT OPTION;
GRANT SELECT ON shopify.clients TO 'dbsync'@'localhost' WITH GRANT OPTION;