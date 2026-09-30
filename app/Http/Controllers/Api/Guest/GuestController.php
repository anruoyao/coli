<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;

/**
 * 访客 API 控制器基类。
 *
 * 仅作命名空间标记与共享类型；访客控制器不得调用 me()，
 * 所有输出必须经访客资源白名单序列化。
 */
abstract class GuestController extends Controller
{
}
