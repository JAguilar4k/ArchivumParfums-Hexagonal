<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Upgrade accounts already created by the original migration without discarding accounts, sessions or reset
     * tokens. Keep the numeric identity and its sequence for a lossless rollback, including accounts added later.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE users DROP CONSTRAINT users_pkey;
            ALTER TABLE users RENAME COLUMN id TO legacy_id;
            ALTER TABLE users
                ADD CONSTRAINT users_legacy_id_unique UNIQUE (legacy_id),
                ADD COLUMN id uuid NOT NULL DEFAULT uuidv7(),
                ADD PRIMARY KEY (id),
                ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE current_setting('TimeZone'),
                ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE current_setting('TimeZone'),
                ADD CONSTRAINT users_name_check CHECK (btrim(name) <> '') NOT VALID;

            ALTER TABLE sessions RENAME COLUMN user_id TO legacy_user_id;
            ALTER TABLE sessions ADD COLUMN user_id uuid;
            UPDATE sessions s SET user_id = u.id FROM users u WHERE u.legacy_id = s.legacy_user_id;
            DROP INDEX sessions_user_id_index;
            CREATE INDEX sessions_user_id_index ON sessions (user_id);
            SQL);
    }

    /** Restore numeric account references while retaining every account and the original session contents. */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            UPDATE sessions s SET legacy_user_id = u.legacy_id FROM users u WHERE u.id = s.user_id;
            DROP INDEX sessions_user_id_index;
            ALTER TABLE sessions DROP COLUMN user_id;
            ALTER TABLE sessions RENAME COLUMN legacy_user_id TO user_id;
            CREATE INDEX sessions_user_id_index ON sessions (user_id);

            ALTER TABLE users
                DROP CONSTRAINT users_name_check,
                DROP CONSTRAINT users_pkey,
                DROP CONSTRAINT users_legacy_id_unique,
                DROP COLUMN id,
                ALTER COLUMN created_at TYPE timestamp(0) USING created_at AT TIME ZONE current_setting('TimeZone'),
                ALTER COLUMN updated_at TYPE timestamp(0) USING updated_at AT TIME ZONE current_setting('TimeZone');
            ALTER TABLE users RENAME COLUMN legacy_id TO id;
            ALTER TABLE users ADD PRIMARY KEY (id);
            SQL);
    }
};
