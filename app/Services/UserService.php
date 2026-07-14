<?php


namespace App\Services;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\User;

class UserService
{
    protected $user;
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->user = $userRepository;
    }

    public function create($data)
    {
        $officer = Auth::user();

        // Merchant desk creates staff on merchant_staff table.
        if ($officer instanceof Merchant || $officer instanceof MerchantStaff || current_merchant_id()) {
            $merchantId = current_merchant_id();
            if ($officer instanceof Merchant) {
                $merchantId = $officer->id;
            } elseif ($officer instanceof MerchantStaff) {
                $merchantId = $officer->merchant_id;
            }

            return MerchantStaff::create([
                'merchant_id' => $merchantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'] ?? 'officer',
                'designation_id' => $data['designation_id'] ?? null,
                'counter_id' => (array_key_exists('counter_id', $data) && $data['counter_id']) ? $data['counter_id'] : null,
                'status' => 1,
                'email_verified_at' => now(),
            ]);
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'password' => Hash::make($data['password']),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
    }
}
