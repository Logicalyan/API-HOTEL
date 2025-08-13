<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VillageSeeder extends Seeder
{
    public function run()
    {
        $districts = DB::table('districts')->pluck('id');

        foreach ($districts as $districtId) {
            $path = base_path("database/data/kelurahan/{$districtId}.json");

            if (!file_exists($path)) {
                continue; // skip kalau file tidak ada
            }

            $json = file_get_contents($path);
            $data = json_decode($json, true);

            foreach ($data as $item) {
                DB::table('villages')->insert([
                    'id'           => $item['id'],
                    'district_id'  => $districtId,
                    'name'         => $item['nama']
                ]);
            }
        }
    }
}
