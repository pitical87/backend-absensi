<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (app()->isLocal()) {
    Artisan::command('brain:scan {--watch} {--interval=3 : Poll interval in seconds (watch mode only)} {--memory-limit= : Memory limit for the scan}', function () {
        $analyzer = new \LaraMint\LaravelBrain\Analysis\ProjectAnalyzer;
        $projectPath = base_path();

        $memLimit = $this->option('memory-limit') ?: config('laravel-brain.memory_limit', '1024M');
        if ($memLimit !== '-1') {
            ini_set('memory_limit', $memLimit);
        }
        set_time_limit(300);

        $this->newLine();
        $this->line('<info>LaraMint\LaravelBrain</info> — analyzing project...');
        $this->line('Path: ' . $projectPath);
        $this->newLine();
        $this->line('Scanning routes, controllers, models and call chains...');
        $this->newLine();

        ob_start();
        $result = $analyzer->analyze($projectPath);
        ob_end_clean();

        $storageDir = storage_path('app/laravel-brain');
        if (! is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        file_put_contents($storageDir.'/.graph-manifest.json', $result->manifestJson);
        file_put_contents($storageDir.'/.graph-all.json', $result->fullGraph->toJson());

        foreach ($result->subgraphs as $tabId => $subgraph) {
            file_put_contents($storageDir."/.graph-{$tabId}.json", $subgraph->toJson());
        }

        $this->info('Done! Open the viewer at: ' . url('/_laravel-brain'));
    })->purpose('Scan the project and generate the architecture graph');
}
