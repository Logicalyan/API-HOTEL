<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run()
    {
        $regencies = DB::table('regencies')->pluck('id');

        foreach ($regencies as $regencyId) {
            $path = base_path("database/data/kecamatan/{$regencyId}.json");

            if (!file_exists($path)) {
                continue; // skip kalau file tidak ada
            }

            $json = file_get_contents($path);
            $data = json_decode($json, true);

            foreach ($data as $item) {
                DB::table('districts')->insert([
                    'id'          => $item['id'],
                    'regency_id'  => $regencyId,
                    'name'        => $item['nama']
                ]);
            }
        }
    }
}
