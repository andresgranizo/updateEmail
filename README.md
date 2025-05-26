# Formulario de Actualización de Correo - Segundo Periodo 2025

Este proyecto es un formulario Laravel que permite la actualización del correo electrónico de los ciudadanos para el proceso del Segundo Periodo 2025, en conformidad con la Ley Orgánica de Protección de Datos Personales.

---

## 🧰 Requisitos

- PHP >= 8.0
- Composer
- Postgres

---

## 🚀 Instalación y ejecución local

```bash
git clone https://github.com/usuario/formulario-correo-2025.git
cd formulario-correo-2025

composer install

cp .env.example .env
php artisan key:generate

# Edita .env con tus datos de base de datos

php artisan migrate
php artisan serve

#RollBack a la base de datos y encerar
php artisan migrate:fresh
```

Accede a: http://127.0.0.1:8000

---

## 📦 Despliegue en servidor (Apache/NGINX)

### 1. Subir archivos al servidor (sin la carpeta `vendor`)
```bash
scp -r * usuario@IP:/ruta/proyecto
```

### 2. Conectarse al servidor y ejecutar:

```bash
cd /ruta/proyecto

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Editar .env para conexión real
php artisan migrate

php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data .
```

---

## 🛡️ Seguridad

- Verifica que `DocumentRoot` apunte a `/public`
- Asegúrate de no exponer `.env`, `/vendor`, `composer.lock`, etc.

---

## 📜 Licencia

Proyecto institucional para SENESCYT — uso educativo e institucional.
# updateEmail
