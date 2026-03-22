<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_public_token', 64)->nullable()->unique()->after('school_logo_url');
            $table->string('school_logo_public_token', 64)->nullable()->unique()->after('avatar_public_token');
        });

        // Backfill tokens so existing disk files are reachable via /api/public/... (not /storage/).
        User::query()->orderBy('id')->chunk(100, function ($users) {
            foreach ($users as $user) {
                $rawAvatar = $user->profile_avatar_url ?? $user->avatar_url;
                if ($rawAvatar && str_starts_with($rawAvatar, '/storage/') && $user->avatar_public_token === null) {
                    $user->forceFill(['avatar_public_token' => Str::random(48)])->saveQuietly();
                }
                if ($user->school_logo_url && str_starts_with($user->school_logo_url, '/storage/')
                    && $user->school_logo_public_token === null) {
                    $user->forceFill(['school_logo_public_token' => Str::random(48)])->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_public_token', 'school_logo_public_token']);
        });
    }
};
