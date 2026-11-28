<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The official Instagram and Telegram accounts, shown to members and visitors; editable in the admin center. */
return new class extends Migration
{
    public const LINKS = [
        ['kind' => 'instagram', 'label' => 'SN TRADE', 'url' => 'https://www.instagram.com/sntradex'],
        ['kind' => 'instagram', 'label' => 'C.Sobhi', 'url' => 'https://www.instagram.com/sobhi_abo_aisha'],
        ['kind' => 'telegram', 'label' => 'Channel', 'url' => 'https://t.me/sntradex'],
        ['kind' => 'telegram', 'label' => 'Support', 'url' => 'https://t.me/sntradesupport'],
        ['kind' => 'telegram', 'label' => 'C.Sobhi', 'url' => 'https://t.me/Sobhi_Abo_Aisha'],
    ];

    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'official_links')->exists()) {
            DB::table('settings')->insert(['key' => 'official_links', 'value' => json_encode(self::LINKS), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'official_links')->delete();
    }
};
