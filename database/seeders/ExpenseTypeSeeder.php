<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Seeder;

class ExpenseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'EQUAL','EXACT','PERCENT'
        ];
        foreach($data as $val) {
            ExpenseType::create([
                'name' => $val,
            ]);
        }
    }
}
