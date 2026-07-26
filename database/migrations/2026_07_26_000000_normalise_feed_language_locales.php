<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RENAMES = [
        'pt-BR' => 'pt_BR',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('feeds')->where('language', $from)->update(['language' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('feeds')->where('language', $to)->update(['language' => $from]);
        }
    }
};
