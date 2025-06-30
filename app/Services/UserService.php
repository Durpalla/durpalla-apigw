<?php


namespace App\Services;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserService
{
    protected $user;
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->user = $userRepository;
    }

    public function create($data)
    {
        // request role
        $role = Role::where('id', $data['role'])->first();
        $officer = Auth::user();

        //if merchant user
        $merchant_id = null;
        if( $officer->type == 'merchant' ) {
            if( $officer->hasRole('merchant') ) {
                $merchant_id = $officer->id;
            } else {
                $merchant_id = $officer->merchant_id;
            }
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'designation_id' => $data['designation_id'],
            'mobile' => $data['mobile'],
            'password' => Hash::make($data['password']),
            'type' => $officer->type,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'merchant_id' => $merchant_id,
            'counter_id' =>  (array_key_exists('counter_id', $data) && $data['counter_id']) ? $data['counter_id'] : 0
        ]);
        $user->assignRole($role);
        //upload poster/banner
//        if( $request->file('avatar') ) {
//            $image = $request->file('avatar');
//            $filename = time().'.'.$image->getClientOriginalExtension();
//            $destinationPath = public_path('/avatars');
//            $img = Image::make($image->getRealPath());
//            $img->resize(460, 340, function ($constraint) {
//                $constraint->aspectRatio();
//            })->save($destinationPath.'/'.$filename);
//            $user->profile_pic = 'avatars/' . $filename;
//        }
        return $user;
    }
}
