<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Validation\User\Account\UserdataRules;

class VerificationController extends Controller
{
    use SupportsApiResponses;
    
    public function verifyUser(Request $request)
    {
        $user = $request->user('sanctum');

        abort_unless($user && ($user->isAdmin() || $user->isRoot()), 403);

        return $this->performVerification($request, 'admin', $user->id);
    }

    public function verifySystemUser(Request $request)
    {
        $expectedKey = (string) config('services.system_verification.api_key', '');
        $providedKey = (string) $request->header('X-System-Verification-Key', '');

        abort_unless($expectedKey !== '' && hash_equals($expectedKey, $providedKey), 401);

        return $this->performVerification($request, 'system', null);
    }

    private function performVerification(Request $request, string $source, ?int $actorId)
    {

        $validator = Validator::make([
            'username' => $request->get('username', null)
        ], [
            'username' => UserdataRules::username()
        ]);

        if($validator->fails()) {
            return $this->responseValidationError([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $username = $request->get('username');
        $userData = User::where('username', $username)->first();

        if(! $userData) {
            return $this->responseResourceNotFoundError('User', $username);
        }

        if($userData->verified) {
            return $this->responseValidationError([
                'message' => 'User is already verified.'
            ]);
        }

        else {
            $userData->update([
                'verified_at' => now(),
                'verified' => true
            ]);

            Log::info('User verification status changed.', [
                'target_user_id' => $userData->id,
                'actor_user_id' => $actorId,
                'source' => $source,
            ]);
    
            // TODO: Send verification email to user.
            // Send notification to user.
    
            return $this->responseSuccess([
                'message' => 'User verified successfully.'
            ]);
        }

    }
}
