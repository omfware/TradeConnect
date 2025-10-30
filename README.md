Este documento explica paso a paso como se ejecuto el sistema TradeConnect
Dentro de un contenedor Docker, en una maquina virtual AlmaLinux 8.10 montada 
en Proxmox.

El objetivo es que cualquier persona pueda reproducir este entorno sin conocimientos
previos.

¿Que se logra? 
-La pagina web de TradeConnect se ejecuta en un servidor web (Apache+PHP)
-La base de datos funciona en un contenedor aparte (MariaDB)
-Ambos servicios se comunican entre si automaticamente
-Si el servidor se apaga y se vuelve a encender, la base de datos no se pierde

para eso hay que seguir los siguientes pasos:

1) Transferir el proyecto al servidor:
   -Primero, desde windwos se una WinSCP (programa para transferir archivos) donde ponemos
   la ip de del servidor con su usuario y contraseña
   -La carpeta TradeConnect/  la colocamos en /home/uits que es el codigo fuente de la web
   -plataformaoficios.sql  tambien la colocamos en /home/uits/

   2) Instalar Docker si no esta instalado en Almalinux
      Docker es la herramienta que permite crear contenedores
      dnf -y install dnf-plugins-core
      dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
       dnf -y install docker-ce docker-ce-cli containerd.io docker-compose-plugin
      luego de instalar docker lo dejamos activo todo el tiempo 
systemctl enable --now docker
usermod -aG docker uits

3)Crear la estructura del proyecto en el servidor
cd /home/uits
mkdir -p proyecto/{php,src,initdb}
cp -r /home/uits/TradeConnect/* /home/uits/proyecto/src/
mv /home/uits/plataformaoficios.sql /home/uits/proyecto/initdb/

src:Es donde esta el codigo de la web, es lo que se mostrara en el navegador
php:Configuracion del servidor , es para construir el contenedor web
initdb: es la base de datos .sql que se importa automaticamente al iniciar el contenedor de BD

4) Archivo .env (Guarda las contraseñas)
     cd /home/uits/proyecto
    Sudo nano .env
   MARIADB_ROOT_PASSWORD=RootPassSegura123
MARIADB_DATABASE=plataformaoficios
MARIADB_USER=uits
MARIADB_PASSWORD=UsuarioITS
Esto se usa para no escribir contraseñas dentro del codigo, es mas seguro

5)Crear la imagen del servidor
Sudo nano php/Dockerfile
FROM php:8.2-apache
# Extensiones que usa tu app
RUN docker-php-ext-install mysqli pdo pdo_mysql
# Ajustes de Apache
RUN a2enmod rewrite
-Crea un servidor Apache con PHP listo para la web
-Activa mysqli y pdo_mysql, que la web usa para conectarse a la BD

6) Crear el archivo que indica como se conectan los contenedores
   nano docker-compose.yml
   version: "3.9"

services:
  app:
    build: ./php
    container_name: php-apache
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "8080:80"
    environment:
      DB_HOST: db
      DB_NAME: ${MARIADB_DATABASE}
      DB_USER: ${MARIADB_USER}
      DB_PASS: ${MARIADB_PASSWORD}
    volumes:
      - ./src:/var/www/html:z

  db:
    image: mariadb:11
    container_name: mariadb
    restart: unless-stopped
    environment:
      MARIADB_ROOT_PASSWORD: ${MARIADB_ROOT_PASSWORD}
      MARIADB_DATABASE: ${MARIADB_DATABASE}
      MARIADB_USER: ${MARIADB_USER}
      MARIADB_PASSWORD: ${MARIADB_PASSWORD}
    volumes:
      - mariadb_data:/var/lib/mysql:z
      - ./initdb:/docker-entrypoint-initdb.d:ro,z

volumes:
  mariadb_data:

7) Conectar PHP con la base de datos usando variables de entorno
nano /home/uits/proyecto/src/conexión.php

Adaptar a esto:
<?php
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'plataformaoficios';
$user = getenv('DB_USER') ?: 'uits';
$pass = getenv('DB_PASS') ?: 'UsuarioITS';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($host, $user, $pass, $db, 3306);
$mysqli->set_charset('utf8mb4');

/* Alias por si otros scripts usan $conn o $con */
$conn = $mysqli;
$con  = $mysqli; 
?>

**Importante:Si en tus consultas usabas nombres de tablas con mayuscula (p.ej.Categoria
) y tu dump las trae en minuscula (categoria), corregi en el codigo los nombres en 
minuscula o renombra las tablas dentro de MariaDB (Liux distingue mayusculas/minusculas)**

8) Encender los contenedores
   cd /home/uits/proyecto
docker compose up -d --build
docker compose ps

9)acceder a la web
http://Ip del servidor:8080/

   

      
