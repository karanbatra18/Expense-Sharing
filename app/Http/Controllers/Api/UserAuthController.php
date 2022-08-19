<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;

class UserAuthController extends BaseController
{
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $input = $request->all();

        /* User validation Rules */
        $rules = [
            'name' => 'required|max:255',
            'email' => 'required|email',
            'phone' => 'numeric|digits:10',
            'password' => 'required|confirmed'
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check if email already exist
        $user = User::where('email', $input['email'])->first();

        if (!empty($user)) {
            // If yes than update name, phone and password
            $user->name = $input['name'];
            if (!empty($request->phone)) {
                $user->phone = $input['phone'];
            }
            $user->password = bcrypt($input['password']);
            $user->save();
        } else {
            // Else create a new user
            $input['password'] = bcrypt($input['password']);
            $input['phone'] = $input['phone'] ?? null;
            $user = User::create($input);
        }

        $token = $user->createToken('Laravel')->accessToken;

        $response = [
            'user' => $user,
            'token' => $token
        ];

        return $this->sendResponse($response, 'Account successfully created.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $input = $request->all();

        /* User validation Rules */
        $rules = [
            'email' => 'email|required',
            'password' => 'required'
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if (!auth()->attempt($input)) {
            return $this->sendError('Validation Error.', [
                'error_message' => 'Incorrect Details. Please try again'
            ]);
        }

        $user = auth()->user();
        $token = $user->createToken('Laravel')->accessToken;

        $response = [
            'user' => auth()->user(),
            'token' => $token
        ];

        return $this->sendResponse($response, 'Login successful.');
    }
}
