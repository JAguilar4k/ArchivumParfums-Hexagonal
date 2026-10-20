<?php

declare(strict_types=1);

return [

    'database_failed' => 'No se pudo preparar la base de datos: :message',
    'database_hint' => 'Revise que PostgreSQL 18 esté instalado y encendido y los datos DB_ de .env; luego ejecute de nuevo composer install.',
    'description' => 'Deja listo el entorno local: .env, clave de la aplicación, roles y bases de PostgreSQL, migraciones y datos de demostración.',
    'environment_file_created' => 'Se creó .env a partir de .env.example.',
    'existing_role_password' => 'El rol de PostgreSQL ya existe. Escriba su contraseña actual en :variable de .env; el instalador no cambia las contraseñas de otras instalaciones.',
    'migration_failed' => 'Las migraciones fallaron. Corrija el error y ejecute de nuevo archivum:install.',
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
