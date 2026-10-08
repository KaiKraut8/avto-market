<?php

namespace App\Console\Commands;

use App\Services\PhotoStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Copies everything from the old custom-PHP database (the "legacy" connection) into the Laravel schema,
// keeping ids. The old database is only read, never changed.
class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
                            {--fresh : empty the Laravel tables first}
                            {--uploads=legacy/public/uploads : folder with the old photo files}';

    protected $description = 'Import cars, photos, parts, views, wishlists and inquiries from the legacy database';

    private const TABLES = ['users', 'part_categories', 'cars', 'car_photos', 'car_parts', 'car_views', 'wishlist_items', 'car_inquiries', 'car_logs'];

    public function handle(PhotoStore $photos): int
    {
        $legacy = DB::connection('legacy');
        $uploads = base_path($this->option('uploads'));
        $counts = [];

        // TRUNCATE commits implicitly in MySQL, so empty the tables before the import transaction starts
        if ($this->option('fresh')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ([...self::TABLES, 'car_watchers'] as $t) {
                DB::table($t)->truncate();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        DB::transaction(function () use ($legacy, $uploads, $photos, &$counts) {

            foreach ($legacy->table('users')->orderBy('id')->get() as $u) {
                DB::table('users')->insert([
                    'id' => $u->id, 'name' => $u->name, 'email' => mb_strtolower($u->email), 'password' => $u->password_hash,
                    'phone' => $u->phone, 'location' => $u->location, 'country' => $u->country,
                    'premium_plan' => $u->premium_plan, 'premium_since' => $u->premium_since, 'premium_until' => $u->premium_until,
                    'created_at' => $u->created_at, 'updated_at' => $u->created_at,
                ]);
            }

            foreach ($legacy->table('part_categories')->orderBy('id')->get() as $c) {
                DB::table('part_categories')->updateOrInsert(['id' => $c->id], ['name' => $c->name, 'created_at' => now(), 'updated_at' => now()]);
            }

            foreach ($legacy->table('cars')->orderBy('id')->get() as $c) {
                // per-car premium bought before accounts existed is kept until it runs out
                $legacyPremium = ($c->premium ?? 0) == 1 && $c->premium_until ? $c->premium_until : null;
                DB::table('cars')->insert([
                    'id' => $c->id, 'user_id' => $c->user_id ?? null, 'name' => $c->name, 'price' => $c->price,
                    'description' => $c->description, 'location' => $c->seller_location ?? null, 'country' => $c->country ?? null,
                    'boosted_until' => $c->boost_until ?? null, 'legacy_premium_until' => $legacyPremium,
                    'legacy_seller_name' => $c->seller_name ?? null, 'legacy_seller_phone' => $c->seller_phone ?? null,
                    'legacy_seller_email' => $c->seller_email ?? null,
                    'created_at' => $c->creation_date, 'updated_at' => $c->creation_date, 'deleted_at' => $c->deleted_at,
                ]);
            }

            $position = [];
            foreach ($legacy->table('car_photos')->orderBy('id')->get() as $p) {
                $source = $uploads.'/'.basename($p->filename);
                if (! is_file($source)) {
                    $this->warn("Photo file missing, skipped: {$p->filename}");

                    continue;
                }
                $ext = strtolower(pathinfo($p->filename, PATHINFO_EXTENSION)) ?: 'jpg';
                $bytes = $photos->encode($source, $ext === 'jpeg' ? 'jpg' : $ext);
                $position[$p->car_id] = ($position[$p->car_id] ?? 0) + 1;
                DB::table('car_photos')->insert([
                    'id' => $p->id, 'car_id' => $p->car_id, 'data' => $bytes, 'size' => strlen($bytes),
                    'mime' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg', 'position' => $position[$p->car_id],
                    'created_at' => $p->created_at, 'updated_at' => $p->created_at,
                ]);
            }

            foreach ($legacy->table('car_parts')->whereNotNull('car_id')->orderBy('id')->get() as $p) {
                DB::table('car_parts')->insert([
                    'id' => $p->id, 'car_id' => $p->car_id, 'part_category_id' => $p->category_id ?? null,
                    'name' => $p->part_name, 'description' => trim((string) $p->description) === '' ? null : $p->description,
                    'created_at' => $p->creation_date, 'updated_at' => $p->creation_date,
                ]);
            }

            foreach ($legacy->table('car_views')->orderBy('id')->lazy() as $v) {
                DB::table('car_views')->insert(['id' => $v->id, 'car_id' => $v->car_id, 'visitor_id' => $v->visitor_id, 'viewed_at' => $v->viewed_at]);
            }

            foreach ($legacy->table('wishlist')->get() as $w) {
                DB::table('wishlist_items')->insert(['visitor_id' => $w->visitor_id, 'car_id' => $w->car_id, 'created_at' => $w->created_at]);
            }

            foreach ($legacy->table('car_inquiries')->orderBy('id')->get() as $q) {
                DB::table('car_inquiries')->insert([
                    'id' => $q->id, 'car_id' => $q->car_id, 'visitor_id' => $q->visitor_id, 'name' => $q->name, 'email' => $q->email,
                    'phone' => $q->phone, 'message' => $q->message, 'created_at' => $q->created_at, 'updated_at' => $q->created_at,
                ]);
            }

            foreach ($legacy->table('cars_log')->orderBy('id')->get() as $l) {
                DB::table('car_logs')->insert([
                    'id' => $l->id, 'car_id' => $l->car_id, 'action' => $l->action, 'old_name' => $l->old_name, 'new_name' => $l->new_name,
                    'created_at' => $l->logged_at,
                ]);
            }
        });

        // after the transaction: ALTER TABLE commits implicitly in MySQL
        // keep new ids above the imported ones
        foreach (['users', 'part_categories', 'cars', 'car_photos', 'car_parts', 'car_views', 'car_inquiries', 'car_logs'] as $t) {
            $max = (int) DB::table($t)->max('id');
            DB::statement("ALTER TABLE `$t` AUTO_INCREMENT = ".($max + 1));
        }

        $map = [
            'users' => 'users', 'part_categories' => 'part_categories', 'cars' => 'cars', 'car_photos' => 'car_photos',
            'car_parts' => 'car_parts', 'car_views' => 'car_views', 'wishlist' => 'wishlist_items',
            'car_inquiries' => 'car_inquiries', 'cars_log' => 'car_logs',
        ];
        foreach ($map as $from => $to) {
            $counts[] = [$from.' → '.$to, $legacy->table($from)->count(), DB::table($to)->count()];
        }

        $this->table(['Table', 'Legacy', 'Imported'], $counts);
        $mismatch = array_filter($counts, fn ($r) => $r[1] !== $r[2]);
        if ($mismatch) {
            $this->error('Row counts differ for: '.implode(', ', array_map(fn ($r) => $r[0], $mismatch)));

            return self::FAILURE;
        }
        $this->info('Import complete. Photos are stored in the database.');

        return self::SUCCESS;
    }
}
