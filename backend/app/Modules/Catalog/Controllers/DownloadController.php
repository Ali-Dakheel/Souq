<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\DownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct(private readonly DownloadService $downloadService) {}

    /**
     * GET /downloads/{token}
     * Auth required. Returns the file as a download response.
     */
    public function download(Request $request, string $token): StreamedResponse|JsonResponse
    {
        try {
            $purchase = $this->downloadService->validateAndDecodeToken($token, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $link = $purchase->downloadableLink;

        // Check file exists on local disk
        if (! Storage::disk('local')->exists($link->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $this->downloadService->recordDownload($purchase);

        return Storage::disk('local')->download(
            $link->file_path,
            basename($link->file_path)
        );
    }
}
