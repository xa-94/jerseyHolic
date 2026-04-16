<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function adminLogin(string $username, string $password): array
    {
        $lockKey = 'login_attempts:admin:' . $username;
        $this->checkLoginAttempts($lockKey);

        $admin = Admin::where('username', $username)->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            $this->incrementLoginAttempts($lockKey);
            throw new BusinessException(ErrorCode::UNAUTHORIZED, '用户名或密码错误');
        }

        if ($admin->status !== 1) {
            throw new BusinessException(ErrorCode::ACCOUNT_DISABLED, '账号已被禁用');
        }

        $this->clearLoginAttempts($lockKey);
        $token = $admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        return [
            'user' => $admin,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function merchantLogin(string $email, string $password): array
    {
        $lockKey = 'login_attempts:merchant:' . $email;
        $this->checkLoginAttempts($lockKey);

        $merchant = Merchant::where('email', $email)->first();

        if (!$merchant || !Hash::check($password, $merchant->password)) {
            $this->incrementLoginAttempts($lockKey);
            throw new BusinessException(ErrorCode::UNAUTHORIZED, '邮箱或密码错误');
        }

        if ($merchant->status !== 1) {
            throw new BusinessException(ErrorCode::ACCOUNT_DISABLED, '账号已被禁用');
        }

        $this->clearLoginAttempts($lockKey);
        $token = $merchant->createToken('merchant-token', ['role:merchant'])->plainTextToken;

        return [
            'user' => $merchant,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function buyerLogin(string $email, string $password): array
    {
        $lockKey = 'login_attempts:buyer:' . $email;
        $this->checkLoginAttempts($lockKey);

        $customer = Customer::where('email', $email)->first();

        if (!$customer || !Hash::check($password, $customer->password)) {
            $this->incrementLoginAttempts($lockKey);
            throw new BusinessException(ErrorCode::UNAUTHORIZED, '邮箱或密码错误');
        }

        if ($customer->status !== 1) {
            throw new BusinessException(ErrorCode::ACCOUNT_DISABLED, '账号已被禁用');
        }

        $this->clearLoginAttempts($lockKey);
        $token = $customer->createToken('buyer-token', ['role:buyer'])->plainTextToken;

        return [
            'user' => $customer,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function buyerRegister(array $data): array
    {
        $customer = Customer::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 1,
        ]);

        $token = $customer->createToken('buyer-token', ['role:buyer'])->plainTextToken;

        return [
            'user' => $customer,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function logout(Authenticatable $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function refreshToken(Authenticatable $user): array
    {
        $user->currentAccessToken()->delete();
        $token = $user->createToken('refreshed-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    private function checkLoginAttempts(string $key): void
    {
        $attempts = (int) Cache::get($key, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $minutes = self::LOCKOUT_MINUTES;
            throw new BusinessException(ErrorCode::RATE_LIMIT, "登录失败次数过多，请{$minutes}分钟后再试");
        }
    }

    private function incrementLoginAttempts(string $key): void
    {
        $attempts = (int) Cache::get($key, 0);
        Cache::put($key, $attempts + 1, now()->addMinutes(self::LOCKOUT_MINUTES));
    }

    private function clearLoginAttempts(string $key): void
    {
        Cache::forget($key);
    }
}
