<?php

namespace Hammerstone\Sidecar\PHP\Commands;

use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\PHP\VaporLayers;
use Hammerstone\Sidecar\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class FetchVaporLayers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sidecar:vapor-layers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find the PHP layers that Vapor publishes.';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $regions = array_flip(Region::all());

        $regions = Arr::except($regions, [
            Region::CN_NORTH_1,
            Region::CN_NORTHWEST_1,
        ]);

        $regions = array_flip($regions);

        $layers = VaporLayers::phpVersions();

        $export = [];

        foreach ($regions as $const => $region) {
            $this->info("Checking $region...");

            $client = new LambdaClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => config('sidecar.aws_key'),
                    'secret' => config('sidecar.aws_secret'),
                ]
            ]);

            $export[] = "Region::$const => [";

            $found = 0;
            foreach ($layers as $const => $layer) {
                if ($this->layerExists($client, $region, $layer)) {
                    $found++;
                    $version = $this->latestVersion($client, $region, $layer);

                    $export[] = "\tPhpLayers::$const => $version,";
                }
            }

            if ($found) {
                $export[] = "],";
            } else {
                array_pop($export);
            }
        }

        $export = implode("\n", $export);

        $this->info('Vapor layers found:');
        echo "[\n$export\n];";
    }

    public function layerExists($client, $region, $layer)
    {
        try {
            $client->getLayerVersionByArn([
                'Arn' => "arn:aws:lambda:{$region}:959512994844:layer:vapor-$layer:1",
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function latestVersion($client, $region, $layer)
    {
        $version = 2;
        while (true) {
            try {
                $client->getLayerVersionByArn([
                    'Arn' => "arn:aws:lambda:{$region}:959512994844:layer:vapor-$layer:$version",
                ]);

                $version++;
            } catch (\Exception $e) {
                return --$version;
            }
        }
    }
}
