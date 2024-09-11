CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    feed_url VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    error_count INT DEFAULT 0,
    last_error_check TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    publication_date DATETIME NOT NULL,
    link VARCHAR(255) NOT NULL,
    unique_identifier VARCHAR(255) NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE FULLTEXT INDEX idx_title_fulltext ON articles (title);