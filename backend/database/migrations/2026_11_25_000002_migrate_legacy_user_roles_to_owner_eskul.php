<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Safely migrate legacy organizational accounts created under role='user' to 'owner_eskul'
        DB::table('users')
            ->where('role', 'user')
            ->whereNotNull('organization_id')
            ->update(['role' => 'owner_eskul']);
    }

    public function down(): void
    {
        // Intentionally left as a non-destructive no-op so legitimate owner_eskul rows are not reverted.
    }
};

