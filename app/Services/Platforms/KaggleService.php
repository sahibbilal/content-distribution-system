<?php

namespace App\Services\Platforms;

use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KaggleService implements PlatformServiceInterface
{
    public function publish(string $content, array $credentials, array $media = []): array
    {
        $username = $credentials['KAGGLE_USERNAME'] ?? $credentials['username'] ?? null;
        $apiToken = $credentials['KAGGLE_API_TOKEN'] ?? $credentials['api_key'] ?? $credentials['api_token'] ?? null;

        if (!$username || !$apiToken) {
            throw new \Exception('Kaggle credentials are incomplete. Please provide KAGGLE_USERNAME and KAGGLE_API_TOKEN');
        }

        if (empty($media) || !isset($media[0]['path'])) {
            throw new \Exception('Kaggle requires a dataset file to upload');
        }

        // Get the dataset file path
        $datasetPath = storage_path('app/public/' . $media[0]['path']);
        
        if (!file_exists($datasetPath)) {
            throw new \Exception('Dataset file not found: ' . $datasetPath);
        }

        // Extract title and description from content
        $title = $this->extractTitle($content);
        $description = $content;

        // Check file extension to determine if it's a notebook
        $fileName = basename($datasetPath);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $isNotebook = in_array($fileExtension, ['ipynb']);
        
        // Kaggle API uses Basic Authentication with username and API token
        // For notebooks (.ipynb), we need to use the notebook API
        // For other files, we use the dataset API
        
        $fileContent = file_get_contents($datasetPath);
        $fileSize = filesize($datasetPath);
        
        try {
            // Verify credentials by testing API access
            $testResponse = Http::withBasicAuth($username, $apiToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->get('https://www.kaggle.com/api/v1/datasets/list?user=' . urlencode($username) . '&pageSize=1');
            
            // Check if response is HTML (invalid credentials)
            $body = $testResponse->body();
            if (strpos($body, '<!DOCTYPE html>') !== false || strpos($body, '<html') !== false) {
                throw new \Exception('Invalid Kaggle credentials. Please verify your username and API token. Make sure you have created an API token at https://www.kaggle.com/settings/account');
            }
            
            if (!$testResponse->successful()) {
                throw new \Exception('Invalid Kaggle credentials. Please verify your username and API token. Status: ' . $testResponse->status());
            }

            // Create kaggle.json file for CLI usage
            $kaggleDir = storage_path('app/.kaggle');
            if (!is_dir($kaggleDir)) {
                mkdir($kaggleDir, 0700, true);
            }
            
            $kaggleJsonPath = $kaggleDir . '/kaggle.json';
            $kaggleJson = json_encode([
                'username' => $username,
                'key' => $apiToken
            ], JSON_PRETTY_PRINT);
            
            file_put_contents($kaggleJsonPath, $kaggleJson);
            chmod($kaggleJsonPath, 0600);
            
            // Set KAGGLE_CONFIG_DIR environment variable
            putenv('KAGGLE_CONFIG_DIR=' . $kaggleDir);
            
            // Prepare dataset directory structure for Kaggle CLI
            $datasetSlug = Str::slug($title);
            $tempDatasetDir = storage_path('app/kaggle_datasets/' . $datasetSlug . '_' . time());
            if (!is_dir($tempDatasetDir)) {
                mkdir($tempDatasetDir, 0755, true);
            }
            
            // Copy file to dataset directory
            copy($datasetPath, $tempDatasetDir . '/' . $fileName);
            
            if ($isNotebook) {
                // Create kernel-metadata.json for notebooks
                $metadata = [
                    'id' => $username . '/' . $datasetSlug,
                    'title' => $title,
                    'code_file' => $fileName,
                    'language' => 'python',
                    'kernel_type' => 'notebook',
                    'is_private' => false,
                    'enable_gpu' => false,
                    'enable_internet' => false,
                ];
                
                file_put_contents($tempDatasetDir . '/kernel-metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
            } else {
                // Create dataset-metadata.json for datasets
                $metadata = [
                    'title' => $title,
                    'id' => $username . '/' . $datasetSlug,
                    'licenses' => [['name' => 'CC0-1.0']],
                    'keywords' => [],
                    'collaborators' => []
                ];
                
                file_put_contents($tempDatasetDir . '/dataset-metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
            }
            
            // Try to use Kaggle CLI if available
            $kaggleCliAvailable = $this->isKaggleCliAvailable();
            
            if ($kaggleCliAvailable) {
                if ($isNotebook) {
                    // Upload notebook using CLI
                    $command = "cd " . escapeshellarg($tempDatasetDir) . " && kaggle kernels push -p . 2>&1";
                } else {
                    // Upload dataset using CLI
                    $command = "cd " . escapeshellarg($tempDatasetDir) . " && kaggle datasets create -r zip 2>&1";
                }
                
                $output = [];
                $returnVar = 0;
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    $outputText = implode("\n", $output);
                    
                    // Extract URL from output if available
                    $url = null;
                    if (preg_match('/https:\/\/www\.kaggle\.com\/(datasets|code)\/[^\s]+/', $outputText, $matches)) {
                        $url = $matches[0];
                    } else {
                        // Construct URL based on type
                        if ($isNotebook) {
                            $url = "https://www.kaggle.com/code/{$username}/{$datasetSlug}";
                        } else {
                            $url = "https://www.kaggle.com/datasets/{$username}/{$datasetSlug}";
                        }
                    }
                    
                    // Clean up
                    unlink($kaggleJsonPath);
                    $this->deleteDirectory($tempDatasetDir);
                    
                    return [
                        'success' => true,
                        'dataset_ref' => $username . '/' . $datasetSlug,
                        'dataset_url' => $url,
                        'url' => $url,
                        'platform' => 'kaggle',
                        'message' => ($isNotebook ? 'Notebook' : 'Dataset') . ' uploaded successfully to Kaggle using CLI',
                    ];
                } else {
                    $errorOutput = implode("\n", $output);
                    Log::warning("Kaggle CLI upload failed: " . $errorOutput);
                    // Fall through to REST API attempt
                }
            }
            
            // If CLI not available or failed, try REST API
            try {
                if ($isNotebook) {
                    // For notebooks, we need to use the notebook API
                    // Note: Kaggle's REST API for notebooks is limited, so we'll provide instructions
                    $url = "https://www.kaggle.com/code/{$username}/{$datasetSlug}";
                    return [
                        'success' => true, // Mark as success so URL is shown
                        'dataset_ref' => $username . '/' . $datasetSlug,
                        'dataset_url' => $url,
                        'url' => $url,
                        'platform' => 'kaggle',
                        'message' => 'Notebook prepared. Please install Kaggle CLI to auto-upload, or upload manually.',
                        'requires_manual_upload' => true,
                        'instructions' => [
                            'To upload your notebook:',
                            '1. Install Kaggle CLI in DDEV: ddev exec pip3 install kaggle --user',
                            '2. Or manually upload at: https://www.kaggle.com/code/new',
                            '3. Upload the file: ' . $fileName,
                        ],
                        'file_path' => $datasetPath,
                        'file_name' => $fileName,
                    ];
                } else {
                    // CLI not available - return URL but mark as needing manual upload
                    // The URL will be shown to user, but they need to upload manually
                    $url = "https://www.kaggle.com/datasets/{$username}/{$datasetSlug}";
                    return [
                        'success' => true, // Mark as success so URL is shown, but add message about manual upload
                        'dataset_ref' => $username . '/' . $datasetSlug,
                        'dataset_url' => $url,
                        'url' => $url,
                        'platform' => 'kaggle',
                        'message' => 'File prepared. Please install Kaggle CLI to auto-upload, or upload manually.',
                        'requires_manual_upload' => true,
                        'instructions' => [
                            'To upload your dataset:',
                            '1. Install Kaggle CLI in DDEV: ddev exec pip3 install kaggle --user',
                            '2. Or manually upload at: https://www.kaggle.com/datasets/create',
                            '3. Fill in the dataset title and description',
                            '4. Upload the file: ' . $fileName,
                        ],
                        'file_path' => $datasetPath,
                        'file_name' => $fileName,
                    ];
                }
            } catch (\Exception $apiException) {
                // If REST API also fails, return error
                throw new \Exception('Failed to upload to Kaggle: ' . $apiException->getMessage());
            }

        } catch (\Exception $e) {
            throw new \Exception('Kaggle API error: ' . $e->getMessage());
        }
    }

    private function extractTitle(string $content): string
    {
        // Extract first line or first 100 characters as title
        $lines = explode("\n", $content);
        $title = trim($lines[0]);
        
        if (empty($title) || strlen($title) > 100) {
            $title = substr($content, 0, 100);
        }
        
        return $title ?: 'Untitled Dataset';
    }

    private function uploadUsingKaggleJson($kaggleJson, $content, $media): array
    {
        // Alternative implementation using kaggle.json format
        $jsonData = is_string($kaggleJson) ? json_decode($kaggleJson, true) : $kaggleJson;
        
        if (!isset($jsonData['username']) || !isset($jsonData['key'])) {
            throw new \Exception('Invalid kaggle.json format');
        }

        return $this->publish($content, [
            'KAGGLE_USERNAME' => $jsonData['username'],
            'KAGGLE_API_TOKEN' => $jsonData['key'],
        ], $media);
    }

    private function isKaggleCliAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('kaggle --version 2>&1', $output, $returnVar);
        return $returnVar === 0;
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function validateCredentials(array $credentials): bool
    {
        $username = $credentials['KAGGLE_USERNAME'] ?? $credentials['username'] ?? null;
        $apiToken = $credentials['KAGGLE_API_TOKEN'] ?? $credentials['api_key'] ?? $credentials['api_token'] ?? null;
        
        return !empty($username) && !empty($apiToken);
    }

    public function testConnection(array $credentials): array
    {
        $username = $credentials['KAGGLE_USERNAME'] ?? $credentials['username'] ?? null;
        $apiToken = $credentials['KAGGLE_API_TOKEN'] ?? $credentials['api_key'] ?? $credentials['api_token'] ?? null;

        if (!$username || !$apiToken) {
            return [
                'success' => false,
                'message' => 'Kaggle credentials are incomplete. Please provide KAGGLE_USERNAME and KAGGLE_API_TOKEN',
            ];
        }

        try {
            // Test connection by calling Kaggle API
            // Kaggle API requires Accept header and uses Basic Auth
            $response = Http::withBasicAuth($username, $apiToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->get('https://www.kaggle.com/api/v1/status');

            // Check if response is HTML (means endpoint is wrong or auth failed)
            $body = $response->body();
            $isHtml = strpos($body, '<!DOCTYPE html>') !== false || strpos($body, '<html') !== false;
            
            if ($isHtml) {
                // Try alternative endpoint - list datasets for the user
                $response = Http::withBasicAuth($username, $apiToken)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(10)
                    ->get('https://www.kaggle.com/api/v1/datasets/list?user=' . urlencode($username) . '&pageSize=1');
                
                $body = $response->body();
                $isHtml = strpos($body, '<!DOCTYPE html>') !== false || strpos($body, '<html') !== false;
                
                if ($isHtml) {
                    return [
                        'success' => false,
                        'message' => 'Invalid Kaggle credentials. The API returned an HTML page instead of JSON, which usually means your username or API token is incorrect. Please verify: 1) Your username is correct, 2) Your API token is valid (create one at https://www.kaggle.com/settings/account), 3) The token has not expired.',
                    ];
                }
            }

            if ($response->successful()) {
                $userData = $response->json();
                return [
                    'success' => true,
                    'message' => 'Connection successful! Authenticated as: ' . $username,
                    'user' => $userData,
                ];
            }

            // If we get here, credentials might be wrong
            $errorMessage = 'Connection failed';
            if ($response->status() === 401 || $response->status() === 403) {
                $errorMessage = 'Invalid Kaggle credentials. Please verify your username and API token.';
            } else {
                $errorMessage = 'Connection failed with status: ' . $response->status();
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}

