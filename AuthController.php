<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Otp;
use Carbon\Carbon;

use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;



class AuthController extends Controller
{
    // Register API
    public function register(Request $request)
    {
        // Data Validation
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users',
            'mobile'        => 'required|unique:users,mobile',
            'password'      => 'required|confirmed|min:6',
            'category'      => 'required|string',
            'date_of_birth' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Create User
        User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'mobile'         => $request->mobile,
            'password'       => Hash::make($request->password),
            'category'       => $request->category,
            'date_of_birth'  => $request->date_of_birth, // Add DOB here
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'User Created Successfully',
        ]);
    }


    public function getUserById($id)
{
    try {
        // Validate and authenticate token
        if (!$authUser = JWTAuth::parseToken()->authenticate()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access - user not found from token.',
            ], 401);
        }

        // Retrieve the user by given ID
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully.',
            'data' => $user
        ], 200);

    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Token has expired. Please login again.',
        ], 401);

    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid token.',
        ], 401);

    } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Token missing or not parsed.',
        ], 401);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Unexpected server error.',
            'error' => $e->getMessage()
        ], 500);
    }
}




    public function list(Request $request)
    {

        $user = JWTAuth::parseToken()->authenticate();
        // Get all users
        $users = User::all();

        return response()->json([
            'status'  => true,
            'message' => 'Users Retrieved Successfully',
            'data'    => $users,
        ]);
    }



    // Login API (empty for now)

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        try {
            $credentials = $request->only('email', 'password');
            $token = JWTAuth::attempt($credentials);

            // ❌ If login fails (invalid credentials)
            if (!$token) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid email or password'
                ], 401); // 401 Unauthorized
            }

            $user = auth()->user();

            // ✅ If login is successful
            return response()->json([
                'status'  => true,
                'message' => 'User logged in successfully',
                'token'   => $token,
                'user'    => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'mobile' => $user->mobile,
                    'date_of_birth' => $user->date_of_birth,


                    'category' => $user->category, // Adjust if needed
                ]
            ], 200); // 200 OK

        } catch (JWTException $e) {
            // ❌ Token creation or server error
            return response()->json([
                'status'  => false,
                'message' => 'Could not create token',
                'error'   => $e->getMessage()
            ], 500); // 500 Internal Server Error
        }
    }


    public function updateProfile(Request $request)
    {
        // Get the authenticated user
        $user = JWTAuth::parseToken()->authenticate();

        // Validate the inputs, including the new date_of_birth
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            // 'email'          => 'required|email|unique:users,email,' . $user->id,
            'mobile'         => 'required|unique:users,mobile,' . $user->id,
            // 'password'       => 'nullable|min:6|confirmed',
            // 'category'       => 'required|string',
            'date_of_birth'  => 'nullable|date', // Allow date_of_birth to be nullable during update
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Update fields if present
        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('mobile')) $user->mobile = $request->mobile;
        if ($request->has('category')) $user->category = $request->category;
        if ($request->has('date_of_birth')) $user->date_of_birth = $request->date_of_birth; // Update DOB if provided

        // If password is filled, hash and update it
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }


    public function logout(Request $request)
    {
        try {
            // Invalidate the token


            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'status'  => false,
                'message' => 'User logged out successfully',
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to logout, please try again',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }



    public function forgotPassword(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json([
                'status' => false,
                'message' => 'Method not allowed.'
            ], 405);
        }
        // $validated = $request->validate([
        //     'email' => 'required|email|exists:users,email',
        // ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ], 422);
        }


        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->otp_expires_at && now()->lt($user->otp_expires_at)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP already sent. Please wait until it expires.'
            ], 409); // Conflict
        }

        $otp = rand(1000, 9999);
        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        try {
            $email = $user->email;
            Mail::to($user->email)->send(new OtpMail($user->name, $otp));

            // Mail::raw("Your OTP is: $otp", function ($message) use ($email) {
            //     $message->to($email)
            //         ->subject('Your Password Reset OTP');
            // });

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP email.'
            ], 500); // Internal Server Error
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email.'
        ], 200); // OK
    }






    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|digits:4'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ], 422);
        }

        // $request->validate([
        //     'email' => 'required|email',
        //     'otp'   => 'required|digits:4'
        // ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->otp !== $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        if (Carbon::now()->gt($user->otp_expires_at)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired'
            ], 400);
        }

        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully'
        ]);
    }

    public function resetPassword(Request $request)
    {
        // $request->validate([
        //     'email'                 => 'required|email|exists:users,email',
        //     'otp'                   => 'required|digits:4',
        //     'password'              => 'required|min:6|confirmed',
        // ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password'  => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // if (!$user || $user->otp !== $request->otp) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Invalid OTP'
        //     ], 400);
        // }

        // if (Carbon::now()->gt($user->otp_expires_at)) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'OTP expired'
        //     ], 400);
        // }

        $user->password = Hash::make($request->password);
        // $user->otp = null;
        // $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password reset successful'
        ]);
    }
}
