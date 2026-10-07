-- Runs once, when the MySQL volume is created: the Laravel app's database and the test database.
-- (The legacy site's "app" database is created by MYSQL_DATABASE in docker-compose.yml.)
CREATE DATABASE IF NOT EXISTS kai CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS kai_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON kai.* TO 'app'@'%';
GRANT ALL PRIVILEGES ON kai_test.* TO 'app'@'%';
