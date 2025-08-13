<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegencySeeder extends Seeder
{
    public function run()
    {
        $provinces = DB::table('provinces')->pluck('id');

        foreach ($provinces as $provinceId) {
            $path = base_path("database/data/kabupaten/{$provinceId}.json");

            if (!file_exists($path)) {
                continue; // skip kalau file gak ada
            }

            $json = file_get_contents($path);
            $data = json_decode($json, true);

            foreach ($data as $item) {
                DB::table('regencies')->insert([
                    'id'          => $item['id'],
                    'province_id' => $provinceId,
                    'name'        => $item['nama']
                ]);
            }
        }
    }
}
