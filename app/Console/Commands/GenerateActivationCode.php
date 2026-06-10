<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivationCode;
use Illuminate\Support\Str;

class GenerateActivationCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'code:generate {count=1 : Number of codes to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate new activation codes for users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');

        $this->info("Generating {$count} activation code(s)...");

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            
            ActivationCode::create([
                'code' => $code,
                'is_used' => false,
            ]);

            $this->line("Code created: <comment>{$code}</comment>");
        }

        $this->info('Done!');
    }
}
