<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\AdminRegisterRequest;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function register(AdminRegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] =  Hash::make($data['password']);
        $admin = Admin::create($data);
        return response()->json([
            'message' => 'Admin registered successfully',
            'data' => $admin
        ]);
    }
    public function login(AdminLoginRequest $request)
    {
        $credentials = $request->validated();

        $admin = Admin::where('email', $credentials['email'])->first();

        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            return error('Invalid Credentials', 401);
        }

        $token = $admin->createToken('postman-token-admin')->plainTextToken;

        return response()->json([
            'message' => 'Admin logged in successfull',
            'token' => $token
        ]);
    }
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
