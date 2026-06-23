<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class GiftCardTemplatePreviewController extends Controller
{
    public function __invoke(): Response
    {
        return response('<html><body><h1>Gift card preview</h1></body></html>', 200);
    }
}
