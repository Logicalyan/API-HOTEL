<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    public function run()
    {
        $json = file_get_contents(base_path('database/data/provinsi.json'));
        $data = json_decode($json, true);

        foreach ($data as $item) {
            DB::table('provinces')->insert([
                'id' => $item['id'],
                'name' => $item['nama']
            ]);
        }
    }
}
