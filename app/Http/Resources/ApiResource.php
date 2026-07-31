<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base cho mọi API Resource, đảm bảo mọi response thành công đều có hình dạng
 * nhất quán { "data": {...} } thay vì tuỳ tiện theo từng Resource.
 */
abstract class ApiResource extends JsonResource
{
    public static $wrap = 'data';
}
