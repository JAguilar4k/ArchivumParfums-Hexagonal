<?php

declare(strict_types=1);

return [

    'catalog_loaded' => 'Catálogo de ArchivumParfums: :created perfumes nuevos y :photos fotos asignadas. Los perfumes que ya estaban se conservan.',
    'catalog_without_photos' => 'R2_PUBLIC_URL está vacío en .env, así que los perfumes quedaron sin foto. Configúrelo y ejecute de nuevo instalar.bat para completarlas.',
    'database_failed' => 'No se pudo preparar la base de datos: :message',
    'database_hint' => 'Revise que PostgreSQL 18 esté instalado y encendido y los datos DB_ de .env; luego ejecute de nuevo composer install.',
    'description' => 'Deja listo el entorno local: .env, clave de la aplicación, roles y bases de PostgreSQL, migraciones y datos de demostración.',
    'environment_file_created' => 'Se creó .env a partir de .env.example.',
    'existing_role_password' => 'El rol de PostgreSQL ya existe. Escriba su contraseña actual en :variable de .env; el instalador no cambia las contraseñas de otras instalaciones.',
    'migrations_missing' => 'No se encontraron migraciones. Ejecute git submodule update --init --recursive desde la raíz del repositorio y vuelva a ejecutar archivum:install.',
    'migration_failed' => 'Las migraciones fallaron. Corrija el error y ejecute de nuevo archivum:install.',
    'postgres_version' => 'El servidor es PostgreSQL :found y el proyecto necesita PostgreSQL :required o superior. Instale PostgreSQL :required, apunte DB_HOST y DB_PORT de .env a ese servidor y ejecute de nuevo la instalación.',
    'query_failed' => 'Falló una operación de PostgreSQL. Revise los permisos del rol y los datos DB_ de .env.',
    'query_failed_with_state' => 'Falló una operación de PostgreSQL (SQLSTATE :sqlstate). Revise los permisos del rol y los datos DB_ de .env.',
    'ready' => 'El entorno quedó listo para trabajar.',
    'roles_ready' => 'Los roles :owner y :application y las dos bases de datos quedaron listos; sus contraseñas están en .env.',
    'seeding_failed' => 'Los datos de demostración no se completaron. Corrija el error y ejecute de nuevo archivum:install.',
    'skipped_environment' => 'APP_ENV es :environment y no local: archivum:install no toca la base de datos.',
    'skipped_outside_development' => 'No es una instalación de desarrollo: archivum:install no hace nada.',
    'superuser_needed' => 'Para crear los roles y las bases de datos hace falta la contraseña del superusuario :user de PostgreSQL. Ejecute php artisan archivum:install en la terminal y escríbala cuando la pida; no se guarda.',
    'superuser_password_prompt' => 'Contraseña del superusuario :user de PostgreSQL (no se guarda)',
    'superuser_reason' => 'Hay que crear o actualizar los roles y las bases de datos con el superusuario :user de PostgreSQL.',

];
