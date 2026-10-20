<?php

declare(strict_types=1);

return [

    'database_failed' => 'The database could not be prepared: :message',
    'database_hint' => 'Check that PostgreSQL 18 is installed and running and the DB_ values in .env, then run composer install again.',
    'description' => 'Prepare the local environment: .env, application key, PostgreSQL roles and databases, migrations and demo data.',
    'environment_file_created' => 'Created .env from .env.example.',
    'existing_role_password' => 'The PostgreSQL role already exists. Set its current password in :variable in .env; the installer does not change credentials used by another installation.',
    'migration_failed' => 'Migrations failed. Fix the error and run archivum:install again.',
    'query_failed' => 'A PostgreSQL operation failed. Check the role permissions and the DB_ values in .env.',
    'query_failed_with_state' => 'A PostgreSQL operation failed (SQLSTATE :sqlstate). Check the role permissions and the DB_ values in .env.',
    'ready' => 'The environment is ready to work.',
    'roles_ready' => 'The roles :owner and :application and both databases are ready; their passwords are in .env.',
    'seeding_failed' => 'Demo data could not be completed. Fix the error and run archivum:install again.',
    'skipped_environment' => 'APP_ENV is :environment, not local: archivum:install leaves the database alone.',
    'skipped_outside_development' => 'This is not a development install: archivum:install does nothing.',
    'superuser_needed' => 'Creating the roles and databases needs the password of the PostgreSQL superuser :user. Run php artisan archivum:install in the terminal and type it when asked; it is not saved.',
    'superuser_password_prompt' => 'Password of the PostgreSQL superuser :user (not saved)',
    'superuser_reason' => 'The roles and databases must be created or updated with the PostgreSQL superuser :user.',

];
