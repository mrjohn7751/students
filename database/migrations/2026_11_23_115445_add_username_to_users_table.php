<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kirish email o'rniga login (username) orqali. Mavjud hisoblar uchun login emailning
     * "@" gacha qismidan olinadi (admin@jdpu.uz → admin); band bo'lsa ID qo'shiladi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $user) {
            $base = Str::lower(preg_replace('/[^a-z0-9._-]/i', '', Str::before((string) $user->email, '@'))) ?: 'user';
            $username = DB::table('users')->where('username', $base)->exists() ? $base.$user->id : $base;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('email')->update(['email' => DB::raw("username || '@local.invalid'")]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
