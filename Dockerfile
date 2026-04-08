FROM php:8.2-apache

# 1. Instala extensões SQLite
RUN apt-get update && apt-get install -y libsqlite3-dev && docker-php-ext-install pdo_sqlite

# 2. Muda a porta do Apache de 80 para 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

# 3. Copia os arquivos
COPY . /var/www/html/

# 4. LIBERA GERAL: O OpenShift usa usuários aleatórios, então precisamos
# que o grupo root (que é o padrão do OpenShift) tenha acesso às pastas
RUN chown -R www-data:root /var/www/html && \
    chmod -R g+w /var/www/html && \
    chown -R www-data:root /var/lock/apache2 /var/run/apache2 /var/log/apache2 && \
    chmod -R g+w /var/lock/apache2 /var/run/apache2 /var/log/apache2

# Informa ao OpenShift que a porta agora é 8080
EXPOSE 8080

# Roda o Apache em primeiro plano
CMD ["apache2-foreground"]