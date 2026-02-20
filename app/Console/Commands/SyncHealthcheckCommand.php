<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncHealthcheckCommand extends Command
{
    protected $signature = 'sync:healthcheck {--endpoint=}';

    protected $description = 'Test connection and authentication to remote sync endpoint';

    public function handle()
    {
        $endpoint = $this->option('endpoint') ?? config('services.sync.upload_endpoint');
        $secret = config('services.sync.shared_secret');

        // Validate configuration
        if (! $endpoint) {
            $this->error('❌ No endpoint specified.');
            $this->line('');
            $this->line('Use --endpoint or set SYNC_UPLOAD_ENDPOINT in .env');

            return 1;
        }

        if (! $secret) {
            $this->error('❌ No shared secret configured.');
            $this->line('');
            $this->line('Set SHARED_UPLOAD_SECRET in .env');

            return 1;
        }

        // Parse and validate endpoint URL
        $parsedUrl = parse_url($endpoint);
        if (! $parsedUrl || ! isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            $this->error('❌ Invalid endpoint URL format.');
            $this->line('');
            $this->line('Expected format: https://your-server.com/api/import-sync');

            return 1;
        }

        // Build healthcheck URL
        $baseUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $baseUrl .= ':'.$parsedUrl['port'];
        }
        $healthcheckUrl = $baseUrl.'/api/sync-healthcheck';

        $this->info('🔍 Testing sync connection...');
        $this->line('');
        $this->table(['Setting', 'Value'], [
            ['Endpoint', $endpoint],
            ['Healthcheck URL', $healthcheckUrl],
            ['Secret Configured', $secret ? '✓ Yes' : '✗ No'],
            ['Secret Preview', $secret ? substr($secret, 0, 10).'...' : 'N/A'],
        ]);
        $this->line('');

        try {
            $this->info('📡 Connecting to remote server...');

            $timestamp = (string) now()->timestamp;
            $path = 'api/sync-healthcheck';
            $signature = hash_hmac('sha256', implode("\n", [
                'POST',
                $path,
                $timestamp,
                hash('sha256', ''),
            ]), $secret);

            $response = Http::withHeaders([
                'X-Upload-Secret' => $secret,
                'X-Upload-Timestamp' => $timestamp,
                'X-Upload-Signature' => $signature,
            ])
                ->timeout(10)
                ->post($healthcheckUrl);

            // Debug output
            $this->line('Debug Info:');
            $this->line('  HTTP Status: '.$response->status());
            $this->line('  Response Headers: '.json_encode($response->headers()));
            $this->line('');

            if ($response->successful()) {
                $data = $response->json();

                $this->info('✅ Connection successful!');
                $this->line('');
                $this->table(['Property', 'Value'], [
                    ['Status', $data['status'] ?? 'N/A'],
                    ['Message', $data['message'] ?? 'N/A'],
                    ['Server Time', $data['server_time'] ?? 'N/A'],
                    ['Environment', $data['environment'] ?? 'N/A'],
                ]);
                $this->line('');
                $this->info('🎉 Your sync configuration is working correctly!');

                return 0;

            } elseif ($response->status() === 401) {
                $this->error('❌ Authentication failed!');
                $this->line('');
                $this->warn('The shared secret does not match on the remote server.');
                $this->line('');
                $this->line('Troubleshooting steps:');
                $this->line('1. Check SHARED_UPLOAD_SECRET matches on both servers');
                $this->line('2. Ensure there are no extra spaces or hidden characters');
                $this->line('3. Try regenerating the secret on both servers');

                return 1;

            } elseif ($response->status() === 404) {
                $this->error('❌ Endpoint not found!');
                $this->line('');
                $this->warn('The healthcheck endpoint does not exist on the remote server.');
                $this->line('');
                $this->line('Troubleshooting steps:');
                $this->line('1. Ensure the remote server has the latest code deployed');
                $this->line('2. Check that routes/api.php includes the healthcheck route');
                $this->line('3. Verify the endpoint URL is correct');

                return 1;

            } else {
                $this->error('❌ Unexpected response!');
                $this->line('');
                $this->line('HTTP Status: '.$response->status());
                $this->line('Response: '.$response->body());

                return 1;
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error('❌ Connection failed!');
            $this->line('');
            $this->warn('Could not connect to the remote server.');
            $this->line('');
            $this->line('Troubleshooting steps:');
            $this->line('1. Check the endpoint URL is correct');
            $this->line('2. Ensure the remote server is online and accessible');
            $this->line('3. Verify firewall rules allow outbound HTTPS connections');
            $this->line('4. Check if the domain resolves: ping '.($parsedUrl['host'] ?? 'unknown'));
            $this->line('');
            $this->line('Error: '.$e->getMessage());

            return 1;

        } catch (\Exception $e) {
            $this->error('❌ Unexpected error!');
            $this->line('');
            $this->line('Error: '.$e->getMessage());

            Log::error('Sync healthcheck failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
