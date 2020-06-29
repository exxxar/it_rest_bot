<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //$this->call(ProductTableSeeder::class);
        $this->call(PrizeTableSeeder::class);
     //   $this->call(PromocodeTableSeeder::class);


        \App\Product::truncate();

        \App\Product::create([
            "title"=>"Разработка веб-сайтов",
            "article_url"=>"https://telegra.ph/Razrabotka-veb-sajtov-06-29"
        ]);

        \App\Product::create([
            "title"=>"Разработка телеграм-ботов",
            "article_url"=>"https://telegra.ph/Razrabotka-telegram-botov-06-29"
        ]);

    }
}
