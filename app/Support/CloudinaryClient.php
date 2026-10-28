<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Minimal signed-upload Cloudinary client (no cloudinary/cloudinary_php SDK
 * dependency - just Laravel's own HTTP client, which is already available).
 *
 * Why this exists: Render's free-tier disk is wiped on every deploy, so
 * anything written to local storage (documents, OR photos, PAR reports) was
 * being lost. The previous workaround base64-encoded every file into a
 * MySQL `longText` column, which bloats row size, slows down every query
 * that touches those tables, and roughly adds 33% to the file's size in
 * transit and on disk. This client uploads straight to Cloudinary instead,
 * so the database stays small and files survive redeploys.
 *
 * Security note: uploaded files use a random UUID public_id and are never
 * exposed to the frontend - the browser only ever sees our own Document /
 * TestingRecord IDs. Laravel's existing authorizeAccess() / role checks run
 * first, and only then does the backend fetch the bytes from Cloudinary and
 * stream them back (see Document::streamResponse()). The Cloudinary URL
 * itself is never handed to the client, so this keeps exactly the same
 * access-control boundary the app already had with local disk storage.
 */
class CloudinaryClient
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $url = (string) env('CLOUDINARY_URL', '');
        $parts = parse_url($url);

        if (!$parts || empty($parts['host']) || !isset($parts['user']) || !isset($parts['pass'])) {
            throw new RuntimeException(
                'CLOUDINARY_URL is not set or malformed. Set it in Render → Environment ' .
                '(format: cloudinary://<api_key>:<api_secret>@<cloud_name>), never in a committed file.'
            );
        }

        $this->cloudName = $parts['host'];
        $this->apiKey    = (string) $parts['user'];
        $this->apiSecret = (string) $parts['pass'];
    }

    /**
     * Upload a local file (e.g. $uploadedFile->getRealPath()). Returns the
     * identifiers to store on your own model - never the raw Cloudinary URL.
     *
     * @return array{public_id: string, resource_type: string, format: ?string, bytes: ?int}
     */
    public function upload(string $localPath, string $folder): array
    {
        $timestamp = time();
        // Bug (10/29/2026): this used to be "{$folder}/" . Str::uuid(), with
        // $folder ALSO sent below as its own 'folder' param. Cloudinary
        // prepends 'folder' onto whatever 'public_id' it's given, so a
        // public_id that already started with the folder name came out
        // doubled - "testing-reports/testing-reports/<uuid>" - instead of
        // "testing-reports/<uuid>". public_id here is just the unique id;
        // the 'folder' param below is what actually places it in the folder.
        $publicId  = (string) Str::uuid();

        $toSign = [
            'folder'    => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $handle = fopen($localPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$localPath} for Cloudinary upload.");
        }

        try {
            $response = Http::timeout(60)
                ->attach('file', $handle, basename($localPath))
                ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/auto/upload", [
                    'api_key'   => $this->apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $this->sign($toSign),
                    'folder'    => $folder,
                    'public_id' => $publicId,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Cloudinary upload failed: ' . $response->body());
        }

        $json = $response->json();

        return [
            // Fallback (the happy path always has this from Cloudinary's own
            // response) needs the folder prefixed back on, since $publicId
            // above is now just the bare uuid.
            'public_id'     => $json['public_id'] ?? "{$folder}/{$publicId}",
            'resource_type' => $json['resource_type'] ?? 'raw',
            'format'        => $json['format'] ?? null,
            'bytes'         => $json['bytes'] ?? null,
        ];
    }

    /**
     * Fetch a previously uploaded file's raw bytes so the backend can stream
     * them to the client itself. Call this only after your own auth check
     * has already passed.
     */
    public function fetch(string $publicId, string $resourceType, ?string $format = null): string
    {
        $url = "https://res.cloudinary.com/{$this->cloudName}/{$resourceType}/upload/{$publicId}";
        if ($format && $resourceType !== 'raw') {
            $url .= ".{$format}";
        }

        $response = Http::timeout(60)->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Could not fetch {$publicId} from Cloudinary (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    /**
     * Permanently delete a file from Cloudinary. Best-effort: a failure here
     * leaves an orphaned (harmless, just wasted-quota) file in Cloudinary -
     * it should never block the user's own delete request.
     */
    public function delete(string $publicId, string $resourceType): void
    {
        try {
            $timestamp = time();
            $toSign = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
            ];

            Http::asForm()->timeout(30)->post(
                "https://api.cloudinary.com/v1_1/{$this->cloudName}/{$resourceType}/destroy",
                [
                    'public_id' => $publicId,
                    'api_key'   => $this->apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $this->sign($toSign),
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function sign(array $params): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }

        return sha1(implode('&', $pairs) . $this->apiSecret);
    }
}