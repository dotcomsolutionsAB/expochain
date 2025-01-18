<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Utils\sendWhatsAppUtility;
use App\Models\User;

class AuthController extends Controller
{
    //    
        // genearate otp and send to `whatsapp`
        public function generate_otp(Request $request)
        {
            $request->validate([
                'mobile' => ['required', 'string', 'min:12', 'max:14'],
                'company_id' => 'required'
            ]);
    
            $mobile = $request->input('mobile');
    
            $get_user = User::select('id')
                            ->where('mobile', $mobile)
                            ->first();
    
            if(!$get_user == null)
            {
                $six_digit_otp = random_int(100000, 999999);
    
                $expiresAt = now()->addMinutes(10);
    
                $store_otp = User::where('mobile', $mobile)
                                 ->update([
                                    'otp' => $six_digit_otp,
                                    'expires_at' => $expiresAt,
                                ]);
    
                if($store_otp)
                {
                    $templateParams = [
                        'name' => 'login_otp', // Replace with your WhatsApp template name
                        'language' => ['code' => 'en'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $six_digit_otp,
                                    ],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                "index" => "0",
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $six_digit_otp,
                                    ],
                                ],
                            ]
                        ],
                    ];
    
                    $whatsappUtility = new sendWhatsAppUtility();
    
                    $response = $whatsappUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');
    
                    return response()->json([
                        'message' => 'Otp send successfully!',
                        'data' => $store_otp
                    ], 200);
                }
            }
            else {
                return response()->json([
                    'message' => 'User has not registered!',
                ], 404);
            }
        }
    
        // user `login`
        public function login(Request $request, $otp = null)
        {
            if($otp)
            {
                $request->validate([
                    'mobile' => ['required', 'string', 'size:13'],
                    'company_id' => 'required',
                ]);
                
                $otpRecord = User::select('otp', 'expires_at')
                ->where('mobile', $request->mobile)
                ->where('company_id', $request->company_id) // Add condition for company_id
                ->first();
    
                if($otpRecord)
                {
                    if(!$otpRecord || $otpRecord->otp != $otp)
                    {
                        return response()->json(['message' => 'Sorry, invalid Otp!'], 400);
                    }
                    elseif ($otpRecord->expires_at < now()) {
                        return response()->json(['message' => 'Sorry, otp has expired!'], 400);
                    }
    
                    else {
                        // Remove OTP record after successful validation
                        User::select('otp')->where('mobile', $request->mobile)->update(['otp' => null, 'expires_at' => null]);
    
                        // Retrieve the use
                        $user = User::where('mobile', $request->mobile)->first();
    
                        // Generate a sanctrum token
                        $generated_token = $user->createToken('API TOKEN')->plainTextToken;
    
                        return response()->json([
                            'success' => true,
                            'data' => [
                                'token' => $generated_token,
                                'name' => $user->name,
                                // 'role' => $user->role,
                            ],
                            'message' => 'User logged in successfully!',
                        ], 200);
                    }
                }
    
                else {
                        return response()->json([
                            'success' => false,
                            'message' => 'User not register.',
                        ], 401);
                }
            }
    
            else {
                $request->validate([
                    // 'email' => ['required', 'string', 'min:9'],
                //     'email' => [
                //     'required',
                //     'string',
                //     'email',
                //     function ($attribute, $value, $fail) {
                //         // Check for custom email validation to include shortest domain like '.bit'
                //         $pattern = '/^[\w\.\-]+@[\w\-]+\.[a-zA-Z]{2,3}$/';
                //         if (!preg_match($pattern, $value)) {
                //             $fail($attribute . ' is not a valid email address.');
                //         }
                //     },
                // ],
                    'username' => 'required',
                    'password' => 'required',
                    'company_id' => 'required'
                ]);

                if(Auth::attempt(['username' => $request->username, 'password' => $request->password, 'company_id' => $request->company_id]))
                {
                    $user = Auth::user();
    
                    // Generate a sanctrum token
                    $generated_token = $user->createToken('API TOKEN')->plainTextToken;
    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'token' => $generated_token,
                            'name' => $user->name,
                            // 'role' => $user->role,
                        ],
                        'message' => 'User logged in successfully!',
                    ], 200);
                }
    
                else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not register.',
                    ], 401);
                }
            }
        }
    
        // user `logout`
        public function logout(Request $request)
        {
            // Check if the user is authenticated
            if(!$request->user()) {
                return response()->json([
                    'success'=> false,
                    'message'=>'Sorry, no user is logged in now!',
                ], 401);
            }
    
            // Revoke the token that was used to authenticate the current request
            $request->user()->currentAccessToken()->delete();
    
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully!',
            ], 204);
        }

        public function forgetPassword(Request $request)
        {
            // Validate the incoming request
            $request->validate([
                'username' => 'required|string|exists:users,username'
            ]);

            try {
                // Retrieve the user by username
                $user = User::where('username', $request->username)->first();

                if (!$user) {
                    return response()->json([
                        'code' => 404,
                        'success' => false,
                        'message' => 'User not found.',
                    ], 404);
                }

                // Hardcoded new password
                $newPassword = 'EGSM1234'; // Replace with your desired hardcoded password

                // Update the password in the users table
                $user->update([
                    'password' => Hash::make($newPassword)
                ]);

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Password has been reset successfully!',
                    'username' => $user->username,
                    'new_password' => $newPassword // Send the new password to the user
                ], 200);

            } catch (\Exception $e) {
                return response()->json([
                    'code' => 500,
                    'success' => false,
                    'message' => 'An error occurred while resetting the password.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }
}
