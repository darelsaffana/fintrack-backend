<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    private array $defaultCategories = [
        ['name' => 'Gaji', 'type' => 'income', 'color' => '#3ddc97'],
        ['name' => 'Freelance', 'type' => 'income', 'color' => '#4fc3f7'],
        ['name' => 'Hadiah', 'type' => 'income', 'color' => '#8b7cf6'],
        ['name' => 'Makanan', 'type' => 'expense', 'color' => '#ff6b8a'],
        ['name' => 'Transportasi', 'type' => 'expense', 'color' => '#ffb84f'],
        ['name' => 'Belanja', 'type' => 'expense', 'color' => '#f472b6'],
        ['name' => 'Hiburan', 'type' => 'expense', 'color' => '#facc15'],
        ['name' => 'Tagihan', 'type' => 'expense', 'color' => '#4fd1c5'],
    ];

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        foreach ($this->defaultCategories as $cat) {
            $user->categories()->create($cat);
        }

        $token = $user->createToken('fintrack-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        $token = $user->createToken('fintrack-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$request->user()->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $request->user()->update($request->only(['name', 'email']));

        return response()->json($request->user());
    }

    public function uploadAvatar(Request $request)
{
    $validator = Validator::make($request->all(), [
        'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096', // maks 4MB
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
    }

    $user = $request->user();

    // Hapus foto lama kalau ada, biar storage tidak numpuk file yatim.
    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
        Storage::disk('public')->delete($user->avatar);
    }

    $path = $request->file('avatar')->store('avatars', 'public');

    $user->update(['avatar' => $path]);

    return response()->json($user->fresh());
}

    public function changePassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'current_password' => 'required|string',
        'password' => 'required|string|min:6|confirmed',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
    }

    $user = $request->user();

    if (! Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'message' => 'Password lama salah',
            'errors' => ['current_password' => ['Password lama salah']],
        ], 422);
    }

    $user->update(['password' => Hash::make($request->password)]);

    return response()->json(['message' => 'Password berhasil diubah']);
}

}
